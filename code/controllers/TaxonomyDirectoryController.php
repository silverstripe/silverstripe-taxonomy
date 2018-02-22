<?php

/**
 * Class TaxonomyDirectoryController
 *
 * Controller for returning a list of pages tagged with a specific Taxonomy Term
 */
class TaxonomyDirectoryController extends Page_Controller
{

    private static $allowed_actions = array(
        'index'
    );

    public function index(SS_HTTPRequest $HTTPRequest)
    {
        //look up the term to avoid sql injection by using the ORM
        $term = TaxonomyTerm::get()->filter(['URLSegment' => $HTTPRequest->param('ID')])->first();

        if ($term) {
            $data = ArrayData::create(array(
                'Title' => $term->Name,
                'Results' => Page::get()
                    ->innerJoin(
                        'BasePage_Terms',
                        '"Page"."ID"="BasePage_Terms"."BasePageID"')
                    ->innerJoin(
                        'TaxonomyTerm',
                        "\"BasePage_Terms\".\"TaxonomyTermID\"=\"TaxonomyTerm\".\"ID\" AND \"TaxonomyTerm\".\"ID\" = '$term->ID'"),
                'Breadcrumbs' => $this->renderBreadcrumb($term->Name)
            ));

        } else {
           //no term found return null results
           $data = ArrayData::create(array(
               'Title' => Convert::raw2sql($HTTPRequest->param('ID'), true),
               'Results' => null,
               'Breadcrumbs' => $this->renderBreadcrumb(Convert::raw2sql($HTTPRequest->param('ID'), true))
           ));
        }

        return $this->customise($data)->renderWith(array("TaxonomyDirectory", "Page"));
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

