<?php namespace App\Events;

use App\Models\Relation;
use Illuminate\Queue\SerializesModels;

/**
 * Class ClientWasArchived
 */
class ClientWasArchived extends Event
{
    use SerializesModels;

    /**
     * @var Relation
     */
    public $client;

    /**
     * Create a new event instance.
     *
     * @param Relation $client
     */
    public function __construct(Relation $client)
    {
        $this->relation = $client;
    }
}
