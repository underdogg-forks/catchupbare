<?php namespace App\Services;

use App\Models\Product;
use stdClass;
use Excel;
use Cache;
use Exception;
use Auth;
use Utils;
use parsecsv;
use Session;
use League\Fractal\Manager;
use App\Ninja\Repositories\ContactRepository;
use Modules\Relations\Repositories\RelationRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\ProductRepository;
use App\Ninja\Repositories\ExpenseRepository;
use App\Ninja\Repositories\VendorRepository;
use App\Ninja\Repositories\ExpenseCategoryRepository;
use App\Ninja\Serializers\ArraySerializer;
use Modules\Relations\Models\Relation;
use App\Models\Invoice;
use App\Models\Vendor;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\EntityModel;
use App\Ninja\Import\BaseTransformer;

/**
 * Class ImportService
 */
class ImportService
{
    /**
     * @var
     */
    protected $transformer;

    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepo;

    /**
     * @var RelationRepository
     */
    protected $relationRepo;

    /**
     * @var ContactRepository
     */
    protected $contactRepo;

    /**
     * @var ProductRepository
     */
    protected $productRepo;

    /**
     * @var array
     */
    protected $processedRows = [];

    /**
     * @var array
     */
    private $maps = [];

    /**
     * @var array
     */
    public $results = [];

    /**
     * @var array
     */
    public static $entityTypes = [
        IMPORT_JSON,
        ENTITY_RELATION,
        ENTITY_CONTACT,
        ENTITY_INVOICE,
        ENTITY_PAYMENT,
        ENTITY_TASK,
        ENTITY_PRODUCT,
        ENTITY_EXPENSE,
    ];

    /**
     * @var array
     */
    public static $sources = [
        IMPORT_CSV,
        IMPORT_JSON,
        IMPORT_FRESHBOOKS,
        IMPORT_HIVEAGE,
        IMPORT_INVOICEABLE,
        IMPORT_NUTCACHE,
        IMPORT_RONIN,
        IMPORT_WAVE,
        IMPORT_ZOHO,
    ];

    /**
     * ImportService constructor.
     *
     * @param Manager $manager
     * @param RelationRepository $relationRepo
     * @param InvoiceRepository $invoiceRepo
     * @param PaymentRepository $paymentRepo
     * @param ContactRepository $contactRepo
     * @param ProductRepository $productRepo
     */
    public function __construct(
        Manager $manager,
        RelationRepository $relationRepo,
        InvoiceRepository $invoiceRepo,
        PaymentRepository $paymentRepo,
        ContactRepository $contactRepo,
        ProductRepository $productRepo,
        ExpenseRepository $expenseRepo,
        VendorRepository $vendorRepo,
        ExpenseCategoryRepository $expenseCategoryRepo
    )
    {
        $this->fractal = $manager;
        $this->fractal->setSerializer(new ArraySerializer());

        $this->clientRepo = $relationRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentRepo = $paymentRepo;
        $this->contactRepo = $contactRepo;
        $this->productRepo = $productRepo;
        $this->expenseRepo = $expenseRepo;
        $this->vendorRepo = $vendorRepo;
        $this->expenseCategoryRepo = $expenseCategoryRepo;
    }

