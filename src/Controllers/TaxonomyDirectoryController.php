<?php

namespace SilverStripe\Taxonomy\Controllers;

use Page;
use PageController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

if (!class_exists(PageController::class)) {
    return;
}

/**
 * Class TaxonomyDirectoryController
 *
 * Controller for returning a list of pages tagged with a specific Taxonomy Term
 *
 * @extends PageController<Page>
 */
class TaxonomyDirectoryController extends PageController
{
    private static $allowed_actions = array(
        'index'
    );

    public function index(HTTPRequest $request)
    {
        $termString = $request->param('ID');

        $pages = Page::get()->filter(['Terms.Name' => $termString]);

        return $this->customise(new ArrayData(array(
            'Title' => $termString,
            'Term' => $termString,
            'Pages' => $pages,
            'Breadcrumbs' => $this->renderBreadcrumb($termString)
        )))->renderWith(array(__CLASS__, "Page"));
    }

    protected function renderBreadcrumb($termString)
    {
        $page = new Page();
        $page->Title = $termString;

        $template = new SSViewer('BreadcrumbsTemplate');
        return $template->process($this->customise(new ArrayData(array(
            "Pages" => new ArrayList(array($page))
        ))));
    }
}
