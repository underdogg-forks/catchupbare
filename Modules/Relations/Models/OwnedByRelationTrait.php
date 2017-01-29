<?php
namespace Modules\Relations\Models;

/**
 * Class OwnedByRelationTrait
 */
trait OwnedByRelationTrait
{
    /**
     * @return bool
     */
    public function isRelationTrashed()
    {
        if (!$this->relation) {
            return false;
        }

        return $this->relation->trashed();
    }
}