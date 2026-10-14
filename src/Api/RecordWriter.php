<?php

namespace SilverStripe\Taxonomy\Api;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\MultiSelectField;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;

/**
 * Writes the data of a request body into a record, through the form that the API serves for the record.
 */
class RecordWriter
{
    use Injectable;

    private const STATUS_UNPROCESSABLE = 422;

    private const NON_ERROR_TYPES = [
        ValidationResult::TYPE_GOOD,
        ValidationResult::TYPE_INFO,
        ValidationResult::TYPE_WARNING,
    ];

    /**
     * Refuses fields that the form does not have, or that are read-only or disabled. Then merges the data over the
     * current values of the form, validates the whole form and writes the record in a transaction.
     *
     * @param array<string, mixed> $data
     * @return array<int, array{type: string, code: int, value: string, field?: string}> The errors, or an empty
     * array when the record was written
     */
    public function write(Form $form, DataObject $record, array $data): array
    {
        $errors = $this->findRefusedFields($form, $data);
        if ($errors) {
            return $errors;
        }
        $form->loadDataFrom($data, Form::MERGE_DEFAULT, array_map('strval', array_keys($data)));
        $result = $form->validate();
        if (!$result->isValid()) {
            return $this->createErrors($result);
        }
        try {
            DB::get_conn()->withTransaction(function () use ($form, $record) {
                $form->saveInto($record);
                $record->write();
            });
        } catch (ValidationException $exception) {
            return $this->createErrors($exception->getResult());
        }
        return [];
    }

    private function findRefusedFields(Form $form, array $data): array
    {
        $errors = [];
        foreach ($data as $key => $value) {
            $name = (string) $key;
            $field = $form->Fields()->dataFieldByName($name);
            if (!$field || $field->getName() !== $name || $field->isReadonly() || $field->isDisabled()) {
                $errors[] = $this->createError(_t(
                    __CLASS__ . '.FIELD_NOT_WRITABLE',
                    'The "{field}" field does not exist or cannot be changed.',
                    ['field' => $name]
                ), $name);
            } elseif (is_array($value) && !($field instanceof MultiSelectField)) {
                $errors[] = $this->createError(_t(
                    __CLASS__ . '.FIELD_NOT_SCALAR',
                    'The "{field}" field needs a single value.',
                    ['field' => $name]
                ), $name);
            }
        }
        return $errors;
    }

    private function createErrors(ValidationResult $result): array
    {
        $errors = [];
        foreach ($result->getMessages() as $message) {
            // Validators also use their own message types for errors, such as "required"
            if (in_array($message['messageType'], RecordWriter::NON_ERROR_TYPES, true)) {
                continue;
            }
            $text = $message['messageCast'] === ValidationResult::CAST_HTML
                ? html_entity_decode(strip_tags($message['message']))
                : $message['message'];
            $errors[] = $this->createError($text, ($message['fieldName'] ?? '') ?: null);
        }
        if (!$errors) {
            $errors[] = $this->createError(_t(__CLASS__ . '.INVALID', 'The data is not valid.'));
        }
        return $errors;
    }

    private function createError(string $message, ?string $field = null): array
    {
        $error = ['type' => 'error', 'code' => RecordWriter::STATUS_UNPROCESSABLE, 'value' => $message];
        if ($field !== null) {
            $error['field'] = $field;
        }
        return $error;
    }
}
