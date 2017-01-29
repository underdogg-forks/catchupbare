<?php namespace App\Ninja\Datatables;

use Utils;
use URL;
use Auth;

class ProjectDatatable extends EntityDatatable
{
    public $entityType = ENTITY_PROJECT;
    public $sortCol = 1;

    public function columns()
    {
        return [
            [
                'project',
                function ($model)
                {
                    if ( ! Auth::user()->can('editByOwner', [ENTITY_PROJECT, $model->user_id])) {
                        return $model->project;
                    }

                    return link_to("projects/{$model->public_id}/edit", $model->project)->toHtml();
                }
            ],
            [
                'relation_name',
                function ($model)
                {
                    if ($model->relation_public_id) {
                        if(!Auth::user()->can('viewByOwner', [ENTITY_RELATION, $model->client_user_id])){
                            return Utils::getRelationDisplayName($model);
                        }

                        return link_to("relations/{$model->relation_public_id}", Utils::getRelationDisplayName($model))->toHtml();
                    } else {
                        return '';
                    }
                }
            ]
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_project'),
                function ($model) {
                    return URL::to("projects/{$model->public_id}/edit") ;
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_PROJECT, $model->user_id]);
                }
            ],
        ];
    }

}
