<?php

namespace SilverStripe\Taxonomy\Tests\Api;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;

/**
 * @extends Extension<DataObject>
 */
class ReadonlyFieldsExtension extends Extension implements TestOnly
{
    protected function updateCMSFields(FieldList $fields): void
    {
        $fields->push(ReadonlyField::create('Note'));
        $fields->push(TextField::create('Locked')->setDisabled(true));
    }
}
