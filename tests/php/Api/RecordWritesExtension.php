<?php

namespace SilverStripe\Taxonomy\Tests\Api;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Records the ID of every record that a write saves
 *
 * @extends Extension<DataObject>
 */
class RecordWritesExtension extends Extension implements TestOnly
{
    /**
     * @var array<int, int>
     */
    public static array $writtenIDs = [];

    protected function onAfterWrite(): void
    {
        RecordWritesExtension::$writtenIDs[] = $this->getOwner()->ID;
    }
}
