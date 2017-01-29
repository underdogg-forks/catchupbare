<?php namespace App\Ninja\Repositories;

use DB;

class AccountGatewayRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\AccountGateway';
    }

    public function find($companyId)
    {
        $query = DB::table('acc_gateways')
                    ->join('gateways', 'gateways.id', '=', 'acc_gateways.gateway_id')
                    ->where('acc_gateways.company_id', '=', $companyId)
                    ->whereNull('acc_gateways.deleted_at');

        return $query->select('acc_gateways.id', 'acc_gateways.public_id', 'gateways.name', 'acc_gateways.deleted_at', 'acc_gateways.gateway_id');
    }
}
