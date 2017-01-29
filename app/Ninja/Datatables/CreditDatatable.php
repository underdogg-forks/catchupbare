<?php namespace App\Ninja\Datatables;

use Utils;
use URL;
use Auth;

class CreditDatatable extends EntityDatatable
{
    public $entityType = ENTITY_CREDIT;
    public $sortCol = 4;

    public function columns()
    {
        return [
            [
                'relation_name',
                function ($model) {
                    if(!Auth::user()->can('viewByOwner', [ENTITY_RELATION, $model->client_user_id])){
                        return Utils::getRelationDisplayName($model);
                    }

                    return $model->relation_public_id ? link_to("relations/{$model->relation_public_id}", Utils::getRelationDisplayName($model))->toHtml() : '';
                },
                ! $this->hideClient
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id) . '<span '.Utils::getEntityRowClass($model).'/>';
                }
            ],
            [
                'balance',
                function ($model) {
                    return Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
                }
            ],
            [
                'credit_date',
                function ($model) {
                    if ( ! Auth::user()->can('viewByOwner', [ENTITY_CREDIT, $model->user_id])){
                        return Utils::fromSqlDate($model->credit_date);
                    }

                    return link_to("credits/{$model->public_id}/edit", Utils::fromSqlDate($model->credit_date))->toHtml();
                }
            ],
            [
                'private_notes',
                function ($model) {
                    return $model->private_notes;
                }
            ]
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_credit'),
                function ($model) {
                    return URL::to("credits/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_CREDIT, $model->user_id]);
                }
            ],
            [
                trans('texts.apply_credit'),
                function ($model) {
                    return URL::to("payments/create/{$model->relation_public_id}") . '?paymentTypeId=1';
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_PAYMENT);
                }
            ]
        ];
    }
}
