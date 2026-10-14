<?php

namespace SilverStripe\Taxonomy\Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Taxonomy\TaxonomyTerm;
use SilverStripe\Taxonomy\TaxonomyType;

// The composer test namespace maps to tests/, so the autoloader cannot find this base class
require_once __DIR__ . '/TaxonomyApiTestCase.php';

class TaxonomyApiWriteTest extends TaxonomyApiTestCase
{
    protected static $required_extensions = [
        TaxonomyTerm::class => [
            HideRecordExtension::class,
            ReadonlyFieldsExtension::class,
            RejectNameExtension::class,
            RecordWritesExtension::class,
        ],
        TaxonomyType::class => [HideRecordExtension::class],
    ];

    public static function provideInvalidToken(): array
    {
        return [
            'no token' => ['token' => null],
            'wrong token' => ['token' => 'wrong'],
        ];
    }

    #[DataProvider('provideInvalidToken')]
    public function testInvalidToken(?string $token): void
    {
        $this->logInWithPermission('ADMIN');
        $plantID = $this->plant->ID;
        $requests = [
            ['POST', 'terms', ['Name' => 'New']],
            ['POST', "terms/$plantID/children", ['Name' => 'New']],
            ['PATCH', "terms/$plantID", ['Name' => 'Changed']],
            ['PATCH', "terms/$plantID", ['Sort' => 1]],
            ['DELETE', "terms/$plantID", null],
            ['POST', 'types', ['Name' => 'New']],
            ['PATCH', 'types/' . $this->type->ID, ['Name' => 'Changed']],
            ['DELETE', 'types/' . $this->type->ID, null],
        ];
        foreach ($requests as [$method, $path, $body]) {
            $response = $this->sendJson($method, $path, $body, 400, ['X-SecurityID' => $token]);
            $this->assertSame(400, $response['errors'][0]['code']);
        }
        $this->assertSame('Plant', TaxonomyTerm::get()->byID($plantID)->Name);
        $this->assertSame(2, (int) TaxonomyTerm::get()->byID($plantID)->Sort);
        $this->assertSame(0, TaxonomyTerm::get()->filter('Name', 'New')->count());
        $this->assertSame(1, TaxonomyType::get()->count());
    }

    public function testTokenInBody(): void
    {
        $this->logInWithPermission('ADMIN');
        $body = ['Name' => 'In body', 'SecurityID' => $this->getSecurityToken()];
        $this->assertSame('In body', $this->sendJson('POST', 'terms', $body, 201, ['X-SecurityID' => null])['Name']);
        $body['SecurityID'] = 'wrong';
        $this->sendJson('POST', 'terms', $body, 400, ['X-SecurityID' => null]);
    }

    public function testRecordsDoNotContainTheToken(): void
    {
        $this->logInWithPermission('ADMIN');
        $token = $this->getSecurityToken();
        $plantID = $this->plant->ID;
        $responses = [
            $this->getJson("terms/$plantID"),
            $this->getJson('terms'),
            $this->getJson('types/' . $this->type->ID),
            $this->sendJson('POST', 'terms', ['Name' => 'New'], 201),
            $this->sendJson('PATCH', "terms/$plantID", ['Name' => 'Changed'], 200),
            $this->sendJson('PATCH', "terms/$plantID", ['Sort' => 1], 200),
        ];
        foreach ($responses as $response) {
            $this->assertArrayNotHasKey('SecurityID', $response);
            $this->assertStringNotContainsString($token, json_encode($response));
        }
        $this->assertArrayNotHasKey('SecurityID', $responses[1]['items'][0]);
    }

