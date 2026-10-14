<?php

namespace SilverStripe\Taxonomy\Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Taxonomy\TaxonomyTerm;
use SilverStripe\Taxonomy\TaxonomyType;

// The composer test namespace maps to tests/, so the autoloader cannot find this base class
require_once __DIR__ . '/TaxonomyApiTestCase.php';

class TaxonomyApiControllerTest extends TaxonomyApiTestCase
{
    public static function providePermissionMatrix(): array
    {
        // Expected status per endpoint, in this order
        $endpoints = [
            ['method' => 'GET', 'path' => 'terms', 'body' => null],
            ['method' => 'POST', 'path' => 'terms', 'body' => ['Name' => 'New']],
            ['method' => 'PATCH', 'path' => 'terms/{plantID}', 'body' => ['Name' => 'Changed']],
            ['method' => 'PATCH', 'path' => 'terms/{plantID}', 'body' => ['Sort' => 1]],
            ['method' => 'DELETE', 'path' => 'terms/{animalID}', 'body' => null],
            ['method' => 'POST', 'path' => 'types', 'body' => ['Name' => 'New']],
        ];
        $actors = [
            'anonymous' => ['codes' => null, 'statuses' => [401, 401, 401, 401, 401, 401]],
            'cms user without taxonomy access' => [
                'codes' => ['CMS_ACCESS_CMSMain', 'TAXONOMYTERM_CREATE', 'TAXONOMYTERM_EDIT'],
                'statuses' => [403, 403, 403, 403, 403, 403],
            ],
            'view only' => ['codes' => [], 'statuses' => [200, 403, 403, 403, 403, 403]],
            'create code' => ['codes' => ['TAXONOMYTERM_CREATE'], 'statuses' => [200, 201, 403, 403, 403, 403]],
            'edit code' => ['codes' => ['TAXONOMYTERM_EDIT'], 'statuses' => [200, 403, 200, 200, 403, 403]],
            'delete code' => ['codes' => ['TAXONOMYTERM_DELETE'], 'statuses' => [200, 403, 403, 403, 204, 403]],
            'admin' => ['codes' => ['ADMIN'], 'statuses' => [200, 201, 200, 200, 204, 201]],
        ];
        $rows = [];
        foreach ($actors as $actor => $definition) {
            foreach ($endpoints as $index => $endpoint) {
                $rows["$actor: {$endpoint['method']} {$endpoint['path']} " . json_encode($endpoint['body'])] = [
                    'codes' => $definition['codes'],
                    'method' => $endpoint['method'],
                    'path' => $endpoint['path'],
                    'body' => $endpoint['body'],
                    'expectedStatus' => $definition['statuses'][$index],
                ];
            }
        }
        return $rows;
    }

    #[DataProvider('providePermissionMatrix')]
    public function testPermissionMatrix(
        ?array $codes,
        string $method,
        string $path,
        mixed $body,
        int $expectedStatus
    ): void {
        if ($codes !== null && in_array('CMS_ACCESS_CMSMain', $codes, true)) {
            $this->logInWithPermission($codes);
        } elseif ($codes !== null) {
            $this->logInAsTaxonomyUser($codes);
        }
        $path = str_replace(['{plantID}', '{animalID}'], [$this->plant->ID, $this->animal->ID], $path);
        $response = $this->sendJson($method, $path, $body, $expectedStatus);
        if ($expectedStatus >= 400) {
            $this->assertSame($expectedStatus, $response['errors'][0]['code']);
        }
    }

