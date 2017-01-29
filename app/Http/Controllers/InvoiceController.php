<?php namespace App\Http\Controllers;

use Auth;
use Session;
use Utils;
use View;
use Input;
use Cache;
use Redirect;
use DB;
use URL;
use App\Models\Invoice;
use Modules\Relations\Models\Relation;
use App\Models\Company;
use Modules\Products\Models\Product;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\TaxRate;
use App\Models\InvoiceDesign;
use App\Models\Activity;
use App\Jobs\SendInvoiceEmail;
use App\Ninja\Repositories\InvoiceRepository;
use Modules\Relations\Repositories\RelationRepository;
use App\Ninja\Repositories\DocumentRepository;
use App\Ninja\Datatables\InvoiceDatatable;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\RecurringInvoiceService;
use App\Http\Requests\InvoiceRequest;
use App\Http\Requests\CreateInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;

class InvoiceController extends BaseController
{
    protected $invoiceRepo;
    protected $relationRepo;
    protected $documentRepo;
    protected $invoiceService;
    protected $paymentService;
    protected $recurringInvoiceService;
    protected $entityType = ENTITY_INVOICE;

    public function __construct(InvoiceRepository $invoiceRepo, RelationRepository $relationRepo, InvoiceService $invoiceService, DocumentRepository $documentRepo, RecurringInvoiceService $recurringInvoiceService, PaymentService $paymentService)
    {
        // parent::__construct();

        $this->invoiceRepo = $invoiceRepo;
        $this->clientRepo = $relationRepo;
        $this->invoiceService = $invoiceService;
        $this->recurringInvoiceService = $recurringInvoiceService;
        $this->paymentService = $paymentService;
    }

    public function index()
    {
        $data = [
            'title' => trans('texts.invoices'),
            'entityType' => ENTITY_INVOICE,
            'statuses' => Invoice::getStatuses(),
            'datatable' => new InvoiceDatatable(),
        ];

        return response()->view('list_wrapper', $data);
    }

    public function getDatatable($relationPublicId = null)
    {
        $companyId = Auth::user()->company_id;
        $search = Input::get('sSearch');

        return $this->invoiceService->getDatatable($companyId, $relationPublicId, ENTITY_INVOICE, $search);
    }

    public function getRecurringDatatable($relationPublicId = null)
    {
        $companyId = Auth::user()->company_id;
        $search = Input::get('sSearch');

        return $this->recurringInvoiceService->getDatatable($companyId, $relationPublicId, ENTITY_RECURRING_INVOICE, $search);
    }

    public function edit(InvoiceRequest $request, $publicId, $clone = false)
    {
        $company = Auth::user()->company;
        $invoice = $request->entity()->load('invitations', 'company.country', 'relation.contacts', 'relation.country', 'invoice_items', 'documents', 'expenses', 'expenses.documents', 'payments');

        $entityType = $invoice->getEntityType();

        $contactIds = DB::table('invitations')
            ->join('contacts', 'contacts.id', '=', 'invitations.contact_id')
            ->where('invitations.invoice_id', '=', $invoice->id)
            ->where('invitations.company_id', '=', Auth::user()->company_id)
            ->where('invitations.deleted_at', '=', null)
            ->select('contacts.public_id')->pluck('public_id');

        $relations = Relation::scope()->withTrashed()->with('contacts', 'country');

        if ($clone) {
            $invoice->id = $invoice->public_id = null;
            $invoice->is_public = false;
            $invoice->invoice_number = $company->getNextNumber($invoice);
            $invoice->balance = $invoice->amount;
            $invoice->invoice_status_id = 0;
            $invoice->invoice_date = Utils::today();
            $method = 'POST';
            $url = "{$entityType}s";
        } else {
            $method = 'PUT';
            $url = "{$entityType}s/{$invoice->public_id}";
            $relations->whereId($invoice->relation_id);
        }

        $invoice->invoice_date = Utils::fromSqlDate($invoice->invoice_date);
        $invoice->recurring_due_date = $invoice->due_date;// Keep in SQL form
        $invoice->due_date = Utils::fromSqlDate($invoice->due_date);
        $invoice->start_date = Utils::fromSqlDate($invoice->start_date);
        $invoice->end_date = Utils::fromSqlDate($invoice->end_date);
        $invoice->last_sent_date = Utils::fromSqlDate($invoice->last_sent_date);
        $invoice->features = [
            'customize_invoice_design' => Auth::user()->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN),
            'remove_created_by' => Auth::user()->hasFeature(FEATURE_REMOVE_CREATED_BY),
            'invoice_settings' => Auth::user()->hasFeature(FEATURE_INVOICE_SETTINGS),
        ];

