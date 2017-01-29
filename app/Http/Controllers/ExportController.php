<?php namespace App\Http\Controllers;

use Auth;
use Excel;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use App\Ninja\Serializers\ArraySerializer;
use App\Ninja\Transformers\AccountTransformer;
use App\Models\Relation;
use App\Models\Contact;
use App\Models\Credit;
use App\Models\Task;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Vendor;
use App\Models\VendorContact;

/**
 * Class ExportController
 */
class ExportController extends BaseController
{
    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function doExport(Request $request)
    {
        $format = $request->input('format');
        $date = date('Y-m-d');

        // set the filename based on the entity types selected
        if ($request->include == 'all') {
            $fileName = "invoice-ninja-{$date}";
        } else {
            $fields = $request->all();
            $fields = array_filter(array_map(function ($key) {
                if ( ! in_array($key, ['format', 'include', '_token'])) {
                    return $key;
                } else {
                    return null;
                }
            }, array_keys($fields), $fields));
            $fileName = "invoice-ninja-" . join('-', $fields) . "-{$date}";
        }

        if ($format === 'JSON') {
            return $this->returnJSON($request, $fileName);
        } elseif ($format === 'CSV') {
            return $this->returnCSV($request, $fileName);
        } else {
            return $this->returnXLS($request, $fileName);
        }
    }

    /**
     * @param $request
     * @param $fileName
     *
     * @return \Illuminate\Http\JsonResponse
     */
    private function returnJSON($request, $fileName)
    {
        $output = fopen('php://output', 'w') or Utils::fatalError();
        header('Content-Type:application/json');
        header("Content-Disposition:attachment;filename={$fileName}.json");

        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());

        // eager load data, include archived but exclude deleted
        $company = Auth::user()->company;
        $company->load(['relations' => function($query) {
            $query->withArchived()
                  ->with(['contacts', 'invoices' => function($query) {
                      $query->withArchived()
                            ->with(['invoice_items', 'payments' => function($query) {
                                $query->withArchived();
                            }]);
                  }]);
        }]);

        $resource = new Item($company, new AccountTransformer);
        $data = $manager->parseIncludes('relations.invoices.payments')
                    ->createData($resource)
                    ->toArray();

        return response()->json($data);
    }

    /**
     * @param $request
     * @param $fileName
     *
     * @return mixed
     */
    private function returnCSV($request, $fileName)
    {
        $data = $this->getData($request);

        return Excel::create($fileName, function($excel) use ($data) {
            $excel->sheet('', function($sheet) use ($data) {
                $sheet->loadView('export', $data);
            });
        })->download('csv');
    }

    /**
     * @param $request
     * @param $fileName
     *
     * @return mixed
     */
    private function returnXLS($request, $fileName)
    {
        $user = Auth::user();
        $data = $this->getData($request);

        return Excel::create($fileName, function($excel) use ($user, $data) {

            $excel->setTitle($data['title'])
                  ->setCreator($user->getDisplayName())
                  ->setLastModifiedBy($user->getDisplayName())
                  ->setDescription('')
                  ->setSubject('')
                  ->setKeywords('')
                  ->setCategory('')
                  ->setManager('')
                  ->setCorporation($user->company->getDisplayName());

            foreach ($data as $key => $val) {
                if ($key === 'company' || $key === 'title' || $key === 'multiUser') {
                    continue;
                }
                if ($key === 'recurringInvoices') {
                    $key = 'recurring_invoices';
                }
                $label = trans("texts.{$key}");
                $excel->sheet($label, function($sheet) use ($key, $data) {
                    if ($key === 'quotes') {
                        $key = 'invoices';
                        $data['entityType'] = ENTITY_QUOTE;
                        $data['invoices'] = $data['quotes'];
                    }
                    $sheet->loadView("export.{$key}", $data);
                });
            }
        })->download('xls');
    }

    /**
     * @param $request
     *
     * @return array
     */
    private function getData($request)
    {
        $company = Auth::user()->company;

        $data = [
            'company' => $company,
            'title' => 'Invoice Ninja v' . NINJA_VERSION . ' - ' . $company->formatDateTime($company->getDateTime()),
            'multiUser' => $company->users->count() > 1
        ];

        if ($request->input('include') === 'all' || $request->input('relations')) {
            $data['relations'] = Relation::scope()
                ->with('user', 'contacts', 'country')
                ->withArchived()
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('contacts')) {
            $data['contacts'] = Contact::scope()
                ->with('user', 'relation.contacts')
                ->withTrashed()
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('credits')) {
            $data['credits'] = Credit::scope()
                ->with('user', 'relation.contacts')
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('tasks')) {
            $data['tasks'] = Task::scope()
                ->with('user', 'relation.contacts')
                ->withArchived()
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('invoices')) {
            $data['invoices'] = Invoice::scope()
                ->invoiceType(INVOICE_TYPE_STANDARD)
                ->with('user', 'relation.contacts', 'invoice_status')
                ->withArchived()
                ->where('is_recurring', '=', false)
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('quotes')) {
            $data['quotes'] = Invoice::scope()
                ->invoiceType(INVOICE_TYPE_QUOTE)
                ->with('user', 'relation.contacts', 'invoice_status')
                ->withArchived()
                ->where('is_recurring', '=', false)
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('recurring')) {
            $data['recurringInvoices'] = Invoice::scope()
                ->invoiceType(INVOICE_TYPE_STANDARD)
                ->with('user', 'relation.contacts', 'invoice_status', 'frequency')
                ->withArchived()
                ->where('is_recurring', '=', true)
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('payments')) {
            $data['payments'] = Payment::scope()
                ->withArchived()
                ->with('user', 'relation.contacts', 'payment_type', 'invoice', 'acc_gateway.gateway')
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('expenses')) {
            $data['expenses'] = Expense::scope()
                ->with('user', 'vendor.vendor_contacts', 'relation.contacts', 'expense_category')
                ->withArchived()
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('products')) {
            $data['products'] = Product::scope()
                ->withArchived()
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('vendors')) {
            $data['vendors'] = Vendor::scope()
                ->with('user', 'vendor_contacts', 'country')
                ->withArchived()
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('vendor_contacts')) {
            $data['vendor_contacts'] = VendorContact::scope()
                ->with('user', 'vendor.vendor_contacts')
                ->withTrashed()
                ->get();
        }

        return $data;
    }
}
