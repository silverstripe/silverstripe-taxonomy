<?php

namespace SilverStripe\Taxonomy\Tests\Api;

use SilverStripe\Control\HTTPResponse;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Taxonomy\TaxonomyAdmin;
use SilverStripe\Taxonomy\TaxonomyTerm;
use SilverStripe\Taxonomy\TaxonomyType;

abstract class TaxonomyApiTestCase extends FunctionalTest
{
    protected const API = 'admin/api/v1/taxonomy';

    protected const FIXTURE_DIRECTORY = __DIR__ . '/../../../client/src/tests/fixtures';

    protected const SECTION_CODE = 'CMS_ACCESS_SilverStripe\\Taxonomy\\TaxonomyAdmin';

    protected $usesDatabase = true;

    protected static $required_extensions = [
        TaxonomyTerm::class => [HideRecordExtension::class],
        TaxonomyType::class => [HideRecordExtension::class],
    ];

    protected TaxonomyType $type;

    protected TaxonomyTerm $plant;

    protected TaxonomyTerm $animal;

    protected TaxonomyTerm $vegetable;

    protected TaxonomyTerm $carrot;

    protected TaxonomyTerm $bean;

    /**
     * @var array<string, array<int, int>> Position among the records that requests created, keyed by kind then ID
     */
    private array $createdIDs = ['term' => [], 'type' => []];

    protected function setUp(): void
    {
        parent::setUp();
        SecurityToken::enable();
        $this->type = TaxonomyType::create(['Name' => 'Biology']);
        $this->type->write();
        $this->plant = $this->createTerm('Plant', 2, 0, $this->type->ID);
        $this->animal = $this->createTerm('Animal', 1);
        $this->vegetable = $this->createTerm('Vegetable', 1, $this->plant->ID);
        $this->carrot = $this->createTerm('Carrot', 1, $this->vegetable->ID);
        $this->bean = $this->createTerm('Bean', 2, $this->plant->ID);
    }

    protected function tearDown(): void
    {
        HideRecordExtension::$hideEverything = false;
        HideRecordExtension::$hiddenClass = '';
        HideRecordExtension::$createContexts = [];
        parent::tearDown();
    }

    protected function createTerm(string $name, int $sort, int $parentID = 0, int $typeID = 0): TaxonomyTerm
    {
        $term = TaxonomyTerm::create(['Name' => $name, 'Sort' => $sort, 'ParentID' => $parentID, 'TypeID' => $typeID]);
        $term->write();
        return $term;
    }