        $lastSent = ($invoice->is_recurring && $invoice->last_sent_date) ? $invoice->recurring_invoices->last() : null;

        if(!Auth::user()->hasPermission('view_all')){
            $relations = $relations->where('relations.user_id', '=', Auth::user()->id);
        }

        $data = [
                'relations' => $relations->get(),
                'entityType' => $entityType,
                'showBreadcrumbs' => $clone,
                'invoice' => $invoice,
                'method' => $method,
                'invitationContactIds' => $contactIds,
                'url' => $url,
                'title' => trans("texts.edit_{$entityType}"),
                'relation' => $invoice->relation,
                'isRecurring' => $invoice->is_recurring,
                'lastSent' => $lastSent];
        $data = array_merge($data, self::getViewModel($invoice));

        if ($invoice->isSent() && $invoice->getAutoBillEnabled() && !$invoice->isPaid()) {
            $data['autoBillChangeWarning'] = $invoice->relation->autoBillLater();
        }

        if ($clone) {
            $data['formIsChanged'] = true;
        }

        // Set the invitation data on the relation's contacts
        if ( ! $clone) {
            $relations = $data['relations'];
            foreach ($relations as $relation) {
                if ($relation->id != $invoice->relation->id) {
                    continue;
                }

                foreach ($invoice->invitations as $invitation) {
                    foreach ($relation->contacts as $contact) {
                        if ($invitation->contact_id == $contact->id) {
                            $contact->email_error = $invitation->email_error;
                            $contact->invitation_link = $invitation->getLink();
                            $contact->invitation_viewed = $invitation->viewed_date && $invitation->viewed_date != '0000-00-00 00:00:00' ? $invitation->viewed_date : false;
                            $contact->invitation_openend = $invitation->opened_date && $invitation->opened_date != '0000-00-00 00:00:00' ? $invitation->opened_date : false;
                            $contact->invitation_status = $contact->email_error ? false : $invitation->getStatus();
                            $contact->invitation_signature_svg = $invitation->signatureDiv();
                        }
                    }
                }

                break;
            }
        }