    public function testCreateTerm(): void
    {
        $this->logInAsTaxonomyUser(['TAXONOMYTERM_CREATE']);
        $typeID = $this->type->ID;
        $response = $this->mainSession->sendRequest(
            'POST',
            TaxonomyApiTestCase::API . '/terms',
            [],
            ['Content-Type' => 'application/json', 'X-SecurityID' => $this->getSecurityToken()],
            null,
            json_encode(['Name' => 'Fungus', 'TypeID' => $typeID])
        );
        $body = json_decode($response->getBody(), true);
        $term = TaxonomyTerm::get()->filter('Name', 'Fungus')->first();
        $this->assertSame(201, $response->getStatusCode());
        $location = TaxonomyApiTestCase::API . '/terms/' . $term->ID;
        $this->assertStringEndsWith($location, $response->getHeader('Location'));
        $this->assertSame([$term->ID, $typeID, 0, 3], [$body['ID'], $body['TypeID'], $body['ParentID'], $body['Sort']]);
        $this->assertSame($typeID, $term->TypeID);
    }

    public function testCreateChildTerm(): void
    {
        $this->logInAsTaxonomyUser(['TAXONOMYTERM_EDIT', 'TAXONOMYTERM_CREATE']);
        $plantID = $this->plant->ID;
        HideRecordExtension::$createContexts = [];
        $response = $this->sendJson('POST', "terms/$plantID/children", ['Name' => 'Fern'], 201);
        $term = TaxonomyTerm::get()->filter('Name', 'Fern')->first();
        $this->assertSame([$plantID, 3], [$response['ParentID'], $response['Sort']]);
        $this->assertSame([$plantID, $this->type->ID], [$term->ParentID, $term->TypeID]);
        $this->assertSame([['ID' => $plantID, 'Name' => 'Plant']], $response['meta']['ancestors']);
        $getParentID = fn (array $context) => ($context['Parent'] ?? null)?->ID;
        $parentIDs = array_map($getParentID, HideRecordExtension::$createContexts);
        $this->assertContains($plantID, $parentIDs);
    }

    public static function provideInvalidChildParent(): array
    {
        return [
            'text' => ['parent' => 'abc', 'expectedStatus' => 404],
            'unknown' => ['parent' => '999999', 'expectedStatus' => 404],
            'move suffix' => ['parent' => '{plantID}:move', 'expectedStatus' => 404],
            'hidden' => ['parent' => '{hiddenID}', 'expectedStatus' => 404],
            'locked' => ['parent' => '{lockedID}', 'expectedStatus' => 403],
        ];
    }

    #[DataProvider('provideInvalidChildParent')]
    public function testInvalidChildParent(string $parent, int $expectedStatus): void
    {
        $this->logInWithPermission('ADMIN');
        $hidden = $this->createTerm(HideRecordExtension::HIDDEN_NAME, 3);
        $locked = $this->createTerm(HideRecordExtension::LOCKED_NAME, 4);
        $parent = str_replace(
            ['{plantID}', '{hiddenID}', '{lockedID}'],
            [$this->plant->ID, $hidden->ID, $locked->ID],
            $parent
        );
        $this->sendJson('POST', "terms/$parent/children", ['Name' => 'X'], $expectedStatus);
        $this->assertSame(0, TaxonomyTerm::get()->filter('Name', 'X')->count());
    }

    public static function provideRefusedField(): array
    {
        return [
            'ParentID' => ['field' => 'ParentID', 'value' => 1],
            'ID' => ['field' => 'ID', 'value' => 1],
            'meta' => ['field' => 'meta', 'value' => []],
            'ClassName' => ['field' => 'ClassName', 'value' => 'Member'],
            'unknown' => ['field' => 'Unknown', 'value' => 1],
            'read-only' => ['field' => 'Note', 'value' => 'Y'],
            'disabled' => ['field' => 'Locked', 'value' => 'Y'],
            'array for a single value field' => ['field' => 'Name', 'value' => ['A']],
        ];
    }

