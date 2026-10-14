<?php

namespace SilverStripe\Taxonomy\Tests\Api;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * @extends Extension<DataObject>
 */
class HideRecordExtension extends Extension implements TestOnly
{
    public const HIDDEN_NAME = 'Hidden';

    public const LOCKED_NAME = 'Locked';

    public static bool $hideEverything = false;

    public static string $hiddenClass = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public static array $createContexts = [];

    protected function canView($member = null): ?bool
    {
        $owner = $this->getOwner();
        $isHidden = HideRecordExtension::$hideEverything
            || $owner::class === HideRecordExtension::$hiddenClass
            || $owner->Name === HideRecordExtension::HIDDEN_NAME;
        if ($isHidden) {
            return false;
        }
        return null;
    }

    protected function canEdit($member = null): ?bool
    {
        if ($this->getOwner()->Name === HideRecordExtension::LOCKED_NAME) {
            return false;
        }
        return null;
    }

    protected function canCreate($member = null, array $context = []): ?bool
    {
        HideRecordExtension::$createContexts[] = $context;
        return null;
    }
}
