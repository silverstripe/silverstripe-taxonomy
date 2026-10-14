<?php

namespace SilverStripe\Taxonomy;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Taxonomy\Api\TaxonomyApiController;
use SilverStripe\View\Requirements;

/**
 * Management interface for taxonomy terms and types. The screen is a React app that only uses the API of
 * TaxonomyApiController.
 *
 * Only the client routes in url_handlers exist. Any other URL returns a 404.
 */
class TaxonomyAdmin extends LeftAndMain
{
    private const ROUTE_NEW = 'new';

    private static $url_segment = 'taxonomy';

    private static $menu_title = 'Taxonomies';

    private static $menu_icon_class = 'font-icon-tags';

    // The more specific routes must come first, because a route also matches longer URLs
    private static array $url_handlers = [
        'terms/new' => 'index',
        'terms/$ID!' => 'index',
        'terms' => 'index',
        'types/new' => 'index',
        'types/$ID!' => 'index',
        'types' => 'index',
    ];

    private static $allowed_actions = [
        'respondNotFound',
    ];

    public function getClientConfig(): array
    {
        return array_merge(parent::getClientConfig(), [
            'reactRouter' => true,
            'apiUrl' => rtrim(TaxonomyApiController::singleton()->Link(), '/'),
        ]);
    }

    public function index(HTTPRequest $request): HTTPResponse
    {
        $id = $request->param('ID');
        if ($id !== null && $id !== TaxonomyAdmin::ROUTE_NEW && !preg_match('/^[1-9][0-9]*$/', $id)) {
            $this->httpError(404);
        }
        return parent::index($request);
    }

    public function respondNotFound(): never
    {
        $this->httpError(404);
    }

    protected function init()
    {
        parent::init();
        Requirements::add_i18n_javascript('silverstripe/taxonomy: client/lang', false);
        Requirements::javascript('silverstripe/taxonomy: client/dist/js/bundle.js');
        Requirements::css('silverstripe/taxonomy: client/dist/styles/bundle.css');
    }

    // Replaces the inherited generic routes, so a URL cannot reach an inherited action by name
    protected function findAction($request)
    {
        if ($request->remaining() === '') {
            return ['rule' => '/', 'action' => 'index'];
        }
        foreach (TaxonomyAdmin::config()->uninherited('url_handlers') as $rule => $action) {
            if ($request->match($rule, true)) {
                return ['rule' => $rule, 'action' => $action];
            }
        }
        return ['rule' => '*', 'action' => 'respondNotFound'];
    }

    protected function handleAction($request, $action)
    {
        // A route also matches longer URLs, e.g. "terms/12/unknown" matches "terms/$ID!"
        if (!$request->allParsed() && $action !== 'respondNotFound') {
            $this->httpError(404);
        }
        return parent::handleAction($request, $action);
    }
}
