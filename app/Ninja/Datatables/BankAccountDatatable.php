<?php namespace App\Ninja\Datatables;

use URL;

class BankAccountDatatable extends EntityDatatable
{
    public $entityType = ENTITY_BANK_COMPANY;

    public function columns()
    {
        return [
            [
                'bank_name',
                function ($model) {
                    return link_to("bank_accs/{$model->public_id}/edit", $model->bank_name)->toHtml();
                },
            ],
            [
                'bank_library_id',
                function ($model) {
                    return 'OFX';
                }
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                uctrans('texts.edit_bank_acc'),
                function ($model) {
                    return URL::to("bank_accs/{$model->public_id}/edit");
                },
            ]
        ];
    }


}
