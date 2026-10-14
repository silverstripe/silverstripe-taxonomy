<?php

namespace SilverStripe\Taxonomy\Tests\Testing;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\Connect\TempDatabase;
use SilverStripe\Taxonomy\Testing\KeepTestDatabaseExtension;
use SilverStripe\TestSession\TestSessionEnvironment;

class KeepTestDatabaseExtensionTest extends SapphireTest
{
    public static function provideTeardownOnExit(): array
    {
        return [
            'no parameter' => [
                'state' => ['globalTestSession' => '1', 'createDatabase' => '1'],
                'expected' => true,
            ],
            'parameter is not 1' => [
                'state' => [KeepTestDatabaseExtension::KEEP_DATABASE_PARAMETER => '0'],
                'expected' => true,
            ],
            'Playwright parameter' => [
                'state' => [KeepTestDatabaseExtension::KEEP_DATABASE_PARAMETER => '1'],
                'expected' => false,
            ],
        ];
    }

    #[DataProvider('provideTeardownOnExit')]
    public function testTeardownOnExit(array $state, bool $expected): void
    {
        if (!class_exists(TestSessionEnvironment::class)) {
            $this->markTestSkipped('silverstripe/testsession is not installed');
        }
        Config::modify()->set(TempDatabase::class, 'teardown_on_exit', true);
        TestSessionEnvironment::create()->extend('onBeforeStartTestSession', $state);
        $this->assertSame($expected, Config::inst()->get(TempDatabase::class, 'teardown_on_exit'));
    }
}
