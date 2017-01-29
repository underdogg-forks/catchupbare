<?php namespace App\Services;

use stdClass;
use Utils;
use Hash;
use App\Models\BankSubaccount;
use App\Models\Vendor;
use App\Models\Expense;
use App\Ninja\Repositories\BankCompanyRepository;
use App\Ninja\Repositories\ExpenseRepository;
use App\Ninja\Repositories\VendorRepository;
use App\Ninja\Datatables\BankAccountDatatable;
use App\Libraries\Finance;
use App\Libraries\Login;

/**
 * Class BankAccountService
 */
class BankAccountService extends BaseService
{
    /**
     * @var BankCompanyRepository
     */
    protected $bankAccountRepo;

    /**
     * @var ExpenseRepository
     */
    protected $expenseRepo;

    /**
     * @var VendorRepository
     */
    protected $vendorRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * BankAccountService constructor.
     *
     * @param BankCompanyRepository $bankAccountRepo
     * @param ExpenseRepository $expenseRepo
     * @param VendorRepository $vendorRepo
     * @param DatatableService $datatableService
     */
    public function __construct(BankCompanyRepository $bankAccountRepo, ExpenseRepository $expenseRepo, VendorRepository $vendorRepo, DatatableService $datatableService)
    {
        $this->bankAccountRepo = $bankAccountRepo;
        $this->vendorRepo = $vendorRepo;
        $this->expenseRepo = $expenseRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return BankCompanyRepository
     */
    protected function getRepo()
    {
        return $this->bankAccountRepo;
    }

    /**
     * @param null $bankId
     * @return array
     */
    private function getExpenses($bankId = null)
    {
        $expenses = Expense::scope()
                        ->bankId($bankId)
                        ->where('transaction_id', '!=', '')
                        ->withTrashed()
                        ->get(['transaction_id'])
                        ->toArray();
        $expenses = array_flip(array_map(function ($val) {
            return $val['transaction_id'];
        }, $expenses));

        return $expenses;
    }

    /**
     * @param $bankId
     * @param $username
     * @param $password
     * @param bool $includeTransactions
     * @return array|bool
     */
    public function loadBankAccounts($bankId, $username, $password, $includeTransactions = true)
    {
        if (! $bankId || ! $username || ! $password) {
            return false;
        }

        $expenses = $this->getExpenses();
        $vendorMap = $this->createVendorMap();
        $bankAccounts = BankSubaccount::scope()
                            ->whereHas('bank_acc', function ($query) use ($bankId) {
                                $query->where('bank_id', '=', $bankId);
                            })
                            ->get();
        $bank = Utils::getFromCache($bankId, 'banks');
        $data = [];

        // load OFX trnansactions
        try {
            $finance = new Finance();
            $finance->banks[$bankId] = $bank->getOFXBank($finance);
            $finance->banks[$bankId]->logins[] = new Login($finance->banks[$bankId], $username, $password);

            foreach ($finance->banks as $bank) {
                foreach ($bank->logins as $login) {
                    $login->setup();
                    foreach ($login->companies as $company) {
                        $company->setup($includeTransactions);
                        if ($company = $this->parseBankAccount($company, $bankAccounts, $expenses, $includeTransactions, $vendorMap)) {
                            $data[] = $company;
                        }
                    }
                }
            }

            return $data;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param $company
     * @param $bankAccounts
     * @param $expenses
     * @param $includeTransactions
     * @param $vendorMap
     * @return bool|stdClass
     */
    private function parseBankAccount($company, $bankAccounts, $expenses, $includeTransactions, $vendorMap)
    {
        $obj = new stdClass();
        $obj->acc_name = '';

        // look up bank company name
        foreach ($bankAccounts as $bankAccount) {
            if (Hash::check($company->id, $bankAccount->acc_number)) {
                $obj->acc_name = $bankAccount->acc_name;
            }
        }

        // if we can't find a match skip the company
        if (count($bankAccounts) && ! $obj->acc_name) {
            return false;
        }

        $obj->masked_acc_number = Utils::maskAccountNumber($company->id);
        $obj->hashed_acc_number = bcrypt($company->id);
        $obj->type = $company->type;
        $obj->balance = Utils::formatMoney($company->ledgerBalance, CURRENCY_DOLLAR);

        if ($includeTransactions) {
            $obj = $this->parseTransactions($obj, $company->response, $expenses, $vendorMap);
        }

        return $obj;
    }

    /**
     * @param $company
     * @param $data
     * @param $expenses
     * @param $vendorMap
     * @return mixed
     */
    private function parseTransactions($company, $data, $expenses, $vendorMap)
    {
        $ofxParser = new \OfxParser\Parser();
        $ofx = $ofxParser->loadFromString($data);

        $company->start_date = $ofx->BankAccount->Statement->startDate;
        $company->end_date = $ofx->BankAccount->Statement->endDate;
        $company->transactions = [];

        foreach ($ofx->BankAccount->Statement->transactions as $transaction) {
            // ensure transactions aren't imported as expenses twice
            if (isset($expenses[$transaction->uniqueId])) {
                continue;
            }
            if ($transaction->amount >= 0) {
                continue;
            }

            // if vendor has already been imported use current name
            $vendorName = trim(substr($transaction->name, 0, 20));
            $key = strtolower($vendorName);
            $vendor = isset($vendorMap[$key]) ? $vendorMap[$key] : null;

            $transaction->vendor = $vendor ? $vendor->name : $this->prepareValue($vendorName);
            $transaction->info = $this->prepareValue(substr($transaction->name, 20));
            $transaction->memo = $this->prepareValue($transaction->memo);
            $transaction->date = \Auth::user()->company->formatDate($transaction->date);
            $transaction->amount *= -1;
            $company->transactions[] = $transaction;
        }

        return $company;
    }

    /**
     * @param $value
     * @return string
     */
    private function prepareValue($value)
    {
        return ucwords(strtolower(trim($value)));
    }

    /**
     * @param $data
     * @return mixed
     */
    public function parseOFX($data)
    {
        $company = new stdClass;
        $expenses = $this->getExpenses();
        $vendorMap = $this->createVendorMap();

        return $this->parseTransactions($company, $data, $expenses, $vendorMap);
    }

    /**
     * @return array
     */
    private function createVendorMap()
    {
        $vendorMap = [];
        $vendors = Vendor::scope()
                        ->withTrashed()
                        ->get(['id', 'name', 'transaction_name']);
        foreach ($vendors as $vendor) {
            $vendorMap[strtolower($vendor->name)] = $vendor;
            $vendorMap[strtolower($vendor->transaction_name)] = $vendor;
        }

        return $vendorMap;
    }

    public function importExpenses($bankId = 0, $input)
    {
        $vendorMap = $this->createVendorMap();
        $countVendors = 0;
        $countExpenses = 0;

        foreach ($input as $transaction) {
            $vendorName = $transaction['vendor'];
            $key = strtolower($vendorName);
            $info = $transaction['info'];

            // find vendor otherwise create it
            if (isset($vendorMap[$key])) {
                $vendor = $vendorMap[$key];
            } else {
                $field = $this->determineInfoField($info);
                $vendor = $this->vendorRepo->save([
                    $field => $info,
                    'name' => $vendorName,
                    'transaction_name' => $transaction['vendor_orig'],
                    'vendor_contact' => [],
                ]);
                $vendorMap[$key] = $vendor;
                $vendorMap[$transaction['vendor_orig']] = $vendor;
                $countVendors++;
            }

            // create the expense record
            $this->expenseRepo->save([
                'vendor_id' => $vendor->id,
                'amount' => $transaction['amount'],
                'public_notes' => $transaction['memo'],
                'expense_date' => $transaction['date'],
                'transaction_id' => $transaction['id'],
                'bank_id' => $bankId,
                'should_be_invoiced' => true,
            ]);
            $countExpenses++;
        }

        return trans('texts.imported_expenses', [
            'count_vendors' => $countVendors,
            'count_expenses' => $countExpenses
        ]);
    }

    private function determineInfoField($value)
    {
        if (preg_match("/^[0-9\-\(\)\.]+$/", $value)) {
            return 'work_phone';
        } elseif (strpos($value, '.') !== false) {
            return 'private_notes';
        } else {
            return 'city';
        }
    }

    public function getDatatable($companyId)
    {
        $query = $this->bankAccountRepo->find($companyId);

        return $this->datatableService->createDatatable(new BankAccountDatatable(false), $query);
    }
}
