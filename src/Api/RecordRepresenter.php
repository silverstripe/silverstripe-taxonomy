<?php

namespace SilverStripe\Taxonomy\Api;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\Taxonomy\TaxonomyTerm;
use SilverStripe\Taxonomy\TaxonomyType;

/**
 * Converts a record into the array that the API returns for it.
 * Attribute keys are the names of the data fields in the served form of the record.
 */
class RecordRepresenter
{
    use Injectable;

    public function __construct(private readonly string $apiLink)
    {
    }

    public function represent(DataObject $record, Form $form, string $resource): array
    {
        $data = ['ID' => $record->ID];
        $tokenName = $form->getSecurityToken()->getName();
        foreach ($form->Fields()->getDataFields() as $name => $field) {
            if ($name !== $tokenName) {
                $data[$name] = $field->dataValue();
            }
        }
        $isTerm = $record instanceof TaxonomyTerm;
        if ($isTerm) {
            $data['ParentID'] = $record->ParentID;
            $siblingIDs = array_map('intval', $record->getSiblingsInOrder()->column('ID'));
            $data['Sort'] = array_search($record->ID, $siblingIDs, true) + 1;
        }
        $data['meta'] = $isTerm ? $this->createTermMeta($record) : $this->createTypeMeta($record);
        $data['links'] = $this->createLinks($record, $resource);
        return $data;
    }

    private function createTermMeta(TaxonomyTerm $term): array
    {
        $ancestors = [];
        foreach (array_reverse($term->getAncestors()->toArray()) as $ancestor) {
            if ($ancestor->canView()) {
                $ancestors[] = ['ID' => $ancestor->ID, 'Name' => $ancestor->Name];
            }
        }
        $type = $term->config()->get('type_inheritance_enabled') ? $term->getTaxonomy()->Type() : $term->Type();
        return [
            'className' => $term->ClassName,
            'typeName' => $type->exists() && $type->canView() ? $type->Name : '',
            'ancestors' => $ancestors,
            'childCount' => $term->Children()->filterByCallback(fn (TaxonomyTerm $child) => $child->canView())->count(),
            'can' => [
                'edit' => (bool) $term->canEdit(),
                'delete' => (bool) $term->canDelete(),
                'createChild' => $term->canCreateChild(),
            ],
        ];
    }

    private function createTypeMeta(DataObject $type): array
    {
        return [
            'className' => $type->ClassName,
            'can' => [
                'edit' => (bool) $type->canEdit(),
                'delete' => (bool) $type->canDelete(),
            ],
        ];
    }

    private function createLinks(DataObject $record, string $resource): array
    {
        $self = Controller::join_links($this->apiLink, $resource, (string) $record->ID);
        $links = ['self' => $self];
        if ($record instanceof TaxonomyTerm) {
            $links['children'] = Controller::join_links($self, 'children');
        }
        if ($record instanceof TaxonomyType) {
            $links['terms'] = Controller::join_links($self, 'terms');
        }
        $links['schema'] = Controller::join_links($self, 'schema');
        return $links;
    }
}
