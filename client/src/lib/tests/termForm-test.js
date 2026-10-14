/* global test, expect */
import {
  createRequestBody,
  sortApiErrors,
} from '../termForm';

const schemaFields = [
  {
    name: 'Root',
    schemaType: 'Structural',
    children: [
      { name: 'Main', schemaType: 'Structural', children: [{ name: 'Name', schemaType: 'Text' }, { name: 'TypeID', schemaType: 'SingleSelect' }] },
    ],
  },
  { name: 'RelatedTypes[]', schemaType: 'MultiSelect' },
  { name: 'SecurityID', schemaType: 'Hidden' },
];

test('createRequestBody leaves out the keys that only FormBuilder uses', () => {
  const values = {
    Root: null, Main: null, Name: 'Plant', SecurityID: 'token', action_save: 1,
  };
  expect(createRequestBody(values, schemaFields)).toEqual({ Name: 'Plant' });
});

test('createRequestBody sends the value of a selected option', () => {
  expect(createRequestBody({ TypeID: { value: 3, label: 'Biology' } }, schemaFields)).toEqual({ TypeID: 3 });
});

test('createRequestBody sends every value of a multi-value field as a plain value', () => {
  const options = [{ value: 3, label: 'Biology' }, { value: 5, label: 'Geology' }];
  expect(createRequestBody({ RelatedTypes: options }, schemaFields)).toEqual({ RelatedTypes: [3, 5] });
  expect(createRequestBody({ 'RelatedTypes[]': [3, 5] }, schemaFields)).toEqual({ RelatedTypes: [3, 5] });
});

test('sortApiErrors gives a field its own message', () => {
  const errors = [{ type: 'error', code: 422, value: '"Name" is required', field: 'Name' }];
  expect(sortApiErrors(errors, ['Name'])).toEqual({
    fieldMessages: { Name: { type: 'error', value: '"Name" is required' } },
    formMessages: [],
  });
});

test('sortApiErrors gives an error without a field to the form', () => {
  const errors = [{ type: 'error', code: 422, value: 'The data is not valid.' }];
  expect(sortApiErrors(errors, ['Name'])).toEqual({ fieldMessages: {}, formMessages: ['The data is not valid.'] });
});
