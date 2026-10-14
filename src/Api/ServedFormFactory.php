<?php

namespace SilverStripe\Taxonomy\Api;

use LogicException;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\DataObject;

/**
 * Builds the form that the API serves for a record, for the current user.
 */
class ServedFormFactory
{
    use Injectable;

    private const FORM_NAME = 'TaxonomyForm';

    /**
     * Field classes that need a nested request handler, which the API does not provide
     */
    private const UNSUPPORTED_FIELD_CLASSES = [GridField::class];

    /**
     * @throws LogicException if the form contains a field that needs a nested request handler
     */
    public function createForm(
        DataObject $record,
        RequestHandler $controller,
        string $formAction,
        bool $isEditable
    ): Form {
        $fields = $record->getCMSFields();
        $this->assertFieldsAreSupported($record, $fields);
        $actions = FieldList::create();
        if ($isEditable) {
            $actions->push(FormAction::create('save', _t(__CLASS__ . '.SAVE', 'Save')));
        }
        $validator = $record->getCMSCompositeValidator();
        $form = Form::create($controller, ServedFormFactory::FORM_NAME, $fields, $actions, $validator);
        $form->setFormAction($formAction);
        $form->loadDataFrom($record);
        if (!$isEditable) {
            $form->makeReadonly();
        }
        return $form;
    }

    private function assertFieldsAreSupported(DataObject $record, FieldList $fields): void
    {
        $fields->recursiveWalk(function (FormField $field) use ($record) {
            foreach (ServedFormFactory::UNSUPPORTED_FIELD_CLASSES as $class) {
                if ($field instanceof $class) {
                    throw new LogicException(sprintf(
                        'The taxonomy API cannot serve field "%s" of class %s in the form for %s.'
                        . ' The field needs a nested request handler.',
                        $field->getName(),
                        $field::class,
                        $record::class
                    ));
                }
            }
        });
    }
}