    #[DataProvider('provideRefusedField')]
    public function testRefusedField(string $field, mixed $value): void
    {
        $this->logInWithPermission('ADMIN');
        $termCount = TaxonomyTerm::get()->count();
        $plantID = $this->plant->ID;
        $body = ['Name' => 'X', $field => $value];
        $requests = [['POST', 'terms'], ['POST', "terms/$plantID/children"], ['PATCH', "terms/$plantID"]];
        foreach ($requests as [$method, $path]) {
            $response = $this->sendJson($method, $path, $body, 422);
            $this->assertSame([$field], array_column($response['errors'], 'field'));
            $this->assertSame([422], array_column($response['errors'], 'code'));
        }
        $this->assertSame($termCount, TaxonomyTerm::get()->count());
        $this->assertSame('Plant', TaxonomyTerm::get()->byID($plantID)->Name);
    }

    public function testSortIsRefusedOnCreate(): void
    {
        $this->logInWithPermission('ADMIN');
        $response = $this->sendJson('POST', 'terms', ['Name' => 'X', 'Sort' => 1], 422);
        $this->assertSame(['Sort'], $this->getErrorFields($response['errors']));
        $this->sendJson('POST', 'types/' . $this->type->ID, ['Sort' => 1], 404);
        $this->sendJson('PATCH', 'types/' . $this->type->ID, ['Sort' => 1], 422);
        $this->assertSame(0, TaxonomyTerm::get()->filter('Name', 'X')->count());
    }

    public static function provideInvalidValue(): array
    {
        return [
            'empty name' => ['body' => '{"Name": ""}', 'field' => 'Name'],
            'whitespace name' => ['body' => '{"Name": "   "}', 'field' => 'Name'],
            'too long name' => ['body' => '{"Name": "' . '%s' . '"}', 'field' => 'Name'],
            'model validation' => [
                'body' => '{"Name": "Rejected"}',
                'field' => 'Name',
            ],
            'unknown type' => ['body' => '{"Name": "X", "TypeID": 999999}', 'field' => 'TypeID'],
        ];
    }

    #[DataProvider('provideInvalidValue')]
    public function testInvalidValue(string $body, string $field): void
    {
        $this->logInWithPermission('ADMIN');
        $body = sprintf($body, str_repeat('a', 256));
        $count = TaxonomyTerm::get()->count();
        $created = $this->sendJson('POST', 'terms', $body, 422);
        $this->assertSame([$field], $this->getErrorFields($created['errors']));
        $updated = $this->sendJson('PATCH', 'terms/' . $this->plant->ID, $body, 422);
        $this->assertSame([$field], $this->getErrorFields($updated['errors']));
        $this->assertSame($count, TaxonomyTerm::get()->count());
        $plant = TaxonomyTerm::get()->byID($this->plant->ID);
        $this->assertSame(['Plant', $this->type->ID], [$plant->Name, $plant->TypeID]);
    }

    public function testUpdateTermChangesOnlyTheGivenFields(): void
    {
        $this->logInWithPermission('ADMIN');
        $path = 'terms/' . $this->plant->ID;
        $response = $this->sendJson('PATCH', $path, ['Name' => 'Flora'], 200);
        $term = TaxonomyTerm::get()->byID($this->plant->ID);
        $this->assertSame(['Flora', $this->type->ID, 2], [$response['Name'], $response['TypeID'], $response['Sort']]);
        $this->assertSame([$this->type->ID, 2, 0], [$term->TypeID, (int) $term->Sort, $term->ParentID]);
        $other = TaxonomyType::create(['Name' => 'Other']);
        $other->write();
        $this->sendJson('PATCH', $path, ['TypeID' => $other->ID], 200);
        $this->assertSame($other->ID, TaxonomyTerm::get()->byID($this->carrot->ID)->TypeID);
        $this->assertSame(0, $this->sendJson('PATCH', $path, ['TypeID' => null], 200)['TypeID']);
        $child = 'terms/' . $this->vegetable->ID;
        $response = $this->sendJson('PATCH', $child, ['TypeID' => $this->type->ID], 422);
        $this->assertSame(['TypeID'], $this->getErrorFields($response['errors']));
    }

