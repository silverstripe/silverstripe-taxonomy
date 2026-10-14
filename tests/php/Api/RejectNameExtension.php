<?php

namespace SilverStripe\Taxonomy\Tests\Api;

use SilverStripe\Core\Extension;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Fails model validation, which the form does not know about
 *
 * @extends Extension<DataObject>
 */
class RejectNameExtension extends Extension implements TestOnly
{
    public const REJECTED_NAME = 'Rejected';

    protected function updateValidate(ValidationResult $result): void
    {
        if ($this->getOwner()->Name === RejectNameExtension::REJECTED_NAME) {
            $result->addFieldError('Name', 'This name is not allowed.');
        }
    }
}
