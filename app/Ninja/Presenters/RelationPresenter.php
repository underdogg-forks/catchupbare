<?php namespace App\Ninja\Presenters;


class ClientPresenter extends EntityPresenter {

    public function country()
    {
        return $this->entity->country ? $this->entity->country->name : '';
    }

    public function balance()
    {
        $client = $this->entity;
        $company = $client->company;

        return $company->formatMoney($client->balance, $client);
    }

    public function paid_to_date()
    {
        $relation = $this->entity;
        $company = $relation->company;

        return $company->formatMoney($relation->paid_to_date, $relation);
    }
}
