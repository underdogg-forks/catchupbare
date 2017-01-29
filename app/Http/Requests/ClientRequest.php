<?php
namespace App\Http\Requests;

use Input;
use Utils;
use App\Libraries\HistoryUtils;
use App\Models\Client;


class ClientRequest extends EntityRequest {

    protected $entityType = ENTITY_CLIENT;

    public function entity()
    {
        //$client = parent::entity();
        $the_client_id = Input::get('id');
        $client = Client::scope($the_client_id)->withTrashed()->firstOrFail();
        
        // eager load the contacts
        if ($client && ! $client->relationLoaded('contacts')) {
            $client->load('contacts');
        }
         
        return $client;
    }
}