        return View::make('invoices.edit', $data);
    }

    public function create(InvoiceRequest $request, $relationPublicId = 0, $isRecurring = false)
    {
        $company = Auth::user()->company;

        $entityType = $isRecurring ? ENTITY_RECURRING_INVOICE : ENTITY_INVOICE;
        $relationId = null;

        if ($request->relation_id) {
            $relationId = Relation::getPrivateId($request->relation_id);
        }

        $invoice = $company->createInvoice($entityType, $relationId);
        $invoice->public_id = 0;

        $relations = Relation::scope()->with('contacts', 'country')->orderBy('name');
        if (!Auth::user()->hasPermission('view_all')) {
            $relations = $relations->where('relations.user_id', '=', Auth::user()->id);
        }

        $data = [
            'relations' => $relations->get(),
            'entityType' => $invoice->getEntityType(),
            'invoice' => $invoice,
            'method' => 'POST',
            'url' => 'invoices',
            'title' => trans('texts.new_invoice'),
        ];
        $data = array_merge($data, self::getViewModel($invoice));

        return View::make('invoices.edit', $data);
    }

    public function createRecurring(InvoiceRequest $request, $relationPublicId = 0)
    {
        return self::create($request, $relationPublicId, true);
    }

    private static function getViewModel($invoice)
    {
        $recurringHelp = '';
        $recurringDueDateHelp = '';
        $recurringDueDates = [];

        foreach (preg_split("/((\r?\n)|(\r\n?))/", trans('texts.recurring_help')) as $line) {
            $parts = explode('=>', $line);
            if (count($parts) > 1) {
                $line = $parts[0].' => '.Utils::processVariables($parts[0]);
                $recurringHelp .= '<li>'.strip_tags($line).'</li>';
            } else {
                $recurringHelp .= $line;
            }
        }

        foreach (preg_split("/((\r?\n)|(\r\n?))/", trans('texts.recurring_due_date_help')) as $line) {
            $parts = explode('=>', $line);
            if (count($parts) > 1) {
                $line = $parts[0].' => '.Utils::processVariables($parts[0]);
                $recurringDueDateHelp .= '<li>'.strip_tags($line).'</li>';
            } else {
                $recurringDueDateHelp .= $line;
            }
        }

        // Create due date options
        $recurringDueDates = [
            trans('texts.use_client_terms') => ['value' => '', 'class' => 'monthly weekly'],
        ];

        $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
        for($i = 1; $i < 31; $i++){
            if ($i >= 11 && $i <= 13) $ordinal = $i. 'th';
            else $ordinal = $i . $ends[$i % 10];

            $dayStr = str_pad($i, 2, '0', STR_PAD_LEFT);
            $str = trans('texts.day_of_month', ['ordinal'=>$ordinal]);

            $recurringDueDates[$str] = ['value' => "1998-01-$dayStr", 'data-num' => $i, 'class' => 'monthly'];
        }
        $recurringDueDates[trans('texts.last_day_of_month')] = ['value' => '1998-01-31', 'data-num' => 31, 'class' => 'monthly'];


        $daysOfWeek = [
            trans('texts.sunday'),
            trans('texts.monday'),
            trans('texts.tuesday'),
            trans('texts.wednesday'),
            trans('texts.thursday'),
            trans('texts.friday'),
            trans('texts.saturday'),
        ];
        foreach(['1st','2nd','3rd','4th'] as $i=>$ordinal){
            foreach($daysOfWeek as $j=>$dayOfWeek){
                $str = trans('texts.day_of_week_after', ['ordinal' => $ordinal, 'day' => $dayOfWeek]);

                $day = $i * 7 + $j  + 1;
                $dayStr = str_pad($day, 2, '0', STR_PAD_LEFT);
                $recurringDueDates[$str] = ['value' => "1998-02-$dayStr", 'data-num' => $day, 'class' => 'weekly'];
            }
        }

        // Tax rate $options
        $company = Auth::user()->company;
        $rates = TaxRate::scope()->orderBy('name')->get();
        $options = [];
        $defaultTax = false;

        foreach ($rates as $rate) {
            $name = $rate->name . ' ' . ($rate->rate+0) . '%';
            if ($rate->is_inclusive) {
                $name .= ' - ' . trans('texts.inclusive');
            }
            $options[($rate->is_inclusive ? '1 ' : '0 ') . $rate->rate . ' ' . $rate->name] = $name;

            // load default invoice tax
            if ($rate->id == $company->default_tax_rate_id) {
                $defaultTax = $rate;
            }
        }

        // Check for any taxes which have been deleted
        if ($invoice->exists) {
            foreach ($invoice->getTaxes() as $key => $rate) {
                if (isset($options[$key])) {
                    continue;
                }
                $options['0 ' . $key] = $rate['name'] . ' ' . $rate['rate'] . '%';
            }
        }

        return [
            'data' => Input::old('data'),
            'company' => Auth::user()->company->load('country'),
            'products' => Product::scope()->with('default_tax_rate')->orderBy('product_key')->get(),
            'taxRateOptions' => $options,
            'defaultTax' => $defaultTax,
            'currencies' => Cache::get('currencies'),
            'sizes' => Cache::get('sizes'),
            'paymentTerms' => Cache::get('paymentTerms'),
            'invoiceDesigns' => InvoiceDesign::getDesigns(),
            'invoiceFonts' => Cache::get('fonts'),
            'frequencies' => [
                1 => trans('texts.freq_weekly'),
                2 => trans('texts.freq_two_weeks'),
                3 => trans('texts.freq_four_weeks'),
                4 => trans('texts.freq_monthly'),
                5 => trans('texts.freq_three_months'),
                6 => trans('texts.freq_six_months'),
                7 => trans('texts.freq_annually'),
            ],
            'recurringDueDates' => $recurringDueDates,
            'recurringHelp' => $recurringHelp,
            'recurringDueDateHelp' => $recurringDueDateHelp,
            'invoiceLabels' => Auth::user()->company->getInvoiceLabels(),
            'tasks' => Session::get('tasks') ? json_encode(Session::get('tasks')) : null,
            'expenseCurrencyId' => Session::get('expenseCurrencyId') ?: null,
            'expenses' => Session::get('expenses') ? Expense::scope(Session::get('expenses'))->with('documents', 'expense_category')->get() : [],
        ];

    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(CreateInvoiceRequest $request)
    {
        $data = $request->input();


        $data['documents'] = $request->file('documents');

        $action = Input::get('action');
        $entityType = Input::get('entityType');

        $invoice = $this->invoiceService->save($data);
        $entityType = $invoice->getEntityType();
        $message = trans("texts.created_{$entityType}");

        $input = $request->input();
        $relationPublicId = isset($input['relation']['id']) ? $input['relation']['id'] : false;
        if ($relationPublicId == '-1') {
            $message = $message.' '.trans('texts.and_created_relation');
        }

        Session::flash('message', $message);

        if ($action == 'email') {
            $this->emailInvoice($invoice, Input::get('pdfupload'));
        }

        return url($invoice->getRoute());
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function update(UpdateInvoiceRequest $request)
    {
        $data = $request->input();
        $data['documents'] = $request->file('documents');

        $action = Input::get('action');
        $entityType = Input::get('entityType');

        $invoice = $this->invoiceService->save($data, $request->entity());
        $entityType = $invoice->getEntityType();
        $message = trans("texts.updated_{$entityType}");
        Session::flash('message', $message);

        if ($action == 'clone') {
            return url(sprintf('%ss/%s/clone', $entityType, $invoice->public_id));
        } elseif ($action == 'convert') {
            return $this->convertQuote($request, $invoice->public_id);
        } elseif ($action == 'email') {
            $this->emailInvoice($invoice, Input::get('pdfupload'));
        }

        return url($invoice->getRoute());
    }


    private function emailInvoice($invoice, $pdfUpload)
    {
        $entityType = $invoice->getEntityType();
        $pdfUpload = Utils::decodePDF($pdfUpload);

        if (!Auth::user()->confirmed) {
            $errorMessage = trans(Auth::user()->registered ? 'texts.confirmation_required' : 'texts.registration_required');
            Session::flash('error', $errorMessage);
            return Redirect::to('invoices/'.$invoice->public_id.'/edit');
        }

        if ($invoice->is_recurring) {
            $response = $this->emailRecurringInvoice($invoice);
        } else {
            app('App\Ninja\Mailers\ContactMailer')->sendInvoice($invoice, false, $pdfUpload);
            $response = true;
            //$this->dispatch(new SendInvoiceEmail($invoice, false, $pdfUpload));
            //return true;
        }

        if ($response === true) {
            $message = trans("texts.emailed_{$entityType}");
            Session::flash('message', $message);
        } else {
            Session::flash('error', $response);
        }
    }

    private function emailRecurringInvoice(&$invoice)
    {
        if (!$invoice->shouldSendToday()) {
            if ($date = $invoice->getNextSendDate()) {
                $date = $invoice->company->formatDate($date);
                $date .= ' ' . DEFAULT_SEND_RECURRING_HOUR . ':00 am ' . $invoice->company->getTimezone();
                return trans('texts.recurring_too_soon', ['date' => $date]);
            } else {
                return trans('texts.no_longer_running');
            }
        }

        // switch from the recurring invoice to the generated invoice
        $invoice = $this->invoiceRepo->createRecurringInvoice($invoice);

        // in case auto-bill is enabled then a receipt has been sent
        if ($invoice->isPaid()) {
            return true;
        } else {
            return app('App\Ninja\Mailers\ContactMailer')->sendInvoice($invoice);
            //$this->dispatch(new SendInvoiceEmail($invoice));
            //return true;
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int      $id
     * @return Response
     */
    public function show($publicId)
    {
        Session::reflash();

        return Redirect::to("invoices/$publicId/edit");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function bulk($entityType = ENTITY_INVOICE)
    {
        $action = Input::get('bulk_action') ?: Input::get('action');;
        $ids = Input::get('bulk_public_id') ?: (Input::get('public_id') ?: Input::get('ids'));
        $count = $this->invoiceService->bulk($ids, $action);

        if ($count > 0) {
            if ($action == 'markSent') {
                $key = 'marked_sent_invoice';
            } elseif ($action == 'emailInvoice') {
                $key = 'emailed_' . $entityType;
            } elseif ($action == 'markPaid') {
                $key = 'created_payment';
            } else {
                $key = "{$action}d_{$entityType}";
            }
            $message = Utils::pluralize($key, $count);
            Session::flash('message', $message);
        }

        return $this->returnBulk($entityType, $action, $ids);
    }

    public function convertQuote(InvoiceRequest $request)
    {
        $clone = $this->invoiceService->convertQuote($request->entity());

        Session::flash('message', trans('texts.converted_to_invoice'));

        return url('invoices/' . $clone->public_id);
    }

    public function cloneInvoice(InvoiceRequest $request, $publicId)
    {
        return self::edit($request, $publicId, true);
    }

    public function invoiceHistory(InvoiceRequest $request)
    {
        $invoice = $request->entity();
        $paymentId = $request->payment_id ? Payment::getPrivateId($request->payment_id) : false;

        $invoice->load('user', 'invoice_items', 'documents', 'expenses', 'expenses.documents', 'company.country', 'relation.contacts', 'relation.country');
        $invoice->invoice_date = Utils::fromSqlDate($invoice->invoice_date);
        $invoice->due_date = Utils::fromSqlDate($invoice->due_date);
        $invoice->features = [
            'customize_invoice_design' => Auth::user()->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN),
            'remove_created_by' => Auth::user()->hasFeature(FEATURE_REMOVE_CREATED_BY),
            'invoice_settings' => Auth::user()->hasFeature(FEATURE_INVOICE_SETTINGS),
        ];
        $invoice->invoice_type_id = intval($invoice->invoice_type_id);

        $activities = Activity::scope(false, $invoice->company_id);
        if ($paymentId) {
            $activities->whereIn('activity_type_id', [ACTIVITY_TYPE_CREATE_PAYMENT])
                       ->where('payment_id', '=', $paymentId);
        } else {
            $activities->whereIn('activity_type_id', [ACTIVITY_TYPE_UPDATE_INVOICE, ACTIVITY_TYPE_UPDATE_QUOTE])
                       ->where('invoice_id', '=', $invoice->id);
        }
        $activities = $activities->orderBy('id', 'desc')
                                 ->get(['id', 'created_at', 'user_id', 'json_backup', 'activity_type_id', 'payment_id']);

        $versionsJson = [];
        $versionsSelect = [];
        $lastId = false;
        //dd($activities->toArray());
        foreach ($activities as $activity) {
            if ($backup = json_decode($activity->json_backup)) {
                $backup->invoice_date = Utils::fromSqlDate($backup->invoice_date);
                $backup->due_date = Utils::fromSqlDate($backup->due_date);
                $backup->features = [
                    'customize_invoice_design' => Auth::user()->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN),
                    'remove_created_by' => Auth::user()->hasFeature(FEATURE_REMOVE_CREATED_BY),
                    'invoice_settings' => Auth::user()->hasFeature(FEATURE_INVOICE_SETTINGS),
                ];
                $backup->invoice_type_id = isset($backup->invoice_type_id) && intval($backup->invoice_type_id) == INVOICE_TYPE_QUOTE;
                $backup->company = $invoice->company->toArray();

                $versionsJson[$paymentId ? 0 : $activity->id] = $backup;
                $key = Utils::timestampToDateTimeString(strtotime($activity->created_at)) . ' - ' . $activity->user->getDisplayName();
                $versionsSelect[$lastId ?: 0] = $key;
                $lastId = $activity->id;
            } else {
                Utils::logError('Failed to parse invoice backup');
            }
        }

        // Show the current version as the last in the history
        if ( ! $paymentId) {
            $versionsSelect[$lastId] = Utils::timestampToDateTimeString(strtotime($invoice->created_at)) . ' - ' . $invoice->user->getDisplayName();
        }

        $data = [
            'invoice' => $invoice,
            'versionsJson' => json_encode($versionsJson),
            'versionsSelect' => $versionsSelect,
            'invoiceDesigns' => InvoiceDesign::getDesigns(),
            'invoiceFonts' => Cache::get('fonts'),
            'paymentId' => $paymentId,
        ];

        return View::make('invoices.history', $data);
    }

    public function checkInvoiceNumber($invoicePublicId = false)
    {
        $invoiceNumber = request()->invoice_number;

        $query = Invoice::scope()
                    ->whereInvoiceNumber($invoiceNumber)
                    ->withTrashed();

        if ($invoicePublicId) {
            $query->where('public_id', '!=', $invoicePublicId);
        }

        $count = $query->count();

        return $count ? RESULT_FAILURE : RESULT_SUCCESS;
    }

}
