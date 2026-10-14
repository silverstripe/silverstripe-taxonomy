/* global test, expect, jest, document */
import React from 'react';
import {
  render,
  screen,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import DeleteTermModal from '../DeleteTermModal';

const term = { Name: 'Plant', meta: { childCount: 0 } };

const renderModal = (props = {}) => render(
  <DeleteTermModal term={term} onConfirm={jest.fn()} onCancel={jest.fn()} {...props} />
);

test('DeleteTermModal warns about nested terms when the term has child terms', () => {
  renderModal({ term: { ...term, meta: { childCount: 2 } } });
  expect(screen.getByText(/This also deletes all the terms nested under it/)).toBeInTheDocument();
});

test('DeleteTermModal calls onConfirm and onCancel from its buttons', async () => {
  const user = userEvent.setup();
  const onConfirm = jest.fn();
  const onCancel = jest.fn();
  renderModal({ onConfirm, onCancel });
  await user.click(screen.getByRole('button', { name: 'Delete' }));
  expect(onConfirm).toHaveBeenCalledTimes(1);
  await user.click(screen.getByRole('button', { name: 'Cancel' }));
  expect(onCancel).toHaveBeenCalledTimes(1);
});