    public static function provideBlockedUpdate(): array
    {
        return [
            'hidden term' => ['name' => 'Hidden', 'expectedStatus' => 404],
            'locked term' => ['name' => 'Locked', 'expectedStatus' => 403],
        ];
    }

    #[DataProvider('provideBlockedUpdate')]
    public function testBlockedUpdate(string $name, int $expectedStatus): void
    {
        $this->logInWithPermission('ADMIN');
        $term = $this->createTerm($name, 3);
        $this->sendJson('PATCH', "terms/{$term->ID}", ['Name' => 'Open'], $expectedStatus);
        $this->sendJson('PATCH', "terms/{$term->ID}", ['Sort' => 1], $expectedStatus);
        $this->assertSame($name, TaxonomyTerm::get()->byID($term->ID)->Name);
        $this->assertSame(3, $this->getSort($term));
    }

    public function testDeleteTermDeletesDescendants(): void
    {
        $this->logInWithPermission('ADMIN');
        $this->assertNull($this->sendJson('DELETE', 'terms/' . $this->plant->ID, null, 204));
        $this->assertSame(['Animal'], TaxonomyTerm::get()->column('Name'));
        $this->getJson('terms/' . $this->plant->ID, 404);
        $this->assertSame(1, TaxonomyType::get()->count());
    }

    public function testPatchSortMovesTheTerm(): void
    {
        $this->logInAsTaxonomyUser(['TAXONOMYTERM_EDIT']);
        $response = $this->sendJson('PATCH', 'terms/' . $this->plant->ID, ['Sort' => 1], 200);
        $this->assertSame([$this->plant->ID, 1], [$response['ID'], $response['Sort']]);
        $this->assertSame(['Plant', 'Animal'], array_column($this->getJson('terms')['items'], 'Name'));
        $this->sendJson('PATCH', 'terms/' . $this->vegetable->ID, ['Sort' => 2], 200);
        $children = $this->getJson('terms/' . $this->plant->ID . '/children');
        $this->assertSame(['Bean', 'Vegetable'], array_column($children['items'], 'Name'));
        $this->assertSame(['Plant', 'Animal'], array_column($this->getJson('terms')['items'], 'Name'));
    }

    public function testPatchSortWithFields(): void
    {
        $this->logInWithPermission('ADMIN');
        $response = $this->sendJson('PATCH', 'terms/' . $this->plant->ID, ['Name' => 'Flora', 'Sort' => 1], 200);
        $this->assertSame(['Flora', 1], [$response['Name'], $response['Sort']]);
        $this->assertSame(['Flora', 'Animal'], array_column($this->getJson('terms')['items'], 'Name'));
        $this->sendJson('PATCH', 'terms/' . $this->plant->ID, ['Name' => '', 'Sort' => 2], 422);
        $this->assertSame(['Flora', 'Animal'], array_column($this->getJson('terms')['items'], 'Name'));
    }

    public function testPatchSortRenumbersSiblings(): void
    {
        $this->logInWithPermission('ADMIN');
        $first = $this->createTerm('First', 5, $this->animal->ID);
        $this->createTerm('Second', 10, $this->animal->ID);
        $this->createTerm('Third', 20, $this->animal->ID);
        $fourth = $this->createTerm('Fourth', 20, $this->animal->ID);
        $this->sendJson('PATCH', "terms/{$fourth->ID}", ['Sort' => 2], 200);
        $this->sendJson('PATCH', "terms/{$first->ID}", ['Sort' => 4], 200);
        $children = TaxonomyTerm::get()->filter('ParentID', $this->animal->ID)->sort(['Sort' => 'ASC', 'ID' => 'ASC']);
        $this->assertSame(['Fourth', 'Second', 'Third', 'First'], $children->column('Name'));
        $this->assertSame([1, 2, 3, 4], $children->column('Sort'));
        $this->assertSame(2, $this->getSort($this->plant));
    }

