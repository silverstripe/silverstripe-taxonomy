<?php

namespace SilverStripe\Taxonomy\Api;

use SilverStripe\Admin\AdminController;
use JsonException;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\Schema\FormSchema;
use SilverStripe\Model\List\SS_List;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Taxonomy\TaxonomyAdmin;
use SilverStripe\Taxonomy\TaxonomyTerm;
use SilverStripe\Taxonomy\TaxonomyType;
use stdClass;

/**
 * Flat JSON API for taxonomy terms and types, served at /admin/api/v1/taxonomy.
 *
 * Only the routes in url_handlers exist. Any other URL returns a JSON 404.
 */
class TaxonomyApiController extends AdminController
{
    private const RESOURCE_TERMS = 'terms';

    private const RESOURCE_TYPES = 'types';

    private const RESOURCES = [
        TaxonomyApiController::RESOURCE_TERMS => TaxonomyTerm::class,
        TaxonomyApiController::RESOURCE_TYPES => TaxonomyType::class,
    ];

    private const FIELD_ACTIONS = ['getTermField', 'getNewTermField'];

    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 200;

    private static ?string $url_segment = 'api/v1/taxonomy';

    private static int $url_priority = 60;

    // The more specific routes must come first, because a route also matches longer URLs
    private static array $url_handlers = [
        'GET /' => 'getIndex',
        'GET terms/new/schema' => 'getNewTermSchema',
        'GET terms/new/field/$FieldName!' => 'getNewTermField',
        'GET terms/$ID!/field/$FieldName!' => 'getTermField',
        'GET terms/$ID!/children' => 'getTermChildren',
        'POST terms/$ID!/children' => 'createChildTerm',
        'GET terms/$ID!/schema' => 'getTermSchema',
        'GET terms/$ID!' => 'getTerm',
        'PATCH terms/$ID!' => 'updateTerm',
        'DELETE terms/$ID!' => 'deleteTerm',
        'GET terms' => 'getTerms',
        'POST terms' => 'createTerm',
        'GET types/new/schema' => 'getNewTypeSchema',
        'GET types/$ID!/schema' => 'getTypeSchema',
        'GET types/$ID!/terms' => 'getTypeTerms',
        'GET types/$ID!' => 'getType',
        'PATCH types/$ID!' => 'updateType',
        'DELETE types/$ID!' => 'deleteType',
        'GET types' => 'getTypes',
        'POST types' => 'createType',
    ];

    private static array $allowed_actions = [
        'getIndex',
        'getNewTermSchema',
        'getTermChildren',
        'getTermSchema',
        'getTerm',
        'getTerms',
        'getNewTermField',
        'getTermField',
        'createChildTerm',
        'updateTerm',
        'deleteTerm',
        'createTerm',
        'getNewTypeSchema',
        'getTypeSchema',
        'getTypeTerms',
        'getType',
        'getTypes',
        'updateType',
        'deleteType',
        'createType',
        'respondNotFound',
    ];

    public static function getRequiredPermissions(): array|string|false
    {
        return TaxonomyAdmin::getRequiredPermissions();
    }

    public function getIndex(): HTTPResponse
    {
        $collections = [];
        foreach (TaxonomyApiController::RESOURCES as $resource => $class) {
            $singleton = $class::singleton();
            $collections[$resource] = [
                'link' => $this->Link($resource),
                'can' => [
                    'view' => (bool) $singleton->canView(),
                    'create' => (bool) $singleton->canCreate(),
                ],
            ];
        }
        return $this->jsonSuccess(200, [
            'securityID' => SecurityToken::inst()->getValue(),
            'collections' => $collections,
        ]);
    }

    public function getTerms(HTTPRequest $request): HTTPResponse
    {
        $this->assertCanViewCollection(TaxonomyTerm::class);
        $list = TaxonomyTerm::get()->filter('ParentID', 0);
        $search = $request->getVar('q') ?? '';
        if (!is_string($search)) {
            $this->respondWithError(400, _t(__CLASS__ . '.INVALID_SEARCH', 'The "q" parameter must be a string.'));
        }
        if ($search !== '') {
            $list = $list->filter('Name:PartialMatch', $search);
        }
        $list = $list->sort(['Sort' => 'ASC', 'ID' => 'ASC']);
        $canCreate = (bool) TaxonomyTerm::singleton()->canCreate();
        return $this->createListResponse($request, $list, TaxonomyApiController::RESOURCE_TERMS, $canCreate);
    }