    public static function provideErrorStatus(): array
    {
        return [
            '400 malformed body' => [
                'codes' => ['ADMIN'],
                'method' => 'POST',
                'path' => 'terms',
                'body' => '{"Name": ',
                'headers' => [],
                'expectedStatus' => 400,
            ],
            '401 not logged in' => [
                'codes' => null,
                'method' => 'GET',
                'path' => 'terms',
                'body' => null,
                'headers' => [],
                'expectedStatus' => 401,
            ],
            '403 not allowed' => [
                'codes' => [],
                'method' => 'POST',
                'path' => 'terms',
                'body' => ['Name' => 'New'],
                'headers' => [],
                'expectedStatus' => 403,
            ],
            '404 unknown record' => [
                'codes' => ['ADMIN'],
                'method' => 'GET',
                'path' => 'terms/999999',
                'body' => null,
                'headers' => [],
                'expectedStatus' => 404,
            ],
            '415 not JSON' => [
                'codes' => ['ADMIN'],
                'method' => 'POST',
                'path' => 'terms',
                'body' => ['Name' => 'New'],
                'headers' => ['Content-Type' => 'text/plain'],
                'expectedStatus' => 415,
            ],
            '422 unknown field' => [
                'codes' => ['ADMIN'],
                'method' => 'POST',
                'path' => 'terms',
                'body' => ['Name' => 'New', 'Unknown' => 1],
                'headers' => [],
                'expectedStatus' => 422,
            ],
        ];
    }

    #[DataProvider('provideErrorStatus')]
    public function testErrorStatus(
        ?array $codes,
        string $method,
        string $path,
        mixed $body,
        array $headers,
        int $expectedStatus
    ): void {
        if ($codes !== null) {
            $this->logInAsTaxonomyUser($codes);
        }
        $response = $this->sendJson($method, $path, $body, $expectedStatus, $headers);
        $this->assertSame('error', $response['status']);
        $this->assertSame('error', $response['errors'][0]['type']);
        $this->assertSame($expectedStatus, $response['errors'][0]['code']);
        $this->assertNotEmpty($response['errors'][0]['value']);
        $this->assertSame(0, TaxonomyTerm::get()->filter('Name', 'New')->count());
    }

    public function testIndex(): void
    {
        $this->logInAsTaxonomyUser(['TAXONOMYTERM_CREATE']);
        $body = $this->getJson('');
        $this->assertNotEmpty($body['securityID']);
        $this->assertSame(['terms', 'types'], array_keys($body['collections']));
        $this->assertStringEndsWith('admin/api/v1/taxonomy/terms', $body['collections']['terms']['link']);
        $this->assertSame(['view' => true, 'create' => true], $body['collections']['terms']['can']);
        $this->assertSame(['view' => true, 'create' => false], $body['collections']['types']['can']);
    }

    public function testListTermsSearchAndPaging(): void
    {
        $this->logInAsTaxonomyUser();
        $body = $this->getJson('terms');
        $this->assertSame(['Animal', 'Plant'], array_column($body['items'], 'Name'));
        $this->assertSame([1, 2], array_column($body['items'], 'Sort'));
        $this->assertSame([2, 50, 0], [$body['total'], $body['limit'], $body['offset']]);
        $this->assertSame(['view' => true, 'create' => false], $body['can']);
        $this->assertSame(['Plant'], array_column($this->getJson('terms?q=lan')['items'], 'Name'));
        $this->assertSame(0, $this->getJson('terms?q=Carrot')['total']);
        $this->getJson('terms?q[]=Plant', 400);
        $page = $this->getJson('terms?limit=1&offset=1');
        $this->assertSame(['Plant'], array_column($page['items'], 'Name'));
        $this->assertSame(2, $page['total']);
    }

    public static function provideInvalidQuery(): array
    {
        return [
            'zero limit' => ['query' => 'limit=0'],
            'limit over maximum' => ['query' => 'limit=201'],
            'text offset' => ['query' => 'offset=abc'],
            'array limit' => ['query' => 'limit[]=1'],
        ];
    }

    #[DataProvider('provideInvalidQuery')]
    public function testInvalidQuery(string $query): void
    {
        $this->logInAsTaxonomyUser();
        $paths = ['terms', 'types', 'terms/' . $this->plant->ID . '/children', 'types/' . $this->type->ID . '/terms'];
        foreach ($paths as $path) {
            $this->getJson("$path?$query", 400);
        }
    }

