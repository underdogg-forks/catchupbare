<?php namespace App\Ninja\Repositories;

use App\Models\Company;
use DB;
use Utils;
use Auth;
use Carbon;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Invitation;
use App\Models\Product;
use App\Models\Task;
use App\Models\Document;
use App\Models\Expense;
use App\Services\PaymentService;
use App\Jobs\SendInvoiceEmail;

class InvoiceRepository extends BaseRepository
{
    protected $documentRepo;

    public function getClassName()
    {
        return 'App\Models\Invoice';
    }

    public function __construct(PaymentService $paymentService, DocumentRepository $documentRepo, PaymentRepository $paymentRepo)
    {
        $this->documentRepo = $documentRepo;
        $this->paymentService = $paymentService;
        $this->paymentRepo = $paymentRepo;
    }

    public function all()
    {
        return Invoice::scope()
                ->invoiceType(INVOICE_TYPE_STANDARD)
                ->with('user', 'client.contacts', 'invoice_status')
                ->withTrashed()
                ->where('is_recurring', '=', false)
                ->get();
    }

    public function getInvoices($companyId, $clientPublicId = false, $entityType = ENTITY_INVOICE, $filter = false)
    {
        $query = DB::table('invoices')
            ->join('companies', 'companies.id', '=', 'invoices.company_id')
            ->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->join('invoice_statuses', 'invoice_statuses.id', '=', 'invoices.invoice_status_id')
            ->join('contacts', 'contacts.client_id', '=', 'clients.id')
            ->where('invoices.company_id', '=', $companyId)
            ->where('contacts.deleted_at', '=', null)
            ->where('invoices.is_recurring', '=', false)
            ->where('contacts.is_primary', '=', true)
            //->whereRaw('(clients.name != "" or contacts.first_name != "" or contacts.last_name != "" or contacts.email != "")') // filter out buy now invoices
            ->select(
                DB::raw('COALESCE(clients.currency_id, companies.currency_id) currency_id'),
                DB::raw('COALESCE(clients.country_id, companies.country_id) country_id'),
                'clients.public_id as client_public_id',
                'clients.user_id as client_user_id',
                'invoice_number',
                'invoice_number as quote_number',
                'invoice_status_id',
                DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                'invoices.public_id',
                'invoices.amount',
                'invoices.balance',
                'invoices.invoice_date as date',
                'invoices.due_date',
                'invoices.due_date as valid_until',
                'invoice_statuses.name as status',
                'invoice_statuses.name as invoice_status_name',
                'contacts.first_name',
                'contacts.last_name',
                'contacts.email',
                'invoices.quote_id',
                'invoices.quote_invoice_id',
                'invoices.deleted_at',
                'invoices.is_deleted',
                'invoices.partial',
                'invoices.user_id',
                'invoices.is_public'
            );

        $this->applyFilters($query, $entityType, ENTITY_INVOICE);

        if ($statuses = session('entity_status_filter:' . $entityType)) {
            $statuses = explode(',', $statuses);
            $query->where(function ($query) use ($statuses) {
                foreach ($statuses as $status) {
                    if (in_array($status, \App\Models\EntityModel::$statuses)) {
                        continue;
                    }
                    $query->orWhere('invoice_status_id', '=', $status);
                }
                if (in_array(INVOICE_STATUS_OVERDUE, $statuses)) {
                    $query->orWhere(function ($query) use ($statuses) {
                        $query->where('invoices.balance', '>', 0)
                              ->where('invoices.due_date', '<', date('Y-m-d'));
                    });
                }
            });
        }

        if ($clientPublicId) {
            $query->where('clients.public_id', '=', $clientPublicId);
        } else {
            $query->whereNull('clients.deleted_at');
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('invoices.invoice_number', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function getRecurringInvoices($companyId, $clientPublicId = false, $filter = false)
    {
        $query = DB::table('invoices')
                    ->join('companies', 'companies.id', '=', 'invoices.company_id')
                    ->join('clients', 'clients.id', '=', 'invoices.client_id')
                    ->join('frequencies', 'frequencies.id', '=', 'invoices.frequency_id')
                    ->join('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->where('invoices.company_id', '=', $companyId)
                    ->where('invoices.invoice_type_id', '=', INVOICE_TYPE_STANDARD)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('invoices.is_recurring', '=', true)
                    ->where('contacts.is_primary', '=', true)
                    ->select(
                        DB::raw('COALESCE(clients.currency_id, companies.currency_id) currency_id'),
                        DB::raw('COALESCE(clients.country_id, companies.country_id) country_id'),
                        'clients.public_id as client_public_id',
                        DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                        'invoices.public_id',
                        'invoices.amount',
                        'frequencies.name as frequency',
                        'invoices.start_date',
                        'invoices.end_date',
                        'invoices.last_sent_date',
                        'invoices.last_sent_date as last_sent',
                        'contacts.first_name',
                        'contacts.last_name',
                        'contacts.email',
                        'invoices.deleted_at',
                        'invoices.is_deleted',
                        'invoices.user_id'
                    );

        if ($clientPublicId) {
            $query->where('clients.public_id', '=', $clientPublicId);
        } else {
            $query->whereNull('clients.deleted_at');
        }

        $this->applyFilters($query, ENTITY_RECURRING_INVOICE, ENTITY_INVOICE);

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('invoices.invoice_number', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function getClientRecurringDatatable($contactId)
    {
        $query = DB::table('invitations')
          ->join('companies', 'companies.id', '=', 'invitations.company_id')
          ->join('invoices', 'invoices.id', '=', 'invitations.invoice_id')
          ->join('clients', 'clients.id', '=', 'invoices.client_id')
          ->join('frequencies', 'frequencies.id', '=', 'invoices.frequency_id')
          ->where('invitations.contact_id', '=', $contactId)
          ->where('invitations.deleted_at', '=', null)
          ->where('invoices.invoice_type_id', '=', INVOICE_TYPE_STANDARD)
          ->where('invoices.is_deleted', '=', false)
          ->where('clients.deleted_at', '=', null)
          ->where('invoices.is_recurring', '=', true)
          ->where('invoices.is_public', '=', true)
          ->whereIn('invoices.auto_bill', [AUTO_BILL_OPT_IN, AUTO_BILL_OPT_OUT])
          //->where('invoices.start_date', '>=', date('Y-m-d H:i:s'))
          ->select(
                DB::raw('COALESCE(clients.currency_id, companies.currency_id) currency_id'),
                DB::raw('COALESCE(clients.country_id, companies.country_id) country_id'),
                'invitations.invitation_key',
                'invoices.invoice_number',
                'invoices.due_date',
                'clients.public_id as client_public_id',
                'clients.name as client_name',
                'invoices.public_id',
                'invoices.amount',
                'invoices.start_date',
                'invoices.end_date',
                'invoices.client_enable_auto_bill',
                'frequencies.name as frequency'
            );

        $table = \Datatable::query($query)
            ->addColumn('frequency', function ($model) { return $model->frequency; })
            ->addColumn('start_date', function ($model) { return Utils::fromSqlDate($model->start_date); })
            ->addColumn('end_date', function ($model) { return Utils::fromSqlDate($model->end_date); })
            ->addColumn('amount', function ($model) { return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id); })
            ->addColumn('client_enable_auto_bill', function ($model) {
                if ($model->client_enable_auto_bill) {
                    return trans('texts.enabled') . ' - <a href="javascript:setAutoBill('.$model->public_id.',false)">'.trans('texts.disable').'</a>';
                } else {
                    return trans('texts.disabled') . ' - <a href="javascript:setAutoBill('.$model->public_id.',true)">'.trans('texts.enable').'</a>';
                }
            });

        return $table->make();
    }

    public function getClientDatatable($contactId, $entityType, $search)
    {
        $query = DB::table('invitations')
          ->join('companies', 'companies.id', '=', 'invitations.company_id')
          ->join('invoices', 'invoices.id', '=', 'invitations.invoice_id')
          ->join('clients', 'clients.id', '=', 'invoices.client_id')
          ->join('contacts', 'contacts.client_id', '=', 'clients.id')
          ->where('invitations.contact_id', '=', $contactId)
          ->where('invitations.deleted_at', '=', null)
          ->where('invoices.invoice_type_id', '=', $entityType == ENTITY_QUOTE ? INVOICE_TYPE_QUOTE : INVOICE_TYPE_STANDARD)
          ->where('invoices.is_deleted', '=', false)
          ->where('clients.deleted_at', '=', null)
          ->where('contacts.deleted_at', '=', null)
          ->where('contacts.is_primary', '=', true)
          ->where('invoices.is_recurring', '=', false)
          ->where('invoices.is_public', '=', true)
          // Only show paid invoices for ninja companies
          ->whereRaw(sprintf("((companies.acc_key != '%s' and companies.acc_key != '%s') or invoices.invoice_status_id = %d)", env('NINJA_LICENSE_COMPANY_KEY'), NINJA_COMPANY_KEY, INVOICE_STATUS_PAID))
          ->select(
                DB::raw('COALESCE(clients.currency_id, companies.currency_id) currency_id'),
                DB::raw('COALESCE(clients.country_id, companies.country_id) country_id'),
                'invitations.invitation_key',
                'invoices.invoice_number',
                'invoices.invoice_date',
                'invoices.balance as balance',
                'invoices.due_date',
                'clients.public_id as client_public_id',
                DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                'invoices.public_id',
                'invoices.amount',
                'invoices.start_date',
                'invoices.end_date',
                'invoices.partial'
            );

        $table = \Datatable::query($query)
            ->addColumn('invoice_number', function ($model) use ($entityType) { return link_to('/view/'.$model->invitation_key, $model->invoice_number)->toHtml(); })
            ->addColumn('invoice_date', function ($model) { return Utils::fromSqlDate($model->invoice_date); })
            ->addColumn('amount', function ($model) { return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id); });

        if ($entityType == ENTITY_INVOICE) {
            $table->addColumn('balance', function ($model) {
                return $model->partial > 0 ?
                    trans('texts.partial_remaining', [
                        'partial' => Utils::formatMoney($model->partial, $model->currency_id, $model->country_id),
                        'balance' => Utils::formatMoney($model->balance, $model->currency_id, $model->country_id)
                    ]) :
                    Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
            });
        }

        return $table->addColumn('due_date', function ($model) { return Utils::fromSqlDate($model->due_date); })
            ->make();
    }

    /**
     * @param array $data
     * @param Invoice|null $invoice
     * @return Invoice|mixed
     */
    public function save(array $data, Invoice $invoice = null)
    {
        /** @var Company $company */
        $company = \Auth::user()->company;
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        $isNew = !$publicId || $publicId == '-1';

        if ($invoice) {
            // do nothing
            $entityType = $invoice->getEntityType();
        } elseif ($isNew) {
            $entityType = ENTITY_INVOICE;
            if (isset($data['is_recurring']) && filter_var($data['is_recurring'], FILTER_VALIDATE_BOOLEAN)) {
                $entityType = ENTITY_RECURRING_INVOICE;
            } elseif (isset($data['is_quote']) && filter_var($data['is_quote'], FILTER_VALIDATE_BOOLEAN)) {
                $entityType = ENTITY_QUOTE;
            }
            $invoice = $company->createInvoice($entityType, $data['client_id']);
            $invoice->invoice_date = Utils::today();
            if (isset($data['has_tasks']) && filter_var($data['has_tasks'], FILTER_VALIDATE_BOOLEAN)) {
                $invoice->has_tasks = true;
            }
            if (isset($data['has_expenses']) && filter_var($data['has_expenses'], FILTER_VALIDATE_BOOLEAN)) {
                $invoice->has_expenses = true;
            }
            if ($company->payment_terms != 0) {
               $invoice->due_date = $company->defaultDueDate();
            }
        } else {
            $invoice = Invoice::scope($publicId)->firstOrFail();
            if (Utils::isNinjaDev()) {
                \Log::warning('Entity not set in invoice repo save');
            }
        }

        if ($invoice->is_deleted) {
            return $invoice;
        }

        if (isset($data['is_public']) && filter_var($data['is_public'], FILTER_VALIDATE_BOOLEAN)) {
            $invoice->is_public = true;
            if ( ! $invoice->isSent()) {
                $invoice->invoice_status_id = INVOICE_STATUS_SENT;
            }
        }

        $invoice->fill($data);

        if ((isset($data['set_default_terms']) && $data['set_default_terms'])
            || (isset($data['set_default_footer']) && $data['set_default_footer'])) {
            if (isset($data['set_default_terms']) && $data['set_default_terms']) {
                $company->{"{$invoice->getEntityType()}_terms"} = trim($data['terms']);
            }
            if (isset($data['set_default_footer']) && $data['set_default_footer']) {
                $company->invoice_footer = trim($data['invoice_footer']);
            }
            $company->save();
        }

        if (!empty($data['invoice_number']) && !$invoice->is_recurring) {
            $invoice->invoice_number = trim($data['invoice_number']);
        }

        if (isset($data['discount'])) {
            $invoice->discount = round(Utils::parseFloat($data['discount']), 2);
        }
        if (isset($data['is_amount_discount'])) {
            $invoice->is_amount_discount = $data['is_amount_discount'] ? true : false;
        }
        if (isset($data['invoice_date_sql'])) {
            $invoice->invoice_date = $data['invoice_date_sql'];
        } elseif (isset($data['invoice_date'])) {
            $invoice->invoice_date = Utils::toSqlDate($data['invoice_date']);
        }

        if(isset($data['invoice_status_id'])) {
            if($data['invoice_status_id'] == 0) {
                $data['invoice_status_id'] = INVOICE_STATUS_DRAFT;
            }
            $invoice->invoice_status_id = $data['invoice_status_id'];
        }

        if ($invoice->is_recurring) {
            if ($invoice->start_date && $invoice->start_date != Utils::toSqlDate($data['start_date'])) {
                $invoice->last_sent_date = null;
            }

            $invoice->frequency_id = $data['frequency_id'] ? $data['frequency_id'] : 0;
            $invoice->start_date = Utils::toSqlDate($data['start_date']);
            $invoice->end_date = Utils::toSqlDate($data['end_date']);
            $invoice->client_enable_auto_bill = isset($data['client_enable_auto_bill']) && $data['client_enable_auto_bill'] ? true : false;
            $invoice->auto_bill = isset($data['auto_bill']) ? intval($data['auto_bill']) : AUTO_BILL_OFF;

            if ($invoice->auto_bill < AUTO_BILL_OFF || $invoice->auto_bill > AUTO_BILL_ALWAYS ) {
                $invoice->auto_bill = AUTO_BILL_OFF;
            }

            if (isset($data['recurring_due_date'])) {
                $invoice->due_date = $data['recurring_due_date'];
            } elseif (isset($data['due_date'])) {
                $invoice->due_date = $data['due_date'];
            }
        } else {
            if (!empty($data['due_date']) || !empty($data['due_date_sql'])) {
                $invoice->due_date = isset($data['due_date_sql']) ? $data['due_date_sql'] : Utils::toSqlDate($data['due_date']);
            }
            $invoice->frequency_id = 0;
            $invoice->start_date = null;
            $invoice->end_date = null;
        }

        if (isset($data['terms']) && trim($data['terms'])) {
            $invoice->terms = trim($data['terms']);
        } elseif ($isNew && ! $invoice->is_recurring && $company->{"{$entityType}_terms"}) {
            $invoice->terms = $company->{"{$entityType}_terms"};
        } else {
            $invoice->terms = '';
        }

        $invoice->invoice_footer = (isset($data['invoice_footer']) && trim($data['invoice_footer'])) ? trim($data['invoice_footer']) : (!$publicId && $company->invoice_footer ? $company->invoice_footer : '');
        $invoice->public_notes = isset($data['public_notes']) ? trim($data['public_notes']) : null;

        // process date variables if not recurring
        if(!$invoice->is_recurring) {
            $invoice->terms = Utils::processVariables($invoice->terms);
            $invoice->invoice_footer = Utils::processVariables($invoice->invoice_footer);
            $invoice->public_notes = Utils::processVariables($invoice->public_notes);
        }

        if (isset($data['po_number'])) {
            $invoice->po_number = trim($data['po_number']);
        }

        $invoice->invoice_design_id = isset($data['invoice_design_id']) ? $data['invoice_design_id'] : $company->invoice_design_id;

        // provide backwards compatibility
        if (isset($data['tax_name']) && isset($data['tax_rate'])) {
            $data['tax_name1'] = $data['tax_name'];
            $data['tax_rate1'] = $data['tax_rate'];
        }

        $total = 0;
        $itemTax = 0;

        foreach ($data['invoice_items'] as $item) {
            $item = (array) $item;
            if (!$item['cost'] && !$item['product_key'] && !$item['notes']) {
                continue;
            }

            $invoiceItemCost = round(Utils::parseFloat($item['cost']), 2);
            $invoiceItemQty = round(Utils::parseFloat($item['qty']), 2);

            $lineTotal = $invoiceItemCost * $invoiceItemQty;
            $total += round($lineTotal, 2);
        }

        foreach ($data['invoice_items'] as $item) {
            $item = (array) $item;
            $invoiceItemCost = round(Utils::parseFloat($item['cost']), 2);
            $invoiceItemQty = round(Utils::parseFloat($item['qty']), 2);
            $lineTotal = $invoiceItemCost * $invoiceItemQty;

            if ($invoice->discount > 0) {
                if ($invoice->is_amount_discount) {
                    $lineTotal -= round(($lineTotal/$total) * $invoice->discount, 2);
                } else {
                    $lineTotal -= round($lineTotal * ($invoice->discount/100), 2);
                }
            }

            if (isset($item['tax_rate1']) && Utils::parseFloat($item['tax_rate1']) > 0) {
                $invoiceItemTaxRate = Utils::parseFloat($item['tax_rate1']);
                $itemTax += round($lineTotal * $invoiceItemTaxRate / 100, 2);
            }
            if (isset($item['tax_rate2']) && Utils::parseFloat($item['tax_rate2']) > 0) {
                $invoiceItemTaxRate = Utils::parseFloat($item['tax_rate2']);
                $itemTax += round($lineTotal * $invoiceItemTaxRate / 100, 2);
            }
        }

        if ($invoice->discount > 0) {
            if ($invoice->is_amount_discount) {
                $total -= $invoice->discount;
            } else {
                $discount = round($total * ($invoice->discount/100), 2);
                $total -= $discount;
            }
        }

        if (isset($data['custom_value1'])) {
            $invoice->custom_value1 = round($data['custom_value1'], 2);
            if ($isNew) {
                $invoice->custom_taxes1 = $company->custom_invoice_taxes1 ?: false;
            }
        }
        if (isset($data['custom_value2'])) {
            $invoice->custom_value2 = round($data['custom_value2'], 2);
            if ($isNew) {
                $invoice->custom_taxes2 = $company->custom_invoice_taxes2 ?: false;
            }
        }

        if (isset($data['custom_text_value1'])) {
            $invoice->custom_text_value1 = trim($data['custom_text_value1']);
        }
        if (isset($data['custom_text_value2'])) {
            $invoice->custom_text_value2 = trim($data['custom_text_value2']);
        }

        // custom fields charged taxes
        if ($invoice->custom_value1 && $invoice->custom_taxes1) {
            $total += $invoice->custom_value1;
        }
        if ($invoice->custom_value2 && $invoice->custom_taxes2) {
            $total += $invoice->custom_value2;
        }

        $taxAmount1 = round($total * ($invoice->tax_rate1 ? $invoice->tax_rate1 : 0) / 100, 2);
        $taxAmount2 = round($total * ($invoice->tax_rate2 ? $invoice->tax_rate2 : 0) / 100, 2);
        $total = round($total + $taxAmount1 + $taxAmount2, 2);
        $total += $itemTax;

        // custom fields not charged taxes
        if ($invoice->custom_value1 && !$invoice->custom_taxes1) {
            $total += $invoice->custom_value1;
        }
        if ($invoice->custom_value2 && !$invoice->custom_taxes2) {
            $total += $invoice->custom_value2;
        }

        if ($publicId) {
            $invoice->balance = $total - ($invoice->amount - $invoice->balance);
        } else {
            $invoice->balance = $total;
        }

        if (isset($data['partial'])) {
            $invoice->partial = max(0,min(round(Utils::parseFloat($data['partial']), 2), $invoice->balance));
        }

        $invoice->amount = $total;
        $invoice->save();

        if ($publicId) {
            $invoice->invoice_items()->forceDelete();
        }

        if ( ! empty($data['document_ids'])) {
            $document_ids = array_map('intval', $data['document_ids']);
            foreach ($document_ids as $document_id){
                $document = Document::scope($document_id)->first();
                if($document && Auth::user()->can('edit', $document)){

                    if($document->invoice_id && $document->invoice_id != $invoice->id){
                        // From a clone
                        $document = $document->cloneDocument();
                        $document_ids[] = $document->public_id;// Don't remove this document
                    }

                    $document->invoice_id = $invoice->id;
                    $document->expense_id = null;
                    $document->save();
                }
            }

            if ( ! $invoice->wasRecentlyCreated) {
                foreach ($invoice->documents as $document){
                    if(!in_array($document->public_id, $document_ids)){
                        // Removed
                        // Not checking permissions; deleting a document is just editing the invoice
                        if($document->invoice_id == $invoice->id){
                            // Make sure the document isn't on a clone
                            $document->delete();
                        }
                    }
                }
            }
        }

        foreach ($data['invoice_items'] as $item) {
            $item = (array) $item;
            if (empty($item['cost']) && empty($item['product_key']) && empty($item['notes']) && empty($item['custom_value1']) && empty($item['custom_value2'])) {
                continue;
            }

            $task = false;
            if (isset($item['task_public_id']) && $item['task_public_id']) {
                $task = Task::scope($item['task_public_id'])->where('invoice_id', '=', null)->firstOrFail();
                if(Auth::user()->can('edit', $task)){
                    $task->invoice_id = $invoice->id;
                    $task->client_id = $invoice->client_id;
                    $task->save();
                }
            }

            $expense = false;
            if (isset($item['expense_public_id']) && $item['expense_public_id']) {
                $expense = Expense::scope($item['expense_public_id'])->where('invoice_id', '=', null)->firstOrFail();
                if(Auth::user()->can('edit', $expense)){
                    $expense->invoice_id = $invoice->id;
                    $expense->client_id = $invoice->client_id;
                    $expense->save();
                }
            }

            if ($productKey = trim($item['product_key'])) {
                if (\Auth::user()->company->update_products && ! $invoice->has_tasks && ! $invoice->has_expenses) {
                    $product = Product::findProductByKey($productKey);
                    if (!$product) {
                        if (Auth::user()->can('create', ENTITY_PRODUCT)) {
                            $product = Product::createNew();
                            $product->product_key = trim($item['product_key']);
                        }
                        else{
                            $product = null;
                        }
                    }
                    if ($product && (Auth::user()->can('edit', $product))) {
                        $product->notes = ($task || $expense) ? '' : $item['notes'];
                        $product->cost = $expense ? 0 : $item['cost'];
                        $product->save();
                    }
                }
            }

            $invoiceItem = InvoiceItem::createNew();
            $invoiceItem->product_id = isset($product) ? $product->id : null;
            $invoiceItem->product_key = isset($item['product_key']) ? (trim($invoice->is_recurring ? $item['product_key'] : Utils::processVariables($item['product_key']))) : '';
            $invoiceItem->notes = trim($invoice->is_recurring ? $item['notes'] : Utils::processVariables($item['notes']));
            $invoiceItem->cost = Utils::parseFloat($item['cost']);
            $invoiceItem->qty = Utils::parseFloat($item['qty']);

            if (isset($item['custom_value1'])) {
                $invoiceItem->custom_value1 = $item['custom_value1'];
            }
            if (isset($item['custom_value2'])) {
                $invoiceItem->custom_value2 = $item['custom_value2'];
            }

            // provide backwards compatability
            if (isset($item['tax_name']) && isset($item['tax_rate'])) {
                $item['tax_name1'] = $item['tax_name'];
                $item['tax_rate1'] = $item['tax_rate'];
            }

            $invoiceItem->fill($item);

            $invoice->invoice_items()->save($invoiceItem);
        }

        return $invoice;
    }

    /**
     * @param Invoice $invoice
     * @param null $quotePublicId
     * @return mixed
     */
    public function cloneInvoice(Invoice $invoice, $quotePublicId = null)
    {
        $invoice->load('invitations', 'invoice_items');
        $company = $invoice->company;

        $clone = Invoice::createNew($invoice);
        $clone->balance = $invoice->amount;

        // if the invoice prefix is diff than quote prefix, use the same number for the invoice (if it's available)
        $invoiceNumber = false;
        if ($company->hasInvoicePrefix() && $company->share_counter) {
            $invoiceNumber = $invoice->invoice_number;
            if ($company->quote_number_prefix && strpos($invoiceNumber, $company->quote_number_prefix) === 0) {
                $invoiceNumber = substr($invoiceNumber, strlen($company->quote_number_prefix));
            }
            $invoiceNumber = $company->invoice_number_prefix.$invoiceNumber;
            if (Invoice::scope(false, $company->id)
                    ->withTrashed()
                    ->whereInvoiceNumber($invoiceNumber)
                    ->first()) {
                $invoiceNumber = false;
            }
        }
        $clone->invoice_number = $invoiceNumber ?: $company->getNextNumber($clone);
        $clone->invoice_date = Utils::today();

        foreach ([
          'client_id',
          'discount',
          'is_amount_discount',
          'po_number',
          'is_recurring',
          'frequency_id',
          'start_date',
          'end_date',
          'terms',
          'invoice_footer',
          'public_notes',
          'invoice_design_id',
          'tax_name1',
          'tax_rate1',
          'tax_name2',
          'tax_rate2',
          'amount',
          'invoice_type_id',
          'custom_value1',
          'custom_value2',
          'custom_taxes1',
          'custom_taxes2',
          'partial',
          'custom_text_value1',
          'custom_text_value2',
        ] as $field) {
            $clone->$field = $invoice->$field;
        }

        if ($quotePublicId) {
            $clone->invoice_type_id = INVOICE_TYPE_STANDARD;
            $clone->quote_id = $quotePublicId;
            if ($company->invoice_terms) {
                $clone->terms = $company->invoice_terms;
            }
            if ($company->auto_convert_quote) {
                $clone->is_public = true;
                $clone->invoice_status_id = INVOICE_STATUS_SENT;
            }
        }

        $clone->save();

        if ($quotePublicId) {
            $invoice->quote_invoice_id = $clone->public_id;
            $invoice->save();
        }

        foreach ($invoice->invoice_items as $item) {
            $cloneItem = InvoiceItem::createNew($invoice);

            foreach ([
                'product_id',
                'product_key',
                'notes',
                'cost',
                'qty',
                'tax_name1',
                'tax_rate1',
                'tax_name2',
                'tax_rate2',
            ] as $field) {
                $cloneItem->$field = $item->$field;
            }

            $clone->invoice_items()->save($cloneItem);
        }

        foreach ($invoice->documents as $document) {
            $cloneDocument = $document->cloneDocument();
            $clone->documents()->save($cloneDocument);
        }

        foreach ($invoice->invitations as $invitation) {
            $cloneInvitation = Invitation::createNew($invoice);
            $cloneInvitation->contact_id = $invitation->contact_id;
            $cloneInvitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
            $clone->invitations()->save($cloneInvitation);
        }

        return $clone;
    }

    /**
     * @param Invoice $invoice
     */
    public function emailInvoice(Invoice $invoice)
    {
        app('App\Ninja\Mailers\ContactMailer')->sendInvoice($invoice);
        //dispatch(new SendInvoiceEmail($invoice));
    }

    /**
     * @param Invoice $invoice
     */
    public function markSent(Invoice $invoice)
    {
        $invoice->markSent();
    }

    /**
     * @param Invoice $invoice
     */
    public function markPaid(Invoice $invoice)
    {
        if ( ! $invoice->canBePaid()) {
            return;
        }

        $invoice->markSentIfUnsent();

        $data = [
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'amount' => $invoice->balance,
        ];

        return $this->paymentRepo->save($data);
    }

    /**
     * @param $invitationKey
     * @return Invitation|bool
     */
    public function findInvoiceByInvitation($invitationKey)
    {
        /** @var \App\Models\Invitation $invitation */
        $invitation = Invitation::where('invitation_key', '=', $invitationKey)->first();

        if (!$invitation) {
            return false;
        }

        $invoice = $invitation->invoice;
        if (!$invoice || $invoice->is_deleted) {
            return false;
        }

        $invoice->load('user', 'invoice_items', 'documents', 'invoice_design', 'company.country', 'client.contacts', 'client.country');
        $client = $invoice->client;

        if (!$client || $client->is_deleted) {
            return false;
        }

        return $invitation;
    }

    /**
     * @param $clientId
     * @return mixed
     */
    public function findOpenInvoices($clientId, $entityType = false)
    {
        $query = Invoice::scope()
                    ->invoiceType(INVOICE_TYPE_STANDARD)
                    ->whereClientId($clientId)
                    ->whereIsRecurring(false)
                    ->whereDeletedAt(null)
                    ->where('balance', '>', 0);

        if ($entityType == ENTITY_TASK) {
            $query->whereHasTasks(true);
        } elseif ($entityType == ENTITY_EXPENSE) {
            $query->whereHasTasks(false);
        }

        return $query->where('invoice_status_id', '<', 5)
                ->select(['public_id', 'invoice_number'])
                ->get();
    }

    /**
     * @param Invoice $recurInvoice
     * @return mixed
     */
    public function createRecurringInvoice(Invoice $recurInvoice)
    {
        $recurInvoice->load('company.timezone', 'invoice_items', 'client', 'user');

        if ($recurInvoice->client->deleted_at) {
            return false;
        }

        if (!$recurInvoice->user->confirmed) {
            return false;
        }

        if (!$recurInvoice->shouldSendToday()) {
            return false;
        }

        $invoice = Invoice::createNew($recurInvoice);
        $invoice->is_public = true;
        $invoice->invoice_type_id = INVOICE_TYPE_STANDARD;
        $invoice->client_id = $recurInvoice->client_id;
        $invoice->recurring_invoice_id = $recurInvoice->id;
        $invoice->invoice_number = $recurInvoice->company->getNextNumber($invoice);
        $invoice->amount = $recurInvoice->amount;
        $invoice->balance = $recurInvoice->amount;
        $invoice->invoice_date = Utils::today();
        $invoice->discount = $recurInvoice->discount;
        $invoice->po_number = $recurInvoice->po_number;
        $invoice->public_notes = Utils::processVariables($recurInvoice->public_notes);
        $invoice->terms = Utils::processVariables($recurInvoice->terms ?: $recurInvoice->company->invoice_terms);
        $invoice->invoice_footer = Utils::processVariables($recurInvoice->invoice_footer ?: $recurInvoice->company->invoice_footer);
        $invoice->tax_name1 = $recurInvoice->tax_name1;
        $invoice->tax_rate1 = $recurInvoice->tax_rate1;
        $invoice->tax_name2 = $recurInvoice->tax_name2;
        $invoice->tax_rate2 = $recurInvoice->tax_rate2;
        $invoice->invoice_design_id = $recurInvoice->invoice_design_id;
        $invoice->custom_value1 = $recurInvoice->custom_value1 ?: 0;
        $invoice->custom_value2 = $recurInvoice->custom_value2 ?: 0;
        $invoice->custom_taxes1 = $recurInvoice->custom_taxes1 ?: 0;
        $invoice->custom_taxes2 = $recurInvoice->custom_taxes2 ?: 0;
        $invoice->custom_text_value1 = Utils::processVariables($recurInvoice->custom_text_value1);
        $invoice->custom_text_value2 = Utils::processVariables($recurInvoice->custom_text_value2);
        $invoice->is_amount_discount = $recurInvoice->is_amount_discount;
        $invoice->due_date = $recurInvoice->getDueDate();
        $invoice->save();

        foreach ($recurInvoice->invoice_items as $recurItem) {
            $item = InvoiceItem::createNew($recurItem);
            $item->product_id = $recurItem->product_id;
            $item->qty = $recurItem->qty;
            $item->cost = $recurItem->cost;
            $item->notes = Utils::processVariables($recurItem->notes);
            $item->product_key = Utils::processVariables($recurItem->product_key);
            $item->tax_name1 = $recurItem->tax_name1;
            $item->tax_rate1 = $recurItem->tax_rate1;
            $item->tax_name2 = $recurItem->tax_name2;
            $item->tax_rate2 = $recurItem->tax_rate2;
            $item->custom_value1 = Utils::processVariables($recurItem->custom_value1);
            $item->custom_value2 = Utils::processVariables($recurItem->custom_value2);
            $invoice->invoice_items()->save($item);
        }

        foreach ($recurInvoice->documents as $recurDocument) {
            $document = $recurDocument->cloneDocument();
            $invoice->documents()->save($document);
        }

        foreach ($recurInvoice->invitations as $recurInvitation) {
            $invitation = Invitation::createNew($recurInvitation);
            $invitation->contact_id = $recurInvitation->contact_id;
            $invitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
            $invoice->invitations()->save($invitation);
        }

        $recurInvoice->last_sent_date = date('Y-m-d');
        $recurInvoice->save();

        if ($recurInvoice->getAutoBillEnabled() && !$recurInvoice->company->auto_bill_on_due_date) {
            // autoBillInvoice will check for ACH, so we're not checking here
            if ($this->paymentService->autoBillInvoice($invoice)) {
                // update the invoice reference to match its actual state
                // this is to ensure a 'payment received' email is sent
                $invoice->invoice_status_id = INVOICE_STATUS_PAID;
            }
        }

        return $invoice;
    }

    /**
     * @param Company $company
     * @return mixed
     */
    public function findNeedingReminding(Company $company)
    {
        $dates = [];

        for ($i=1; $i<=3; $i++) {
            if ($date = $company->getReminderDate($i)) {
                $field = $company->{"field_reminder{$i}"} == REMINDER_FIELD_DUE_DATE ? 'due_date' : 'invoice_date';
                $dates[] = "$field = '$date'";
            }
        }

        $sql = implode(' OR ', $dates);
        $invoices = Invoice::invoiceType(INVOICE_TYPE_STANDARD)
                    ->whereCompanyId($company->id)
                    ->where('balance', '>', 0)
                    ->where('is_recurring', '=', false)
                    ->whereRaw('('.$sql.')')
                    ->get();

        return $invoices;
    }
}
