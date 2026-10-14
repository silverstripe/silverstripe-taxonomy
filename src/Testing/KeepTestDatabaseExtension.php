<?php

namespace SilverStripe\Taxonomy\Testing;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\Connect\TempDatabase;
use SilverStripe\TestSession\TestSessionEnvironment;

/**
 * @extends Extension<TestSessionEnvironment>
 */
class KeepTestDatabaseExtension extends Extension
{
    /**
     * Request parameter that only the Playwright set-up in tests/playwright/session.js sends
     */
    public const KEEP_DATABASE_PARAMETER = 'taxonomyKeepTestDatabase';

    // The class must stay in src/, because the web request that starts the session ignores classes in tests/
    protected function onBeforeStartTestSession(array $state): void
    {
        if (($state[KeepTestDatabaseExtension::KEEP_DATABASE_PARAMETER] ?? '') !== '1') {
            return;
        }
        Config::modify()->set(TempDatabase::class, 'teardown_on_exit', false);
    }
}
