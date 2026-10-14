/* global test, expect, beforeAll, afterAll, afterEach, beforeEach, document */
import React from 'react';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import { Route, Routes, useLocation } from 'react-router-dom';
import { server, writeRequests } from 'tests/server';
import { renderWithAdmin } from 'tests/renderWithAdmin';
import TypeEditScreen from '../TypeEditScreen';

const LocationDisplay = () => {
  const location = useLocation();
  return <div role="region" aria-label="Current location">{`${location.pathname}${location.search}`}</div>;
};

const renderScreen = (url) => renderWithAdmin(
  <>
    <Routes>
      <Route path="/taxonomy/types/new" element={<TypeEditScreen />} />
      <Route path="/taxonomy/types/:id" element={<TypeEditScreen />} />
      <Route path="/taxonomy/types" element={<h1>Types list</h1>} />
    </Routes>
    <LocationDisplay />
  </>,
  { url }
);

const getToasts = (store) => store.getState().toasts.toasts;

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
beforeEach(() => {
  writeRequests.length = 0;
});
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

test('TypeEditScreen shows the form of the type from the API schema and the breadcrumbs', async () => {
  const { store } = renderScreen('/taxonomy/types/1');
  expect(await screen.findByRole('textbox', { name: 'Name' })).toHaveValue('Biology');
  expect(screen.getByRole('button', { name: 'Save' })).toBeInTheDocument();
  expect(store.getState().breadcrumbs).toEqual([
    { text: 'Taxonomy types', href: '/taxonomy/types' },
    { text: 'Biology', href: '/taxonomy/types/1' },
  ]);
});

test('TypeEditScreen sends the changes as JSON to PATCH with the CSRF token', async () => {
  const user = userEvent.setup();
  renderScreen('/taxonomy/types/1');
  const input = await screen.findByRole('textbox', { name: 'Name' });
  await user.clear(input);
  await user.type(input, 'Science');
  await user.click(screen.getByRole('button', { name: 'Save' }));
  await waitFor(() => expect(writeRequests).toHaveLength(1));
  const [request] = writeRequests;
  expect(request.method).toBe('PATCH');
  expect(request.url.pathname).toBe('/admin/api/v1/taxonomy/types/1');
  expect(request.headers.get('Content-Type')).toBe('application/json');
  expect(request.headers.get('X-SecurityID')).toBe('test-token');
  expect(request.body).toEqual({ Name: 'Science' });
});

test('TypeEditScreen deletes a type and opens the list of types', async () => {
  const user = userEvent.setup();
  const { store } = renderScreen('/taxonomy/types/1');
  await user.click(await screen.findByRole('button', { name: 'Delete type' }));
  await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Delete' }));
  expect(await screen.findByRole('heading', { name: 'Types list' })).toBeInTheDocument();
  expect(writeRequests).toHaveLength(1);
  expect(writeRequests[0].method).toBe('DELETE');
  expect(writeRequests[0].url.pathname).toBe('/admin/api/v1/taxonomy/types/1');
  expect(getToasts(store)[0]).toMatchObject({ type: 'success', text: 'Deleted "Biology"' });
});
