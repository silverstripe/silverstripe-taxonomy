/* global test, expect, beforeAll, afterAll, afterEach, beforeEach, document */
import React from 'react';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import { Route, Routes, useLocation } from 'react-router-dom';
import { http } from 'msw';
import {
  termInvalid,
  termSchemaViewOnly,
  termUnknownField,
  termViewOnly,
} from 'tests/fixtures';
import { API_URL, respondWithFixture, server, writeRequests } from 'tests/server';
import { renderWithAdmin } from 'tests/renderWithAdmin';
import TermEditScreen from '../TermEditScreen';

const LocationDisplay = () => {
  const location = useLocation();
  return <div role="region" aria-label="Current location">{`${location.pathname}${location.search}`}</div>;
};

const renderScreen = (url, state = null) => renderWithAdmin(
  <>
    <Routes>
      <Route path="/taxonomy/terms/new" element={<TermEditScreen />} />
      <Route path="/taxonomy/terms/:id" element={<TermEditScreen />} />
      <Route path="/taxonomy/terms" element={<h1>Terms list</h1>} />
    </Routes>
    <LocationDisplay />
  </>,
  { url, state }
);

const getToasts = (store) => store.getState().toasts.toasts;

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
beforeEach(() => {
  writeRequests.length = 0;
});
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

test('TermEditScreen shows the breadcrumbs of a child term from its ancestors', async () => {
  const { store } = renderScreen('/taxonomy/terms/4');
  await screen.findByRole('textbox', { name: 'Name' });
  expect(store.getState().breadcrumbs).toEqual([
    { text: 'Taxonomy terms', href: '/taxonomy/terms' },
    { text: 'Plant', href: '/taxonomy/terms/1' },
    { text: 'Vegetable', href: '/taxonomy/terms/3' },
    { text: 'Carrot', href: '/taxonomy/terms/4' },
  ]);
  expect(screen.getByRole('link', { name: 'Vegetable' })).toHaveAttribute('href', '/taxonomy/terms/3');
  expect(screen.getByRole('heading', { name: 'Carrot' })).toBeInTheDocument();
});

test('TermEditScreen sends the changes as JSON to PATCH with the CSRF token', async () => {
  const user = userEvent.setup();
  renderScreen('/taxonomy/terms/1');
  const input = await screen.findByRole('textbox', { name: 'Name' });
  await user.clear(input);
  await user.type(input, 'Flora');
  await user.click(screen.getByRole('button', { name: 'Save' }));
  await waitFor(() => expect(writeRequests).toHaveLength(1));
  const [request] = writeRequests;
  expect(request.method).toBe('PATCH');
  expect(request.url.pathname).toBe('/admin/api/v1/taxonomy/terms/1');
  expect(request.headers.get('Content-Type')).toBe('application/json');
  expect(request.headers.get('X-SecurityID')).toBe('test-token');
  expect(request.body).toEqual({ Name: 'Flora', TypeID: 1 });
});

test('TermEditScreen shows the error of a field that the API refused', async () => {
  const user = userEvent.setup();
  server.use(http.patch(`*/${API_URL}/terms/1`, () => respondWithFixture(termInvalid)));
  const { store } = renderScreen('/taxonomy/terms/1');
  await user.type(await screen.findByRole('textbox', { name: 'Name' }), 'x');
  await user.click(screen.getByRole('button', { name: 'Save' }));
  expect(await screen.findByText('"Name" is required')).toBeInTheDocument();
  expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  expect(getToasts(store)).toHaveLength(0);
});

test('TermEditScreen shows an error for a field that is not in the form as a message for the form', async () => {
  const user = userEvent.setup();
  server.use(http.patch(`*/${API_URL}/terms/1`, () => respondWithFixture(termUnknownField)));
  renderScreen('/taxonomy/terms/1');
  await user.type(await screen.findByRole('textbox', { name: 'Name' }), 'x');
  await user.click(screen.getByRole('button', { name: 'Save' }));
  expect(await screen.findByRole('alert')).toHaveTextContent('The "ParentID" field does not exist or cannot be changed.');
});

test('TermEditScreen creates a top-level term and opens its edit screen', async () => {
  const user = userEvent.setup();
  const { store, container } = renderScreen('/taxonomy/terms/new');
  await user.type(await screen.findByRole('textbox', { name: 'Name' }), 'Fungus');
  await user.click(screen.getByRole('button', { name: 'Save' }));
  await waitFor(() => expect(screen.getByRole('region', { name: 'Current location' })).toHaveTextContent('/taxonomy/terms/6'));
  const [request] = writeRequests;
  expect(request.method).toBe('POST');
  expect(request.url.pathname).toBe('/admin/api/v1/taxonomy/terms');
  expect(request.body).toEqual({ Name: 'Fungus', TypeID: null });
  expect(getToasts(store)[0]).toMatchObject({ type: 'success', text: 'Created "Fungus"' });
  await screen.findByRole('textbox', { name: 'Name' });
  expect(container.querySelector('.taxonomy-term__header')).toHaveFocus();
});

test('TermEditScreen creates a child term under the parent from the address', async () => {
  const user = userEvent.setup();
  const { store } = renderScreen('/taxonomy/terms/new?parentID=1');
  await user.type(await screen.findByRole('textbox', { name: 'Name' }), 'Fern');
  expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
  expect(store.getState().breadcrumbs.map((crumb) => crumb.text)).toEqual(['Taxonomy terms', 'Plant', 'Add term']);
  await user.click(screen.getByRole('button', { name: 'Save' }));
  await waitFor(() => expect(writeRequests).toHaveLength(1));
  const [request] = writeRequests;
  expect(request.url.pathname).toBe('/admin/api/v1/taxonomy/terms/1/children');
  expect(request.body).toEqual({ Name: 'Fern' });
});

test('TermEditScreen deletes a child term and opens its parent', async () => {
  const user = userEvent.setup();
  const { store } = renderScreen('/taxonomy/terms/4');
  await user.click(await screen.findByRole('button', { name: 'Delete term' }));
  await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Delete' }));
  await waitFor(() => expect(screen.getByRole('region', { name: 'Current location' })).toHaveTextContent('/taxonomy/terms/3'));
  expect(writeRequests).toHaveLength(1);
  expect(writeRequests[0].method).toBe('DELETE');
  expect(writeRequests[0].url.pathname).toBe('/admin/api/v1/taxonomy/terms/4');
  expect(writeRequests[0].headers.get('X-SecurityID')).toBe('test-token');
  expect(getToasts(store)[0]).toMatchObject({ type: 'success', text: 'Deleted "Carrot"' });
});

test('TermEditScreen offers no editing to a member who can only view the term', async () => {
  server.use(
    http.get(`*/${API_URL}/terms/1`, () => respondWithFixture(termViewOnly)),
    http.get(`*/${API_URL}/terms/1/schema`, () => respondWithFixture(termSchemaViewOnly))
  );
  renderScreen('/taxonomy/terms/1');
  expect(await screen.findByRole('textbox', { name: 'Name' })).toHaveAttribute('readonly');
  expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument();
  expect(screen.queryByRole('button', { name: 'Delete term' })).not.toBeInTheDocument();
  expect(screen.queryByRole('link', { name: 'Add child term' })).not.toBeInTheDocument();
});

test('TermEditScreen shows the message from the API when the term does not exist', async () => {
  renderScreen('/taxonomy/terms/99');
  expect(await screen.findByRole('alert')).toHaveTextContent('The requested resource does not exist.');
});