    public function getTerm(HTTPRequest $request): HTTPResponse
    {
        $term = $this->findRecord(TaxonomyTerm::class, $request);
        return $this->createRecordResponse($this->reloadRecord($term), TaxonomyApiController::RESOURCE_TERMS);
    }

    private function reloadRecord(DataObject $record): DataObject
    {
        // Fields that the write left unset, and values that the model changed, only show on a fresh copy
        return DataObject::get_by_id($record::class, $record->ID, false);
    }

    public function getTermChildren(HTTPRequest $request): HTTPResponse
    {
        $parent = $this->findRecord(TaxonomyTerm::class, $request);
        $this->assertCanViewCollection(TaxonomyTerm::class);
        $list = $parent->Children()->sort(['Sort' => 'ASC', 'ID' => 'ASC']);
        return $this->createListResponse(
            $request,
            $list,
            TaxonomyApiController::RESOURCE_TERMS,
            $parent->canCreateChild()
        );
    }

    public function getTermSchema(HTTPRequest $request): HTTPResponse
    {
        $term = $this->findRecord(TaxonomyTerm::class, $request);
        $form = $this->createForm(TaxonomyApiController::RESOURCE_TERMS, $term, $term->canEdit());
        return $this->createSchemaResponse($request, $form);
    }

    public function getNewTermSchema(HTTPRequest $request): HTTPResponse
    {
        return $this->createSchemaResponse($request, $this->createNewTermForm($request));
    }

    public function getTermField(HTTPRequest $request): HTTPResponse
    {
        $term = $this->findRecord(TaxonomyTerm::class, $request);
        $form = $this->createForm(TaxonomyApiController::RESOURCE_TERMS, $term, $term->canEdit());
        return $this->delegateToField($request, $form);
    }

    public function getNewTermField(HTTPRequest $request): HTTPResponse
    {
        return $this->delegateToField($request, $this->createNewTermForm($request));
    }

    public function createTerm(HTTPRequest $request): HTTPResponse
    {
        $this->assertValidToken($request);
        if (!TaxonomyTerm::singleton()->canCreate()) {
            $this->respondWithError(403);
        }
        return $this->createRecord($request, TaxonomyTerm::create(), TaxonomyApiController::RESOURCE_TERMS);
    }

    public function createChildTerm(HTTPRequest $request): HTTPResponse
    {
        $this->assertValidToken($request);
        $parent = $this->findRecord(TaxonomyTerm::class, $request);
        if (!$parent->canCreateChild()) {
            $this->respondWithError(403);
        }
        $term = TaxonomyTerm::create();
        $term->ParentID = $parent->ID;
        return $this->createRecord($request, $term, TaxonomyApiController::RESOURCE_TERMS);
    }

    public function updateTerm(HTTPRequest $request): HTTPResponse
    {
        return $this->updateRecord($request, TaxonomyTerm::class, TaxonomyApiController::RESOURCE_TERMS);
    }

    public function deleteTerm(HTTPRequest $request): HTTPResponse
    {
        return $this->deleteRecord($request, TaxonomyTerm::class);
    }

    public function getTypes(HTTPRequest $request): HTTPResponse
    {
        $this->assertCanViewCollection(TaxonomyType::class);
        $list = TaxonomyType::get()->sort(['Name' => 'ASC', 'ID' => 'ASC']);
        $canCreate = (bool) TaxonomyType::singleton()->canCreate();
        return $this->createListResponse($request, $list, TaxonomyApiController::RESOURCE_TYPES, $canCreate);
    }

    public function getType(HTTPRequest $request): HTTPResponse
    {
        $type = $this->findRecord(TaxonomyType::class, $request);
        return $this->createRecordResponse($type, TaxonomyApiController::RESOURCE_TYPES);
    }

