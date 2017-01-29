<?php namespace App\Ninja\Repositories;

use DB;
use Crypt;
use App\Models\BankAccount;
use App\Models\BankSubaccount;

class BankCompanyRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\BankAccount';
    }

    public function find($companyId)
    {
        return DB::table('bank_accs')
                    ->join('banks', 'banks.id', '=', 'bank_accs.bank_id')
                    ->where('bank_accs.deleted_at', '=', null)
                    ->where('bank_accs.company_id', '=', $companyId)
                    ->select(
                        'bank_accs.public_id',
                        'banks.name as bank_name',
                        'bank_accs.deleted_at',
                        'banks.bank_library_id'
                    );
    }

    public function save($input)
    {
        $bankAccount = BankAccount::createNew();
        $bankAccount->bank_id = $input['bank_id'];
        $bankAccount->username = Crypt::encrypt(trim($input['bank_username']));

        $company = \Auth::user()->company;
        $company->bank_accs()->save($bankAccount);

        foreach ($input['bank_accs'] as $data) {
            if ( ! isset($data['include']) || ! filter_var($data['include'], FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $subacc = BankSubaccount::createNew();
            $subacc->acc_name = trim($data['acc_name']);
            $subacc->acc_number = trim($data['hashed_acc_number']);
            $bankAccount->bank_subaccs()->save($subacc);
        }

        return $bankAccount;
    }
}