    public function testGetTerm(): void
    {
        $this->logInAsTaxonomyUser(['TAXONOMYTERM_EDIT']);
        $body = $this->getJson('terms/' . $this->vegetable->ID);
        $selfLink = 'admin/api/v1/taxonomy/terms/' . $this->vegetable->ID;
        $this->assertSame($this->vegetable->ID, $body['ID']);
        $this->assertSame('Vegetable', $body['Name']);
        $this->assertArrayNotHasKey('TypeID', $body);
        $this->assertSame($this->plant->ID, $body['ParentID']);
        $this->assertSame(1, $body['Sort']);
        $this->assertSame('Biology', $body['meta']['typeName']);
        $this->assertSame([['ID' => $this->plant->ID, 'Name' => 'Plant']], $body['meta']['ancestors']);
        $this->assertSame(1, $body['meta']['childCount']);
        $this->assertSame(['edit' => true, 'delete' => false, 'createChild' => false], $body['meta']['can']);
        $this->assertStringEndsWith($selfLink, $body['links']['self']);
        $this->assertStringEndsWith("$selfLink/children", $body['links']['children']);
        $this->assertStringEndsWith("$selfLink/schema", $body['links']['schema']);
    }

    public function testSortIsThePositionAmongSiblings(): void
    {
        $this->logInAsTaxonomyUser();
        $first = $this->createTerm('First', 0, $this->animal->ID);
        $second = $this->createTerm('Second', 40, $this->animal->ID);
        $third = $this->createTerm('Third', 40, $this->animal->ID);
        $this->assertSame(1, $this->getJson('terms/' . $first->ID)['Sort']);
        $this->assertSame(2, $this->getJson('terms/' . $second->ID)['Sort']);
        $this->assertSame(3, $this->getJson('terms/' . $third->ID)['Sort']);
        $children = $this->getJson('terms/' . $this->animal->ID . '/children');
        $this->assertSame(['First', 'Second', 'Third'], array_column($children['items'], 'Name'));
        $this->assertSame([1, 2, 3], array_column($children['items'], 'Sort'));
    }

    public function testGetTypeAndItsTerms(): void
    {
        $this->logInAsTaxonomyUser();
        $this->createTerm(HideRecordExtension::HIDDEN_NAME, 3, 0, $this->type->ID);
        $body = $this->getJson('types/' . $this->type->ID);
        $this->assertSame('Biology', $body['Name']);
        $this->assertSame(['edit' => false, 'delete' => false], $body['meta']['can']);
        $this->assertStringEndsWith('/types/' . $this->type->ID . '/terms', $body['links']['terms']);
        $this->assertArrayNotHasKey('children', $body['links']);
        $list = $this->getJson('types/' . $this->type->ID . '/terms');
        $this->assertSame(['Bean', 'Carrot', 'Plant', 'Vegetable'], array_column($list['items'], 'Name'));
        $this->assertSame(4, $list['total']);
        $this->assertSame(['view' => true, 'create' => false], $list['can']);
        $page = $this->getJson('types/' . $this->type->ID . '/terms?limit=1&offset=2');
        $this->assertSame(['Plant'], array_column($page['items'], 'Name'));
        $this->assertSame(4, $page['total']);
    }

    public function testTypeTermsNeedAViewableTypeAndCollection(): void
    {
        $this->logInAsTaxonomyUser();
        $this->getJson('types/999999/terms', 404);
        $this->getJson('types/abc/terms', 404);
        $this->sendJson('POST', 'types/' . $this->type->ID . '/terms', ['Name' => 'X'], 404);
        HideRecordExtension::$hiddenClass = TaxonomyTerm::class;
        $this->getJson('types/' . $this->type->ID . '/terms', 403);
        HideRecordExtension::$hiddenClass = TaxonomyType::class;
        $this->getJson('types/' . $this->type->ID . '/terms', 404);
    }

    public static function provideInvalidPath(): array
    {
        return [
            'text ID' => ['path' => 'terms/abc'],
            'negative ID' => ['path' => 'terms/-1'],
            'move suffix' => ['path' => 'terms/{plantID}:move'],
            'move suffix on children' => ['path' => 'terms/{plantID}:move/children'],
            'unknown segment' => ['path' => 'unknown'],
            'unknown term sub-resource' => ['path' => 'terms/{plantID}/unknown'],
            'extra segment' => ['path' => 'terms/{plantID}/children/extra'],
        ];
    }