    public function getTypeTerms(HTTPRequest $request): HTTPResponse
    {
        $type = $this->findRecord(TaxonomyType::class, $request);
        $this->assertCanViewCollection(TaxonomyTerm::class);
        $list = $type->Terms()->sort(['Name' => 'ASC', 'ID' => 'ASC']);
        $canCreate = (bool) TaxonomyTerm::singleton()->canCreate();
        return $this->createListResponse($request, $list, TaxonomyApiController::RESOURCE_TERMS, $canCreate);
    }

    public function createType(HTTPRequest $request): HTTPResponse
    {
        $this->assertValidToken($request);
        if (!TaxonomyType::singleton()->canCreate()) {
            $this->respondWithError(403);
        }
        return $this->createRecord($request, TaxonomyType::create(), TaxonomyApiController::RESOURCE_TYPES);
    }

    public function updateType(HTTPRequest $request): HTTPResponse
    {
        return $this->updateRecord($request, TaxonomyType::class, TaxonomyApiController::RESOURCE_TYPES);
    }

    public function deleteType(HTTPRequest $request): HTTPResponse
    {
        return $this->deleteRecord($request, TaxonomyType::class);
    }

    public function getTypeSchema(HTTPRequest $request): HTTPResponse
    {
        $type = $this->findRecord(TaxonomyType::class, $request);
        $form = $this->createForm(TaxonomyApiController::RESOURCE_TYPES, $type, $type->canEdit());
        return $this->createSchemaResponse($request, $form);
    }

    public function getNewTypeSchema(HTTPRequest $request): HTTPResponse
    {
        if (!TaxonomyType::singleton()->canCreate()) {
            $this->respondWithError(403);
        }
        $form = $this->createForm(TaxonomyApiController::RESOURCE_TYPES, TaxonomyType::create(), true);
        return $this->createSchemaResponse($request, $form);
    }

    public function respondNotFound(): never
    {
        $this->respondWithError(404);
    }

    public function httpError($errorCode, $errorMessage = null): never
    {
        $this->respondWithError((int) $errorCode);
    }

    protected function onInitPermissionFailure(): void
    {
        $this->respondWithError(Security::getCurrentUser() ? 403 : 401);
    }

    // Replaces the inherited generic routes, so a URL cannot reach an action directly by name
    protected function findAction($request)
    {
        foreach (TaxonomyApiController::config()->uninherited('url_handlers') as $rule => $action) {
            if ($request->match($rule, true)) {
                return ['rule' => $rule, 'action' => $action];
            }
        }
        return ['rule' => '*', 'action' => 'respondNotFound'];
    }

    protected function handleAction($request, $action)
    {
        // A route also matches longer URLs, e.g. "terms/12/unknown" matches "terms/$ID!"
        $isFieldAction = in_array($action, TaxonomyApiController::FIELD_ACTIONS, true);
        if (!$request->allParsed() && $action !== 'respondNotFound' && !$isFieldAction) {
            $this->respondWithError(404);
        }
        return parent::handleAction($request, $action);
    }

