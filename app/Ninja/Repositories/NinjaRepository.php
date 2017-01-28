<?php namespace App\Ninja\Repositories;

use App\Models\Account;

class NinjaRepository
{
    public function updatePlanDetails($clientPublicId, $data)
    {
        $account = Account::whereId($clientPublicId)->first();

        if (!$account) {
            return;
        }

        $corporation = $account->corporation;
        $corporation->plan = !empty($data['plan']) && $data['plan'] != PLAN_FREE?$data['plan']:null;
        $corporation->plan_term = !empty($data['plan_term'])?$data['plan_term']:null;
        $corporation->plan_paid = !empty($data['plan_paid'])?$data['plan_paid']:null;
        $corporation->plan_started = !empty($data['plan_started'])?$data['plan_started']:null;
        $corporation->plan_expires = !empty($data['plan_expires'])?$data['plan_expires']:null;
                
        $corporation->save();
    }
}
