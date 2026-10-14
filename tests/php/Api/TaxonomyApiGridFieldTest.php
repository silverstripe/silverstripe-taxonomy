<?php

namespace SilverStripe\Taxonomy\Tests\Api;

use LogicException;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Taxonomy\TaxonomyTerm;

class TaxonomyApiGridFieldTest extends FunctionalTest
{
    protected static $required_extensions = [
        TaxonomyTerm::class => [AddGridFieldExtension::class],
    ];

    public function testGridFieldInServedFormThrows(): void
    {
        $this->logInWithPermission('ADMIN');
        $term = TaxonomyTerm::create(['Name' => 'Plant']);
        $term->write();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('field "Terms" of class SilverStripe\Forms\GridField\GridField');
        $this->get('admin/api/v1/taxonomy/terms/' . $term->ID . '/schema');
    }
}
