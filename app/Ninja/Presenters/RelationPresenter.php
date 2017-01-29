<?php namespace App\Ninja\Presenters;


class RelationPresenter extends EntityPresenter {

    public function country()
    {
        return $this->entity->country ? $this->entity->country->name : '';
    }

    public function balance()
    {
        $relation = $this->entity;
        $company = $relation->company;

        return $company->formatMoney($relation->balance, $relation);
    }

    public function paid_to_date()
    {
        $relation = $this->entity;
        $company = $relation->company;

        return $company->formatMoney($relation->paid_to_date, $relation);
    }
}