    private function respondWithError(int $statusCode, string $message = ''): never
    {
        if ($message === '') {
            $message = match ($statusCode) {
                400 => _t(__CLASS__ . '.ERROR_400', 'The request is not valid.'),
                401 => _t(__CLASS__ . '.ERROR_401', 'You must log in to use this API.'),
                403 => _t(__CLASS__ . '.ERROR_403', 'You do not have permission to do this.'),
                404 => _t(__CLASS__ . '.ERROR_404', 'The requested resource does not exist.'),
                default => _t(__CLASS__ . '.ERROR_500', 'The server could not process the request.'),
            };
        }
        $this->respondWithErrors($statusCode, [['type' => 'error', 'code' => $statusCode, 'value' => $message]]);
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     */
    private function respondWithErrors(int $statusCode, array $errors): never
    {
        $body = ['status' => 'error', 'errors' => $errors];
        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response = HTTPResponse::create($json, $statusCode)
            ->addHeader('Content-Type', 'application/json');
        throw new HTTPResponse_Exception($response);
    }

    /**
     * @param class-string<DataObject> $class
     */
    private function assertCanViewCollection(string $class): void
    {
        if (!$class::singleton()->canView()) {
            $this->respondWithError(403);
        }
    }

    private function parseIDValue(string $id): int
    {
        if (!preg_match('/^[1-9][0-9]{0,17}$/', $id)) {
            $this->respondWithError(404);
        }
        return (int) $id;
    }

    /**
     * @param class-string<DataObject> $class
     */
    private function findRecord(string $class, HTTPRequest $request): DataObject
    {
        $record = DataObject::get_by_id($class, $this->parseIDValue((string) $request->param('ID')));
        if (!$record || !$record->canView()) {
            $this->respondWithError(404);
        }
        return $record;
    }

    private function createNewTermForm(HTTPRequest $request): Form
    {
        if (!TaxonomyTerm::singleton()->canCreate()) {
            $this->respondWithError(403);
        }
        $term = TaxonomyTerm::create();
        $parentID = $request->getVar('parentID');
        if ($parentID !== null) {
            $term->ParentID = $this->findParentForNewTerm($parentID)->ID;
        }
        return $this->createForm(TaxonomyApiController::RESOURCE_TERMS, $term, true);
    }

    private function findParentForNewTerm(mixed $parentID): TaxonomyTerm
    {
        if (!is_string($parentID) || !preg_match('/^[1-9][0-9]{0,17}$/', $parentID)) {
            $this->respondWithError(400, _t(
                __CLASS__ . '.INVALID_PARENT_ID',
                'The "parentID" parameter must be a positive whole number.'
            ));
        }
        $parent = TaxonomyTerm::get_by_id((int) $parentID);
        if (!$parent || !$parent->canView()) {
            $this->respondWithError(404);
        }
        if (!$parent->canCreateChild()) {
            $this->respondWithError(403);
        }
        return $parent;
    }

    private function parsePageParameter(
        HTTPRequest $request,
        string $name,
        int $default,
        int $minimum,
        int $maximum
    ): int {
        $value = $request->getVar($name);
        if ($value === null) {
            return $default;
        }
        $isValid = is_string($value)
            && preg_match('/^[0-9]{1,9}$/', $value)
            && (int) $value >= $minimum
            && (int) $value <= $maximum;
        if (!$isValid) {
            $this->respondWithError(400, _t(
                __CLASS__ . '.INVALID_PAGE_PARAMETER',
                'The "{name}" parameter must be a whole number from {minimum} to {maximum}.',
                ['name' => $name, 'minimum' => $minimum, 'maximum' => $maximum]
            ));
        }
        return (int) $value;
    }

    private function createForm(string $resource, DataObject $record, bool $isEditable): Form
    {
        if ($record->exists()) {
            $formAction = $this->Link("$resource/{$record->ID}");
        } elseif ($record instanceof TaxonomyTerm) {
            // The field links of the form build on this action, so it must be the flat "new" route
            $formAction = Controller::join_links(
                $this->Link('terms/new'),
                $record->ParentID ? '?parentID=' . $record->ParentID : ''
            );
        } else {
            $formAction = $this->Link($resource);
        }
        return ServedFormFactory::create()->createForm($record, $this, $formAction, $isEditable);
    }

    private function createRecordResponse(DataObject $record, string $resource): HTTPResponse
    {
        return $this->jsonSuccess(200, $this->representRecord($record, $resource));
    }

    private function representRecord(DataObject $record, string $resource): array
    {
        $form = $this->createForm($resource, $record, $record->canEdit());
        return RecordRepresenter::create($this->Link())->represent($record, $form, $resource);
    }

    private function createListResponse(
        HTTPRequest $request,
        SS_List $list,
        string $resource,
        bool $canCreate
    ): HTTPResponse {
        $limit = $this->parsePageParameter(
            $request,
            'limit',
            TaxonomyApiController::DEFAULT_LIMIT,
            1,
            TaxonomyApiController::MAX_LIMIT
        );
        $offset = $this->parsePageParameter($request, 'offset', 0, 0, 999999999);
        $visible = $list->filterByCallback(fn (DataObject $record) => $record->canView());
        $items = [];
        foreach ($visible->limit($limit, $offset) as $record) {
            $items[] = $this->representRecord($record, $resource);
        }
        return $this->jsonSuccess(200, [
            'items' => $items,
            'total' => $visible->count(),
            'limit' => $limit,
            'offset' => $offset,
            'can' => ['view' => true, 'create' => $canCreate],
        ]);
    }

    private function createSchemaResponse(HTTPRequest $request, Form $form): HTTPResponse
    {
        $parts = $request->getHeader(FormSchema::SCHEMA_HEADER) ?: FormSchema::PART_SCHEMA;
        $data = FormSchema::create()->getMultipartSchema($parts, $request->getURL(true), $form);
        $record = $form->getRecord();
        if (isset($data['schema']['action']) && $record instanceof TaxonomyTerm && !$record->exists()) {
            // The form action of a new term is the "new" route for its field links, but the form submits elsewhere
            $data['schema']['action'] = $record->ParentID
                ? $this->Link("terms/{$record->ParentID}/children")
                : $this->Link('terms');
        }
        return $this->jsonSuccess(200, $data);
    }

    private function delegateToField(HTTPRequest $request, Form $form): HTTPResponse
    {
        $field = $form->Fields()->dataFieldByName((string) $request->param('FieldName'));
        if (!$field) {
            $this->respondWithError(404);
        }
        if ($field->isReadonly() || $field->isDisabled()) {
            $this->respondWithError(403);
        }
        try {
            $response = $field->handleRequest($request);
        } catch (HTTPResponse_Exception $exception) {
            $response = $exception->getResponse();
        }
        if (!$response instanceof HTTPResponse) {
            $this->respondWithError(404);
        }
        if ($response->getStatusCode() >= 400) {
            $body = json_decode($response->getBody(), true);
            $message = is_array($body) && is_string($body['message'] ?? null) ? $body['message'] : '';
            $this->respondWithError($response->getStatusCode(), $message);
        }
        return $this->filterFieldResponse($field, $response);
    }

    /**
     * Removes the records that the member cannot view from the options that a searchable field returns.
     */
    private function filterFieldResponse(FormField $field, HTTPResponse $response): HTTPResponse
    {
        if (!method_exists($field, 'getSourceList') || !$field->getSourceList()) {
            return $response;
        }
        $options = json_decode($response->getBody(), true);
        if (!is_array($options) || !array_is_list($options)) {
            return $response;
        }
        $ids = array_filter(array_column($options, 'value'), 'is_int');
        $viewableIDs = $field->getSourceList()->dataClass()::get()
            ->byIDs($ids)
            ->filterByCallback(fn (DataObject $record) => $record->canView())
            ->column('ID');
        $isViewable = fn ($option) => in_array($option['value'] ?? null, $viewableIDs, true);
        $options = array_values(array_filter($options, $isViewable));
        return $response->setBody(json_encode($options));
    }

    private function assertValidToken(HTTPRequest $request): void
    {
        $token = SecurityToken::inst();
        if ($token->checkRequest($request)) {
            return;
        }
        $body = $this->hasJsonContentType($request) ? json_decode($request->getBody(), true) : null;
        $bodyToken = is_array($body) ? ($body[$token->getName()] ?? null) : null;
        if (is_string($bodyToken) && $token->check($bodyToken)) {
            return;
        }
        $this->respondWithError(400, _t(__CLASS__ . '.INVALID_TOKEN', 'The CSRF token is missing or not valid.'));
    }

    private function hasJsonContentType(HTTPRequest $request): bool
    {
        return (bool) preg_match('#^application/json\s*(;|$)#i', (string) $request->getHeader('Content-Type'));
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonBody(HTTPRequest $request): array
    {
        if (!$this->hasJsonContentType($request)) {
            $this->respondWithError(415, _t(
                __CLASS__ . '.ERROR_415',
                'The request must have the content type "application/json".'
            ));
        }
        $json = $request->getBody();
        try {
            $isObject = json_decode($json, false, 64, JSON_THROW_ON_ERROR) instanceof stdClass;
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->respondWithError(400, _t(__CLASS__ . '.INVALID_JSON', 'The request body is not valid JSON.'));
        }
        if (!$isObject) {
            $this->respondWithError(400, _t(__CLASS__ . '.JSON_NOT_OBJECT', 'The request body must be a JSON object.'));
        }
        // The token may travel in the body, and it is not a field
        unset($data[SecurityToken::inst()->getName()]);
        return $data;
    }

    private function createRecord(HTTPRequest $request, DataObject $record, string $resource): HTTPResponse
    {
        $data = $this->parseJsonBody($request);
        $form = $this->createForm($resource, $record, true);
        if ($record instanceof TaxonomyTerm) {
            $record->Sort = (int) TaxonomyTerm::get()->filter('ParentID', (int) $record->ParentID)->max('Sort') + 1;
        }
        $this->writeRecord($form, $record, $data);
        $response = $this->createRecordResponse($this->reloadRecord($record), $resource);
        $self = Controller::join_links($this->Link($resource), (string) $record->ID);
        return $response->setStatusCode(201)->addHeader('Location', Director::absoluteURL($self));
    }

    /**
     * Writes the form fields of the body. A term also takes "Sort", its 1-based position among its siblings.
     *
     * @param class-string<DataObject> $class
     */
    private function updateRecord(HTTPRequest $request, string $class, string $resource): HTTPResponse
    {
        $this->assertValidToken($request);
        $record = $this->findRecord($class, $request);
        if (!$record->canEdit()) {
            $this->respondWithError(403);
        }
        $data = $this->parseJsonBody($request);
        $hasSort = $record instanceof TaxonomyTerm && array_key_exists('Sort', $data);
        $siblings = [];
        $position = 0;
        if ($hasSort) {
            $position = $data['Sort'];
            unset($data['Sort']);
            $parent = $record->Parent();
            if ($parent->exists() && !$parent->canEdit()) {
                $this->respondWithError(403);
            }
            $siblings = $record->getSiblingsInOrder()->toArray();
            $this->assertValidPosition($position, count($siblings));
        }
        $form = $this->createForm($resource, $record, true);
        DB::get_conn()->withTransaction(function () use ($form, $record, $data, $hasSort, $siblings, $position) {
            if ($data || !$hasSort) {
                $this->writeRecord($form, $record, $data);
            }
            if ($hasSort) {
                $this->reorderSiblings($record, $siblings, $position);
            }
        });
        return $this->createRecordResponse($this->reloadRecord($record), $resource);
    }

    private function assertValidPosition(mixed $position, int $siblingCount): void
    {
        if (is_int($position) && $position >= 1 && $position <= $siblingCount) {
            return;
        }
        $this->respondWithErrors(422, [[
            'type' => 'error',
            'code' => 422,
            'value' => _t(
                __CLASS__ . '.INVALID_POSITION',
                'The "Sort" field must be a whole number from 1 to {count}.',
                ['count' => $siblingCount]
            ),
            'field' => 'Sort',
        ]]);
    }

    /**
     * @param array<int, TaxonomyTerm> $siblings The siblings of the term in order, including the term
     */
    private function reorderSiblings(TaxonomyTerm $term, array $siblings, int $position): void
    {
        $ordered = array_values(array_filter($siblings, fn (TaxonomyTerm $sibling) => $sibling->ID !== $term->ID));
        array_splice($ordered, $position - 1, 0, [$term]);
        $table = DataObject::getSchema()->tableName(TaxonomyTerm::class);
        $now = DBDatetime::now()->getValue();
        // Not write(): TaxonomyTerm::onAfterWrite() would rewrite every descendant
        foreach ($ordered as $index => $sibling) {
            if ((int) $sibling->Sort !== $index + 1) {
                $assignments = ['"Sort"' => $index + 1, '"LastEdited"' => $now];
                SQLUpdate::create("\"$table\"", $assignments, ['"ID"' => $sibling->ID])->execute();
            }
        }
    }

    /**
     * @param class-string<DataObject> $class
     */
    private function deleteRecord(HTTPRequest $request, string $class): HTTPResponse
    {
        $this->assertValidToken($request);
        $record = $this->findRecord($class, $request);
        if (!$record->canDelete()) {
            $this->respondWithError(403);
        }
        DB::get_conn()->withTransaction(fn () => $record->delete());
        return $this->jsonSuccess(204);
    }

    private function writeRecord(Form $form, DataObject $record, array $data): void
    {
        $errors = RecordWriter::create()->write($form, $record, $data);
        if ($errors) {
            $this->respondWithErrors(422, $errors);
        }
    }
}
