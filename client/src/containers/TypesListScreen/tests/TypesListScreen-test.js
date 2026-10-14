/* global test, expect, beforeAll, afterAll, afterEach, beforeEach */
import React from 'react';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import { http } from 'msw';
import { error403 } from 'tests/fixtures';
import {
  API_URL,
  requestedUrls,
  respondWithFixture,
  server,
  writeRequests,
} from 'tests/server';
import { renderWithAdmin } from 'tests/renderWithAdmin';
import TypesListScreen from '../TypesListScreen';

const getToasts = (store) => store.getState().toasts.toasts;

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
beforeEach(() => {
  requestedUrls.length = 0;
  writeRequests.length = 0;
});
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

test('TypesListScreen lists the types in a table with headers', async () => {
  renderWithAdmin(<TypesListScreen />, { url: '/taxonomy/types' });
  expect(screen.getByRole('status')).toHaveTextContent('Loading types');
  const table = await screen.findByRole('table', { name: 'Taxonomy types' });
  expect(within(table).getAllByRole('columnheader').map((header) => header.textContent)).toEqual(['Name', 'Actions']);
  expect(within(table).getByRole('link', { name: 'Biology' })).toHaveAttribute('href', '/taxonomy/types/1');
  expect(screen.getByRole('heading', { name: 'Taxonomy types' })).toBeInTheDocument();
  expect(screen.getByText(/^Showing/)).toHaveTextContent('Showing 1 to 1 of 1');
});

test('TypesListScreen shows the message from the API when the member may not view types', async () => {
  server.use(http.get(`*/${API_URL}/types`, () => respondWithFixture(error403)));
  renderWithAdmin(<TypesListScreen />, { url: '/taxonomy/types' });
  const alert = await screen.findByRole('alert');
  expect(alert).toHaveTextContent('Failed to load types');
  expect(alert).toHaveTextContent('You do not have permission to do this.');
  expect(screen.queryByRole('link', { name: 'Add type' })).not.toBeInTheDocument();
});

test('TypesListScreen deletes a type, shows a toast and moves the focus to the heading', async () => {
  const user = userEvent.setup();
  const { store } = renderWithAdmin(<TypesListScreen />, { url: '/taxonomy/types' });
  await user.click(await screen.findByRole('button', { name: 'Delete Biology' }));
  await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Delete' }));
  await waitFor(() => expect(writeRequests).toHaveLength(1));
  expect(writeRequests[0].method).toBe('DELETE');
  expect(writeRequests[0].url.pathname).toBe('/admin/api/v1/taxonomy/types/1');
  expect(writeRequests[0].headers.get('X-SecurityID')).toBe('test-token');
  await waitFor(() => expect(getToasts(store)).toHaveLength(1));
  expect(getToasts(store)[0]).toMatchObject({ type: 'success', text: 'Deleted "Biology"' });
  await waitFor(() => expect(screen.getByRole('heading', { name: 'Taxonomy types' })).toHaveFocus());
});