    /**
     * @param $file
     * @return array
     * @throws Exception
     */
    public function importJSON($file)
    {
        $this->initMaps();

        $file = file_get_contents($file);
        $json = json_decode($file, true);
        $json = $this->removeIdFields($json);
        $transformer = new BaseTransformer($this->maps);

        $this->checkClientCount(count($json['relations']));

        foreach ($json['products'] as $jsonProduct) {
            if ($transformer->hasProduct($jsonProduct['product_key'])) {
                continue;
            }
            if (EntityModel::validate($jsonProduct, ENTITY_PRODUCT) === true) {
                $product = $this->productRepo->save($jsonProduct);
                $this->addProductToMaps($product);
                $this->addSuccess($product);
            } else {
                $this->addFailure(ENTITY_PRODUCT, $jsonProduct);
                continue;
            }
        }

        foreach ($json['relations'] as $jsonClient) {

            if (EntityModel::validate($jsonClient, ENTITY_RELATION) === true) {
                $relation = $this->clientRepo->save($jsonClient);
                $this->addClientToMaps($relation);
                $this->addSuccess($relation);
            } else {
                $this->addFailure(ENTITY_RELATION, $jsonClient);
                continue;
            }

            foreach ($jsonClient['invoices'] as $jsonInvoice) {
                $jsonInvoice['relation_id'] = $relation->id;
                if (EntityModel::validate($jsonInvoice, ENTITY_INVOICE) === true) {
                    $invoice = $this->invoiceRepo->save($jsonInvoice);
                    $this->addInvoiceToMaps($invoice);
                    $this->addSuccess($invoice);
                } else {
                    $this->addFailure(ENTITY_INVOICE, $jsonInvoice);
                    continue;
                }

                foreach ($jsonInvoice['payments'] as $jsonPayment) {
                    $jsonPayment['invoice_id'] = $invoice->public_id;
                    if (EntityModel::validate($jsonPayment, ENTITY_PAYMENT) === true) {
                        $jsonPayment['relation_id'] = $relation->id;
                        $jsonPayment['invoice_id'] = $invoice->id;
                        $payment = $this->paymentRepo->save($jsonPayment);
                        $this->addSuccess($payment);
                    } else {
                        $this->addFailure(ENTITY_PAYMENT, $jsonPayment);
                        continue;
                    }
                }
            }
        }

        return $this->results;
    }

