<?php

namespace SilverStripe\Taxonomy\Tests\Api;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\DataObject;

/**
 * @extends Extension<DataObject>
 */
class AddGridFieldExtension extends Extension implements TestOnly
{
    protected function updateCMSFields(FieldList $fields): void
    {
        $fields->push(GridField::create('Terms'));
    }
}
