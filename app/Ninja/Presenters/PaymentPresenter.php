<?php namespace App\Ninja\Presenters;

use Carbon;
use Utils;

class PaymentPresenter extends EntityPresenter {

    public function amount()
    {
        return Utils::formatMoney($this->entity->amount, $this->entity->client->currency_id);
    }

    public function client()
    {
        return $this->entity->client ? $this->entity->client->getDisplayName() : '';
    }

    public function payment_date()
    {
        return Utils::fromSqlDate($this->entity->payment_date);
    }

    public function month()
    {
        return Carbon::parse($this->entity->payment_date)->format('Y m');
    }

    public function method()
    {
        if ($this->entity->acc_gateway) {
            return $this->entity->acc_gateway->gateway->name;
        } elseif ($this->entity->payment_type) {
            return $this->entity->payment_type->name;
        }
    }
}
