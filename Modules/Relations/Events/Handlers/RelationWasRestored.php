<?php
namespace Modules\Relations\Events;

use Modules\Relations\Models\Relation;
use Illuminate\Queue\SerializesModels;
use App\Events\Event;

/**
 * Class ClientWasRestored
 */
class ClientWasRestored extends Event
{
    use SerializesModels;

    /**
     * @var Relation
     */
    public $relation;

    /**
     * Create a new event instance.
     *
     * @param Relation $relation
     */
    public function __construct(Relation $relation)
    {
        $this->relation = $relation;
    }
}