    public function testPatchSortDoesNotWriteDescendants(): void
    {
        $this->logInWithPermission('ADMIN');
        RecordWritesExtension::$writtenIDs = [];
        $this->sendJson('PATCH', 'terms/' . $this->plant->ID, ['Sort' => 1], 200);
        $this->assertSame([], RecordWritesExtension::$writtenIDs);
        $this->assertSame(1, $this->getSort($this->plant));
        $this->assertSame(2, $this->getSort($this->animal));
        $this->assertSame(1, $this->getSort($this->vegetable));
    }

    public static function provideInvalidSort(): array
    {
        return [
            'zero' => ['sort' => '0'],
            'negative' => ['sort' => '-1'],
            'over sibling count' => ['sort' => '3'],
            'huge' => ['sort' => '99999999999999999999'],
            'text' => ['sort' => '"abc"'],
            'numeric text' => ['sort' => '"1"'],
            'float' => ['sort' => '1.5'],
            'whole float' => ['sort' => '1.0'],
            'true' => ['sort' => 'true'],
            'null' => ['sort' => 'null'],
            'array' => ['sort' => '[1]'],
        ];
    }

    #[DataProvider('provideInvalidSort')]
    public function testInvalidSort(string $sort): void
    {
        $this->logInWithPermission('ADMIN');
        $body = '{"Name": "Changed", "Sort": ' . $sort . '}';
        $response = $this->sendJson('PATCH', 'terms/' . $this->animal->ID, $body, 422);
        $this->assertSame(['Sort'], $this->getErrorFields($response['errors']));
        $this->assertStringContainsString('"Sort" field must be a whole number', $response['errors'][0]['value']);
        $topLevel = TaxonomyTerm::get()->filter('ParentID', 0)->sort('Sort');
        $this->assertSame(['Animal', 'Plant'], $topLevel->column('Name'));
        $this->assertSame([1, 2], $topLevel->column('Sort'));
    }

    public function testPatchSortNeedsEditOnTheParent(): void
    {
        $this->logInWithPermission('ADMIN');
        $locked = $this->createTerm(HideRecordExtension::LOCKED_NAME, 3);
        $child = $this->createTerm('Child', 1, $locked->ID);
        $this->createTerm('Sibling', 2, $locked->ID);
        $this->sendJson('PATCH', "terms/{$child->ID}", ['Sort' => 2], 403);
        $this->assertSame(1, $this->getSort($child));
        $renamed = $this->sendJson('PATCH', "terms/{$child->ID}", ['Name' => 'Renamed'], 200);
        $this->assertSame('Renamed', $renamed['Name']);
    }

    public function testTypeLifecycle(): void
    {
        $this->logInWithPermission('ADMIN');
        $response = $this->sendJson('POST', 'types', ['Name' => 'Geology'], 201);
        $this->assertSame(['edit' => true, 'delete' => true], $response['meta']['can']);
        $invalid = $this->sendJson('POST', 'types', ['Name' => ''], 422);
        $this->assertSame(['Name'], $this->getErrorFields($invalid['errors']));
        $path = 'types/' . $response['ID'];
        $this->assertSame('Science', $this->sendJson('PATCH', $path, ['Name' => 'Science'], 200)['Name']);
        $this->sendJson('PATCH', $path, ['Name' => ''], 422);
        $this->assertSame('Science', TaxonomyType::get_by_id($response['ID'])->Name);
        $this->sendJson('DELETE', $path, null, 204);
        $this->getJson($path, 404);
        $hidden = TaxonomyType::create(['Name' => HideRecordExtension::HIDDEN_NAME]);
        $hidden->write();
        $this->sendJson('PATCH', "types/{$hidden->ID}", ['Name' => 'Shown'], 404);
        $this->sendJson('DELETE', "types/{$hidden->ID}", null, 404);
    }
}
