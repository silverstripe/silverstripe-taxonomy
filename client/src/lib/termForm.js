import { SubmissionError } from 'redux-form';

const FORM_ONLY_KEYS = ['SecurityID'];
const FORM_ONLY_KEY_PREFIX = 'action_';

// A dropdown field holds an option, or a list of options when the form state came from the server
const toApiValue = (value, isMultiple = false) => {
  if (isMultiple) {
    const items = Array.isArray(value) ? value : [value];
    return items.filter((item) => item !== null && item !== undefined && item !== '').map((item) => toApiValue(item));
  }
  if (Array.isArray(value)) {
    return value.length > 0 ? toApiValue(value[0]) : null;
  }
  if (value && typeof value === 'object' && 'value' in value) {
    return value.value;
  }
  return value;
};

const SCHEMA_TYPE_STRUCTURAL = 'Structural';
const SCHEMA_TYPE_MULTI_SELECT = 'MultiSelect';
const MULTI_VALUE_NAME_SUFFIX = /\[\]$/;

const flattenFields = (fields = []) => fields.reduce(
  (flat, field) => [...flat, field, ...flattenFields(field.children)],
  []
);

export const getFieldNames = (fields) => flattenFields(fields).map((field) => field.name);

// Builds the API request body, dropping keys the API refuses: form action, CSRF token and structural fields.
export const createRequestBody = (values, schemaFields) => {
  const fields = flattenFields(schemaFields);
  const structuralNames = fields
    .filter((field) => field.schemaType === SCHEMA_TYPE_STRUCTURAL)
    .map((field) => field.name);
  // The schema names a multi-value field "Name[]", while the API field is "Name"
  const multiValueNames = fields
    .filter((field) => field.schemaType === SCHEMA_TYPE_MULTI_SELECT)
    .map((field) => field.name.replace(MULTI_VALUE_NAME_SUFFIX, ''));
  return Object.keys(values)
    .filter((key) => (
      !FORM_ONLY_KEYS.includes(key)
      && !key.startsWith(FORM_ONLY_KEY_PREFIX)
      && !structuralNames.includes(key)
    ))
    .reduce((body, key) => {
      const name = key.replace(MULTI_VALUE_NAME_SUFFIX, '');
      const isMultiple = multiValueNames.includes(name);
      return { ...body, [isMultiple ? name : key]: toApiValue(values[key], isMultiple) };
    }, {});
};

// Splits API errors into field messages and form messages; an error for a field the form lacks is a form message.
export const sortApiErrors = (errors, fieldNames) => errors.reduce((sorted, error) => {
  if (error.field && fieldNames.includes(error.field)) {
    return {
      ...sorted,
      fieldMessages: {
        ...sorted.fieldMessages,
        [error.field]: { type: 'error', value: error.value },
      },
    };
  }
  return { ...sorted, formMessages: [...sorted.formMessages, error.value] };
}, { fieldMessages: {}, formMessages: [] });

export const createSubmissionError = (fieldMessages, formMessages) => new SubmissionError({
  ...fieldMessages,
  ...(formMessages.length > 0 ? { _error: formMessages.join(' ') } : {}),
});