    /**
     * Writes the normalised response to client/src/tests/fixtures when TAXONOMY_RECORD_FIXTURES=1. Otherwise
     * asserts that the committed fixture still matches, so a change to the contract fails here.
     *
     * @param array<string, mixed> $body
     */
    protected function assertMatchesFixture(string $name, int $status, array $body): void
    {
        $contents = json_encode(
            ['status' => $status, 'body' => $this->normaliseFixtureData($body)],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . "\n";
        $path = TaxonomyApiTestCase::FIXTURE_DIRECTORY . '/' . $name . '.json';
        if (getenv('TAXONOMY_RECORD_FIXTURES') === '1') {
            if (!is_dir(TaxonomyApiTestCase::FIXTURE_DIRECTORY)) {
                mkdir(TaxonomyApiTestCase::FIXTURE_DIRECTORY, 0777, true);
            }
            file_put_contents($path, $contents);
            return;
        }
        $this->assertFileExists($path);
        $this->assertSame(file_get_contents($path), $contents);
    }

    /**
     * Replaces values that change between test runs, so recorded fixtures stay stable. Database IDs are not
     * stable, because MySQL test databases clear tables with DELETE and keep counting.
     */
    private function normaliseFixtureData(mixed $data, bool $isType = false): mixed
    {
        if (is_string($data)) {
            $data = preg_replace_callback(
                '#parentID=(\d+)#',
                fn (array $matches) => 'parentID=' . $this->normaliseFixtureID((int) $matches[1], false),
                $data
            );
            return preg_replace_callback(
                '#/(terms|types)/(\d+)#',
                fn (array $matches) => '/' . $matches[1] . '/' . $this->normaliseFixtureID(
                    (int) $matches[2],
                    $matches[1] === 'types'
                ),
                $data
            );
        }
        if (!is_array($data)) {
            return $data;
        }
        $isType = ($data['meta']['className'] ?? null) === TaxonomyType::class || $isType;
        $fieldName = $data['name'] ?? null;
        $normalised = [];
        foreach ($data as $key => $value) {
            // Form schema and state describe a field as an array with a "name" key
            if ($fieldName === 'SecurityID' && $key === 'value') {
                $normalised[$key] = 'SECURITY_ID';
                continue;
            }
            if ($fieldName === 'TypeID' && in_array($key, ['value', 'options', 'source'], true)) {
                $normalised[$key] = $this->normaliseTypeOptions($value);
                continue;
            }
            $normalised[$key] = match ($key) {
                'securityID' => 'SECURITY_ID',
                'ID' => is_int($value) ? $this->normaliseFixtureID($value, $isType) : $value,
                'ParentID' => $this->normaliseFixtureID((int) $value, false),
                'TypeID' => $this->normaliseFixtureID((int) $value, true),
                default => $this->normaliseFixtureData($value, $isType && $key !== 'ancestors'),
            };
        }
        return $normalised;
    }

    /**
     * @param mixed $options A type ID, or a list of options that each have a type ID as their "value"
     */
    private function normaliseTypeOptions(mixed $options): mixed
    {
        if (!is_array($options)) {
            return is_int($options) ? $this->normaliseFixtureID($options, true) : $options;
        }
        return array_map(function (array $option) {
            $option['value'] = $this->normaliseFixtureID((int) $option['value'], true);
            return $option;
        }, $options);
    }

    private function normaliseFixtureID(int $id, bool $isType): int
    {
        if ($id === 0) {
            return 0;
        }
        $ids = $isType ? [$this->type->ID] : [
            $this->plant->ID,
            $this->animal->ID,
            $this->vegetable->ID,
            $this->carrot->ID,
            $this->bean->ID,
        ];
        $position = array_search($id, $ids, true);
        if ($position === false) {
            // A record that a test request created, numbered in the order it appears after the ones from setUp()
            $kind = $isType ? 'type' : 'term';
            $this->createdIDs[$kind][$id] ??= count($ids) + count($this->createdIDs[$kind]);
            return $this->createdIDs[$kind][$id] + 1;
        }
        return $position + 1;
    }

    protected function getSort(TaxonomyTerm $term): int
    {
        // get_by_id() serves a cached record that a reorder through SQL does not update
        return (int) TaxonomyTerm::get()->byID($term->ID)->Sort;
    }

    protected function logInAsTaxonomyUser(array $extraCodes = []): void
    {
        $this->logInWithPermission([TaxonomyAdmin::getRequiredPermissions(), ...$extraCodes]);
    }

    /**
     * @param array<string, string> $headers
     */
    protected function getJson(string $path, int $expectedStatus = 200, array $headers = []): array
    {
        $response = $this->get(rtrim(TaxonomyApiTestCase::API . '/' . $path, '/'), null, $headers);
        $this->assertInstanceOf(HTTPResponse::class, $response);
        $this->assertSame($expectedStatus, $response->getStatusCode());
        $this->assertStringStartsWith('application/json', $response->getHeader('Content-Type'));
        return json_decode($response->getBody(), true);
    }

    protected function findField(array $fields, string $name): ?array
    {
        foreach ($fields as $field) {
            if ($field['name'] === $name) {
                return $field;
            }
            $found = $this->findField($field['children'] ?? [], $name);
            if ($found) {
                return $found;
            }
        }
        return null;
    }

    protected function getFieldNames(array $fields): array
    {
        $names = [];
        foreach ($fields as $field) {
            $names[] = $field['name'];
            $names = array_merge($names, $this->getFieldNames($field['children'] ?? []));
        }
        return $names;
    }

    protected function getSecurityToken(): string
    {
        $response = $this->get(TaxonomyApiTestCase::API);
        return json_decode($response->getBody(), true)['securityID'] ?? '';
    }

    /**
     * Sends a request with a JSON body, the CSRF token and the content type header. A header with a null value is
     * left out.
     *
     * @param array<string, ?string> $headers
     */
    protected function sendJson(
        string $method,
        string $path,
        mixed $body = null,
        int $expectedStatus = 200,
        array $headers = []
    ): ?array {
        $headers = array_filter([
            'Content-Type' => 'application/json',
            'X-SecurityID' => array_key_exists('X-SecurityID', $headers) ? null : $this->getSecurityToken(),
            ...$headers,
        ], fn (?string $value) => $value !== null);
        $encoded = is_string($body) ? $body : json_encode($body);
        $url = rtrim(TaxonomyApiTestCase::API . '/' . $path, '/');
        $response = $this->mainSession->sendRequest($method, $url, [], $headers, null, $encoded);
        $this->assertSame($expectedStatus, $response->getStatusCode());
        if ($response->getBody() === '') {
            return null;
        }
        $this->assertStringStartsWith('application/json', $response->getHeader('Content-Type'));
        return json_decode($response->getBody(), true);
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     * @return array<int, string>
     */
    protected function getErrorFields(array $errors): array
    {
        return array_values(array_filter(array_column($errors, 'field')));
    }
}
