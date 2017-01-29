<?php
namespace App\Http\Requests;

use Input;
use Utils;
use Illuminate\Http\Request;
use App\Libraries\HistoryUtils;
use Modules\Relations\Models\Relation;


class RelationRequest extends EntityRequest {

    protected $entityType = ENTITY_RELATION;

    public function entity()
    {
        /*if(is_null($relation))
        {
            die("hello world");
        }*/


        $the_client_id = Input::get('relation_id');
        dd($the_client_id);
        //$relation = Relation::scope($the_client_id)->withTrashed()->firstOrFail();
        
        // eager load the contacts
        if ($relation && ! $relation->relationLoaded('contacts')) {
            $relation->load('contacts');
        }
         
        return $relation;
    }
}