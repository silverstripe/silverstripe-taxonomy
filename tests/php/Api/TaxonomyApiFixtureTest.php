<?php

namespace SilverStripe\Taxonomy\Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;

// The composer test namespace maps to tests/, so the autoloader cannot find this base class
require_once __DIR__ . '/TaxonomyApiTestCase.php';

/**
 * Records the responses that the Jest tests serve through MSW. Run with TAXONOMY_RECORD_FIXTURES=1 to rewrite
 * client/src/tests/fixtures. Without it, the test fails when a response no longer matches its fixture.
 */
class TaxonomyApiFixtureTest extends TaxonomyApiTestCase
{
    public static function provideFixture(): array
    {
        $admin = ['ADMIN'];
        $viewOnly = [];
        // Name, method, path, body, codes (null for anonymous) and expected status
        $fixtures = [
            ['index', 'GET', '', null, $admin, 200],
            ['types-list', 'GET', 'types', null, $admin, 200],
            ['type', 'GET', 'types/{type}', null, $admin, 200],
            ['error-404-type', 'GET', 'types/999999', null, $admin, 404],
            ['terms-list', 'GET', 'terms', null, $admin, 200],
            ['terms-list-page-1', 'GET', 'terms?limit=1', null, $admin, 200],
            ['terms-list-page-2', 'GET', 'terms?limit=1&offset=1', null, $admin, 200],
            ['terms-list-search', 'GET', 'terms?q=Pla', null, $admin, 200],
            ['terms-list-empty', 'GET', 'terms?q=zzz', null, $admin, 200],
            ['terms-list-view-only', 'GET', 'terms', null, $viewOnly, 200],
            ['term', 'GET', 'terms/{plant}', null, $admin, 200],
            ['term-view-only', 'GET', 'terms/{plant}', null, $viewOnly, 200],
            ['term-child', 'GET', 'terms/{carrot}', null, $admin, 200],
            ['term-children', 'GET', 'terms/{plant}/children', null, $admin, 200],
            ['term-children-empty', 'GET', 'terms/{animal}/children', null, $admin, 200],
            ['error-404', 'GET', 'terms/999999', null, $admin, 404],
            ['error-403', 'GET', 'terms', null, ['CMS_ACCESS_CMSMain'], 403],
            ['term-schema', 'GET', 'terms/{plant}/schema', null, $admin, 200],
            ['term-schema-view-only', 'GET', 'terms/{plant}/schema', null, $viewOnly, 200],
            ['term-child-schema', 'GET', 'terms/{carrot}/schema', null, $admin, 200],
            ['term-new-schema', 'GET', 'terms/new/schema', null, $admin, 200],
            ['term-new-child-schema', 'GET', 'terms/new/schema?parentID={plant}', null, $admin, 200],
            ['type-schema', 'GET', 'types/{type}/schema', null, $admin, 200],
            ['type-new-schema', 'GET', 'types/new/schema', null, $admin, 200],
            ['type-created', 'POST', 'types', ['Name' => 'Geology'], $admin, 201],
            ['type-updated', 'PATCH', 'types/{type}', ['Name' => 'Science'], $admin, 200],
            ['term-created', 'POST', 'terms', ['Name' => 'Fungus'], $admin, 201],
            ['term-child-created', 'POST', 'terms/{plant}/children', ['Name' => 'Fern'], $admin, 201],
            ['term-invalid', 'POST', 'terms', ['Name' => ''], $admin, 422],
            ['term-unknown-field', 'POST', 'terms', ['Name' => 'X', 'ParentID' => 1], $admin, 422],
            ['term-updated', 'PATCH', 'terms/{plant}', ['Name' => 'Flora'], $admin, 200],
            ['error-403-update', 'PATCH', 'terms/{plant}', ['Name' => 'Flora'], $viewOnly, 403],
        ];
        $rows = [];
        foreach ($fixtures as [$name, $method, $path, $body, $codes, $expectedStatus]) {
            $rows[$name] = compact('name', 'method', 'path', 'body', 'codes', 'expectedStatus');
        }
        return $rows;
    }

    #[DataProvider('provideFixture')]
    public function testFixture(
        string $name,
        string $method,
        string $path,
        ?array $body,
        array $codes,
        int $expectedStatus
    ): void {
        $path = strtr($path, [
            '{type}' => (string) $this->type->ID,
            '{plant}' => (string) $this->plant->ID,
            '{animal}' => (string) $this->animal->ID,
            '{carrot}' => (string) $this->carrot->ID,
        ]);
        if ($codes === ['CMS_ACCESS_CMSMain']) {
            $this->logInWithPermission($codes);
        } else {
            $this->logInAsTaxonomyUser($codes);
        }
        if ($method === 'GET') {
            // The header is what FormBuilderLoader sends
            $headers = str_contains($path, 'schema') ? ['X-Formschema-Request' => 'auto,schema,state,errors'] : [];
            $response = $this->getJson($path, $expectedStatus, $headers);
        } else {
            $response = $this->sendJson($method, $path, $body, $expectedStatus) ?? [];
        }
        $this->assertMatchesFixture($name, $expectedStatus, $response);
    }
}