    #[DataProvider('provideInvalidPath')]
    public function testInvalidPath(string $path): void
    {
        $this->logInWithPermission('ADMIN');
        $path = str_replace(['{plantID}', '{typeID}'], [$this->plant->ID, $this->type->ID], $path);
        $this->getJson($path, 404);
        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $this->sendJson($method, $path, ['Sort' => 1], 404);
        }
        $this->assertSame('Plant', TaxonomyTerm::get()->byID($this->plant->ID)->Name);
        $this->assertSame(5, TaxonomyTerm::get()->count());
    }

    public function testListsFilterByCanView(): void
    {
        $this->logInAsTaxonomyUser();
        $hidden = $this->createTerm(HideRecordExtension::HIDDEN_NAME, 3, $this->plant->ID);
        $this->createTerm(HideRecordExtension::HIDDEN_NAME, 3);
        $body = $this->getJson('terms');
        $this->assertSame(['Animal', 'Plant'], array_column($body['items'], 'Name'));
        $this->assertSame(2, $body['total']);
        $children = $this->getJson('terms/' . $this->plant->ID . '/children');
        $this->assertSame(['Vegetable', 'Bean'], array_column($children['items'], 'Name'));
        $this->assertSame(2, $children['total']);
        $this->assertSame(2, $this->getJson('terms/' . $this->plant->ID)['meta']['childCount']);
        $this->getJson('terms/' . $hidden->ID, 404);
        $this->getJson('terms/' . $hidden->ID . '/schema', 404);
        HideRecordExtension::$hideEverything = true;
        $this->getJson('terms', 403);
        $this->getJson('types', 403);
    }

    public function testTermSchema(): void
    {
        $this->logInAsTaxonomyUser(['TAXONOMYTERM_EDIT']);
        $headers = ['X-Formschema-Request' => 'schema,state'];
        $body = $this->getJson('terms/' . $this->plant->ID . '/schema', 200, $headers);
        $names = $this->getFieldNames($body['schema']['fields']);
        $this->assertContains('Name', $names);
        $this->assertContains('TypeID', $names);
        $this->assertSame([], array_intersect(['Children', 'Sort', 'ParentID'], $names));
        $this->assertStringEndsWith('terms/' . $this->plant->ID, $body['schema']['action']);
        $this->assertSame('Plant', $this->findField($body['state']['fields'], 'Name')['value']);
        $child = $this->getJson('terms/' . $this->vegetable->ID . '/schema');
        $this->assertNotContains('TypeID', $this->getFieldNames($child['schema']['fields']));
    }

    public function testTypeSchemaLoadsWithTheTermsRelation(): void
    {
        $this->logInWithPermission('ADMIN');
        $body = $this->getJson('types/' . $this->type->ID . '/schema');
        $names = $this->getFieldNames($body['schema']['fields']);
        $this->assertSame(['Name'], array_values(array_intersect(['Name', 'Terms'], $names)));
        $this->assertStringEndsWith('types/' . $this->type->ID, $body['schema']['action']);
        $new = $this->getJson('types/new/schema');
        $this->assertContains('Name', $this->getFieldNames($new['schema']['fields']));
    }

    public static function provideNewTermSchema(): array
    {
        return [
            'edit only' => ['codes' => ['TAXONOMYTERM_EDIT'], 'query' => '', 'expectedStatus' => 403],
            'parent needs edit' => [
                'codes' => ['TAXONOMYTERM_CREATE'],
                'query' => '?parentID={plantID}',
                'expectedStatus' => 403,
            ],
            'text parent' => [
                'codes' => ['TAXONOMYTERM_CREATE', 'TAXONOMYTERM_EDIT'],
                'query' => '?parentID=abc',
                'expectedStatus' => 400,
            ],
            'unknown parent' => [
                'codes' => ['TAXONOMYTERM_CREATE', 'TAXONOMYTERM_EDIT'],
                'query' => '?parentID=999999',
                'expectedStatus' => 404,
            ],
        ];
    }

    #[DataProvider('provideNewTermSchema')]
    public function testNewTermSchema(array $codes, string $query, int $expectedStatus): void
    {
        $this->logInAsTaxonomyUser($codes);
        $query = str_replace('{plantID}', (string) $this->plant->ID, $query);
        $this->getJson("terms/new/schema$query", $expectedStatus);
    }
}