    /**
     * @param $array
     * @return mixed
     */
    public function removeIdFields($array)
    {
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $array[$key] = $this->removeIdFields($val);
            } elseif ($key === 'id') {
                unset($array[$key]);
            }
        }
        return $array;
    }

    /**
     * @param $source
     * @param $files
     * @return array
     */
    public function importFiles($source, $files)
    {
        $results = [];
        $imported_files = null;
        $this->initMaps();

        foreach ($files as $entityType => $file) {
            $results[$entityType] = $this->execute($source, $entityType, $file);
        }

        return $results;
    }

    /**
     * @param $source
     * @param $entityType
     * @param $file
     * @return array
     */
    private function execute($source, $entityType, $file)
    {
        $results = [
            RESULT_SUCCESS => [],
            RESULT_FAILURE => [],
        ];

        // Convert the data
        $row_list = [];

        Excel::load($file, function ($reader) use ($source, $entityType, &$row_list, &$results) {
            $this->checkData($entityType, count($reader->all()));

            $reader->each(function ($row) use ($source, $entityType, &$row_list, &$results) {
                if ($this->isRowEmpty($row)) {
                    return;
                }

                $data_index = $this->transformRow($source, $entityType, $row);

                if ($data_index !== false) {
                    if ($data_index !== true) {
                        // Wasn't merged with another row
                        $row_list[] = ['row' => $row, 'data_index' => $data_index];
                    }
                } else {
                    $results[RESULT_FAILURE][] = $row;
                }
            });
        });

        // Save the data
        foreach ($row_list as $row_data) {
            $result = $this->saveData($source, $entityType, $row_data['row'], $row_data['data_index']);
            if ($result) {
                $results[RESULT_SUCCESS][] = $result;
            } else {
                $results[RESULT_FAILURE][] = $row_data['row'];
            }
        }

        return $results;
    }

    /**
     * @param $source
     * @param $entityType
     * @param $row
     * @return bool|mixed
     */
    private function transformRow($source, $entityType, $row)
    {
        $transformer = $this->getTransformer($source, $entityType, $this->maps);

        // Create expesnse category
        if ($entityType == ENTITY_EXPENSE) {
            if ( ! empty($row->expense_category)) {
                $categoryId = $transformer->getExpenseCategoryId($row->expense_category);
                if ( ! $categoryId) {
                    $category = $this->expenseCategoryRepo->save(['name' => $row->expense_category]);
                    $this->addExpenseCategoryToMaps($category);
                }
            }
            if ( ! empty($row->vendor) && ($vendorName = trim($row->vendor))) {
                if ( ! $transformer->getVendorId($vendorName)) {
                    $vendor = $this->vendorRepo->save(['name' => $vendorName, 'vendor_contact' => []]);
                    $this->addVendorToMaps($vendor);
                }
            }
        }

        $resource = $transformer->transform($row);

        if (!$resource) {
            return false;
        }

        $data = $this->fractal->createData($resource)->toArray();

        // if the invoice number is blank we'll assign it
        if ($entityType == ENTITY_INVOICE && !$data['invoice_number']) {
            $company = Auth::user()->company;
            $invoice = Invoice::createNew();
            $data['invoice_number'] = $company->getNextNumber($invoice);
        }

        if (EntityModel::validate($data, $entityType) !== true) {
            return false;
        }

        if ($entityType == ENTITY_INVOICE) {
            if (empty($this->processedRows[$data['invoice_number']])) {
                $this->processedRows[$data['invoice_number']] = $data;
            } else {
                // Merge invoice items
                $this->processedRows[$data['invoice_number']]['invoice_items'] = array_merge($this->processedRows[$data['invoice_number']]['invoice_items'], $data['invoice_items']);

                return true;
            }
        } else {
            $this->processedRows[] = $data;
        }

        end($this->processedRows);

        return key($this->processedRows);
    }

    /**
     * @param $source
     * @param $entityType
     * @param $row
     * @param $data_index
     * @return mixed
     */
    private function saveData($source, $entityType, $row, $data_index)
    {
        $data = $this->processedRows[$data_index];

        if ($entityType == ENTITY_INVOICE) {
            $data['is_public'] = true;
        }
        
        $entity = $this->{"{$entityType}Repo"}->save($data);

        // update the entity maps
        $mapFunction = 'add' . ucwords($entity->getEntityType()) . 'ToMaps';
        $this->$mapFunction($entity);

        // if the invoice is paid we'll also create a payment record
        if ($entityType === ENTITY_INVOICE && isset($data['paid']) && $data['paid'] > 0) {
            $this->createPayment($source, $row, $data['relation_id'], $entity->id);
        }

        return $entity;
    }

    /**
     * @param $entityType
     * @param $count
     * @throws Exception
     */
    private function checkData($entityType, $count)
    {
        if (Utils::isNinja() && $count > MAX_IMPORT_ROWS) {
            throw new Exception(trans('texts.limit_import_rows', ['count' => MAX_IMPORT_ROWS]));
        }

        if ($entityType === ENTITY_RELATION) {
            $this->checkClientCount($count);
        }
    }

    /**
     * @param $count
     * @throws Exception
     */
    private function checkClientCount($count)
    {
        $totalClients = $count + Relation::scope()->withTrashed()->count();
        if ($totalClients > Auth::user()->getMaxNumClients()) {
            throw new Exception(trans('texts.limit_clients', ['count' => Auth::user()->getMaxNumClients()]));
        }
    }

    /**
     * @param $source
     * @param $entityType
     * @return string
     */
    public static function getTransformerClassName($source, $entityType)
    {
        return 'App\\Ninja\\Import\\'.$source.'\\'.ucwords($entityType).'Transformer';
    }

    /**
     * @param $source
     * @param $entityType
     * @param $maps
     * @return mixed
     */
    public static function getTransformer($source, $entityType, $maps)
    {
        $className = self::getTransformerClassName($source, $entityType);

        return new $className($maps);
    }

    /**
     * @param $source
     * @param $data
     * @param $relationId
     * @param $invoiceId
     */
    private function createPayment($source, $data, $relationId, $invoiceId)
    {
        $paymentTransformer = $this->getTransformer($source, ENTITY_PAYMENT, $this->maps);

        $data->relation_id = $relationId;
        $data->invoice_id = $invoiceId;

        if ($resource = $paymentTransformer->transform($data)) {
            $data = $this->fractal->createData($resource)->toArray();
            $this->paymentRepo->save($data);
        }
    }


    /**
     * @param array $files
     * @return array
     * @throws Exception
     */
    public function mapCSV(array $files)
    {
        $data = [];

        foreach ($files as $entityType => $filename) {
            $class = 'App\\Models\\' . ucwords($entityType);
            $columns = $class::getImportColumns();
            $map = $class::getImportMap();

            // Lookup field translations
            foreach ($columns as $key => $value) {
                unset($columns[$key]);
                $columns[$value] = trans("texts.{$value}");
            }
            array_unshift($columns, ' ');

            $data[$entityType] = $this->mapFile($entityType, $filename, $columns, $map);

            if ($entityType === ENTITY_RELATION) {
                if (count($data[$entityType]['data']) + Relation::scope()->count() > Auth::user()->getMaxNumClients()) {
                    throw new Exception(trans('texts.limit_clients', ['count' => Auth::user()->getMaxNumClients()]));
                }
            }
        }

        return $data;
    }

    /**
     * @param $entityType
     * @param $filename
     * @param $columns
     * @param $map
     * @return array
     */
    public function mapFile($entityType, $filename, $columns, $map)
    {
        require_once app_path().'/Includes/parsecsv.lib.php';
        $csv = new parseCSV();
        $csv->heading = false;
        $csv->auto($filename);

        $headers = false;
        $hasHeaders = false;
        $mapped = [];

        if (count($csv->data) > 0) {
            $headers = $csv->data[0];

            // Remove Invoice Ninja headers
            if (count($headers) && count($csv->data) > 4) {
                $firstCell = $headers[0];
                if (strstr($firstCell, APP_NAME)) {
                    array_shift($csv->data); // Invoice Ninja...
                    array_shift($csv->data); // <blank line>
                    array_shift($csv->data); // Enitty Type Header
                }
                $headers = $csv->data[0];
            }

            foreach ($headers as $title) {
                if (strpos(strtolower($title), 'name') > 0) {
                    $hasHeaders = true;
                    break;
                }
            }

            for ($i = 0; $i<count($headers); $i++) {
                $title = strtolower($headers[$i]);
                $mapped[$i] = '';

                foreach ($map as $search => $column) {
                    if ($this->checkForMatch($title, $search)) {
                        $hasHeaders = true;
                        $mapped[$i] = $column;
                        break;
                    }
                }
            }
        }

        Session::put("{$entityType}-data", $csv->data);

        $data = [
            'entityType' => $entityType,
            'data' => $csv->data,
            'headers' => $headers,
            'hasHeaders' => $hasHeaders,
            'columns' => $columns,
            'mapped' => $mapped,
        ];

        return $data;
    }

    /**
     * @param $column
     * @param $pattern
     * @return bool
     */
    private function checkForMatch($column, $pattern)
    {
        if (strpos($column, 'sec') === 0) {
            return false;
        }

        if (strpos($pattern, '^')) {
            list($include, $exclude) = explode('^', $pattern);
            $includes = explode('|', $include);
            $excludes = explode('|', $exclude);
        } else {
            $includes = explode('|', $pattern);
            $excludes = [];
        }

        foreach ($includes as $string) {
            if (strpos($column, $string) !== false) {
                $excluded = false;
                foreach ($excludes as $exclude) {
                    if (strpos($column, $exclude) !== false) {
                        $excluded = true;
                        break;
                    }
                }
                if (!$excluded) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array $maps
     * @param $headers
     * @return array
     */
    public function importCSV(array $maps, $headers)
    {
        $results = [];

        foreach ($maps as $entityType => $map) {
            $results[$entityType] = $this->executeCSV($entityType, $map, $headers[$entityType]);
        }

        return $results;
    }

    /**
     * @param $entityType
     * @param $map
     * @param $hasHeaders
     * @return array
     */
    private function executeCSV($entityType, $map, $hasHeaders)
    {
        $results = [
            RESULT_SUCCESS => [],
            RESULT_FAILURE => [],
        ];
        $source = IMPORT_CSV;

        $data = Session::get("{$entityType}-data");
        $this->checkData($entityType, count($data));
        $this->initMaps();

        // Convert the data
        $row_list = [];
        foreach ($data as $row) {
            if ($hasHeaders) {
                $hasHeaders = false;
                continue;
            }

            $row = $this->convertToObject($entityType, $row, $map);
            if ($this->isRowEmpty($row)) {
                continue;
            }
            $data_index = $this->transformRow($source, $entityType, $row);

            if ($data_index !== false) {
                if ($data_index !== true) {
                    // Wasn't merged with another row
                    $row_list[] = ['row' => $row, 'data_index' => $data_index];
                }
            } else {
                $results[RESULT_FAILURE][] = $row;
            }
        }

        // Save the data
        foreach ($row_list as $row_data) {
            $result = $this->saveData($source, $entityType, $row_data['row'], $row_data['data_index']);

            if ($result) {
                $results[RESULT_SUCCESS][] = $result;
            } else {
                $results[RESULT_FAILURE][] = $row;
            }
        }

        Session::forget("{$entityType}-data");

        return $results;
    }

    /**
     * @param $entityType
     * @param $data
     * @param $map
     * @return stdClass
     */
    private function convertToObject($entityType, $data, $map)
    {
        $obj = new stdClass();
        $class = 'App\\Models\\' . ucwords($entityType);
        $columns = $class::getImportColumns();

        foreach ($columns as $column) {
            $obj->$column = false;
        }

        foreach ($map as $index => $field) {
            if (! $field) {
                continue;
            }

            if (isset($obj->$field) && $obj->$field) {
                continue;
            }

            $obj->$field = $data[$index];
        }

        return $obj;
    }

    /**
     * @param $entity
     */
    private function addSuccess($entity)
    {
        $this->results[$entity->getEntityType()][RESULT_SUCCESS][] = $entity;
    }

    /**
     * @param $entityType
     * @param $data
     */
    private function addFailure($entityType, $data)
    {
        $this->results[$entityType][RESULT_FAILURE][] = $data;
    }

    private function init()
    {
        EntityModel::$notifySubscriptions = false;

        foreach ([ENTITY_RELATION, ENTITY_INVOICE, ENTITY_PAYMENT, ENTITY_QUOTE, ENTITY_PRODUCT] as $entityType) {
            $this->results[$entityType] = [
                RESULT_SUCCESS => [],
                RESULT_FAILURE => [],
            ];
        }
    }

    private function initMaps()
    {
        $this->init();

        $this->maps = [
            'relation' => [],
            'invoice' => [],
            'invoice_client' => [],
            'product' => [],
            'countries' => [],
            'countries2' => [],
            'currencies' => [],
            'client_ids' => [],
            'invoice_ids' => [],
            'vendors' => [],
            'expense_categories' => [],
        ];

        $relations = $this->clientRepo->all();
        foreach ($relations as $relation) {
            $this->addClientToMaps($relation);
        }

        $invoices = $this->invoiceRepo->all();
        foreach ($invoices as $invoice) {
            $this->addInvoiceToMaps($invoice);
        }

        $products = $this->productRepo->all();
        foreach ($products as $product) {
            $this->addProductToMaps($product);
        }

        $countries = Cache::get('countries');
        foreach ($countries as $country) {
            $this->maps['countries'][strtolower($country->name)] = $country->id;
            $this->maps['countries2'][strtolower($country->iso_3166_2)] = $country->id;
        }

        $currencies = Cache::get('currencies');
        foreach ($currencies as $currency) {
            $this->maps['currencies'][strtolower($currency->code)] = $currency->id;
        }

        $vendors = $this->vendorRepo->all();
        foreach ($vendors as $vendor) {
            $this->addVendorToMaps($vendor);
        }

        $expenseCaegories = $this->expenseCategoryRepo->all();
        foreach ($expenseCaegories as $category) {
            $this->addExpenseCategoryToMaps($category);
        }
    }

    /**
     * @param Invoice $invoice
     */
    private function addInvoiceToMaps(Invoice $invoice)
    {
        if ($number = strtolower(trim($invoice->invoice_number))) {
            $this->maps['invoice'][$number] = $invoice->id;
            $this->maps['invoice_client'][$number] = $invoice->relation_id;
            $this->maps['invoice_ids'][$invoice->public_id] = $invoice->id;
        }
    }

    /**
     * @param Relation $relation
     */
    private function addClientToMaps(Relation $relation)
    {
        if ($name = strtolower(trim($relation->name))) {
            $this->maps['relation'][$name] = $relation->id;
            $this->maps['client_ids'][$relation->public_id] = $relation->id;
        }
    }

    /**
     * @param Product $product
     */
    private function addProductToMaps(Product $product)
    {
        if ($key = strtolower(trim($product->product_key))) {
            $this->maps['product'][$key] = $product->id;
        }
    }

    private function addExpenseToMaps(Expense $expense)
    {
        // do nothing
    }

    private function addVendorToMaps(Vendor $vendor)
    {
        $this->maps['vendor'][strtolower($vendor->name)] = $vendor->id;
    }

    private function addExpenseCategoryToMaps(ExpenseCategory $category)
    {
        if ($name = strtolower($category->name)) {
            $this->maps['expense_category'][$name] = $category->id;
        }
    }

    private function isRowEmpty($row)
    {
        $isEmpty = true;

        foreach ($row as $key => $val) {
            if (trim($val)) {
                $isEmpty = false;
            }
        }

        return $isEmpty;
    }
}
