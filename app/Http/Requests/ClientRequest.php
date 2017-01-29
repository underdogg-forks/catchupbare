<?php
namespace App\Http\Requests;

use Input;
use Utils;
use Illuminate\Http\Request;
use App\Libraries\HistoryUtils;
use App\Models\Client;


class ClientRequest extends EntityRequest {

    protected $entityType = ENTITY_CLIENT;

    public function entity()
    {
        /*if(is_null($client))
        {
            die("hello world");
        }*/


        $the_client_id = Input::get('client_id');
        dd($the_client_id);
        //$client = Client::scope($the_client_id)->withTrashed()->firstOrFail();
        
        // eager load the contacts
        if ($client && ! $client->relationLoaded('contacts')) {
            $client->load('contacts');
        }
         
        return $client;
    }
}