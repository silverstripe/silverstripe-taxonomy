/* global test, expect, beforeAll, afterAll, afterEach, beforeEach */
import React from 'react';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import { useLocation } from 'react-router-dom';
import { http } from 'msw';
import {
  error403,
  error403Update,
} from 'tests/fixtures';
import {
  API_URL,
  requestedUrls,
  respondWithFixture,
  server,
  writeRequests,
} from 'tests/server';
import { renderWithAdmin } from 'tests/renderWithAdmin';
import TermsListScreen from '../TermsListScreen';

const LocationDisplay = () => {
  const location = useLocation();
  return <div role="region" aria-label="Current location">{`${location.pathname}${location.search}`}</div>;
};

const renderScreen = (url = '/taxonomy', props = {}) => renderWithAdmin(
  <>
    <TermsListScreen {...props} />
    <LocationDisplay />
  </>,
  { url }
);

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
beforeEach(() => {
  requestedUrls.length = 0;
  writeRequests.length = 0;
});
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

test('TermsListScreen lists the terms in a table with headers', async () => {
  renderScreen();
  const table = await screen.findByRole('table', { name: 'Top-level taxonomy terms' });
  expect(within(table).getAllByRole('columnheader').map((header) => header.textContent))
    .toEqual(['Order', 'Name', 'Type', 'Child terms', 'Actions']);
  const [, animalRow, plantRow] = within(table).getAllByRole('row');
  expect(within(animalRow).getByRole('rowheader', { name: 'Animal' })).toBeInTheDocument();
  expect(within(plantRow).getByRole('link', { name: 'Plant' })).toHaveAttribute('href', '/taxonomy/terms/1');
  expect(within(plantRow).getByRole('cell', { name: 'Biology' })).toBeInTheDocument();
  expect(within(plantRow).getByRole('cell', { name: '2' })).toBeInTheDocument();
  expect(screen.getByText(/^Showing/)).toHaveTextContent('Showing 1 to 2 of 2');
});

test('TermsListScreen searches by name', async () => {
  const user = userEvent.setup();
  renderScreen();
  await screen.findByRole('table');
  await user.type(screen.getByRole('searchbox', { name: 'Search terms by name' }), ' Pla ');
  await user.click(screen.getByRole('button', { name: 'Search' }));
  expect(await screen.findByRole('rowheader', { name: 'Plant' })).toBeInTheDocument();
  expect(screen.queryByRole('rowheader', { name: 'Animal' })).not.toBeInTheDocument();
  expect(requestedUrls[requestedUrls.length - 1].searchParams.get('q')).toBe('Pla');
  expect(screen.getByRole('region', { name: 'Current location' })).toHaveTextContent('/taxonomy?q=Pla');
});

test('TermsListScreen pages through the terms', async () => {
  const user = userEvent.setup();
  renderScreen('/taxonomy', { pageSize: 1 });
  expect(await screen.findByRole('rowheader', { name: 'Animal' })).toBeInTheDocument();
  expect(screen.getByText(/^Showing/)).toHaveTextContent('Showing 1 to 1 of 2');
  expect(screen.queryByRole('button', { name: 'Previous' })).not.toBeInTheDocument();
  await user.click(screen.getByRole('button', { name: 'Next' }));
  expect(await screen.findByRole('rowheader', { name: 'Plant' })).toBeInTheDocument();
  expect(screen.queryByRole('rowheader', { name: 'Animal' })).not.toBeInTheDocument();
  expect(screen.getByText(/^Showing/)).toHaveTextContent('Showing 2 to 2 of 2');
  expect(requestedUrls[requestedUrls.length - 1].searchParams.get('offset')).toBe('1');
  expect(screen.getByRole('region', { name: 'Current location' })).toHaveTextContent('/taxonomy?page=2');
  await user.click(screen.getByRole('button', { name: 'Previous' }));
  expect(await screen.findByRole('rowheader', { name: 'Animal' })).toBeInTheDocument();
  expect(screen.getByRole('region', { name: 'Current location' })).toHaveTextContent(/^\/taxonomy$/);
});

test('TermsListScreen shows the message from the API when the member has no access', async () => {
  server.use(http.get(`*/${API_URL}/terms`, () => respondWithFixture(error403)));
  renderScreen();
  const alert = await screen.findByRole('alert');
  expect(alert).toHaveTextContent('Failed to load terms');
  expect(alert).toHaveTextContent('You do not have permission to do this.');
  expect(screen.queryByRole('link', { name: 'Add term' })).not.toBeInTheDocument();
});

const getRowNames = () => within(screen.getByRole('table')).getAllByRole('rowheader').map((header) => header.textContent);

test('TermsListScreen moves a term to the position that the API is told about', async () => {
  const user = userEvent.setup();
  renderScreen();
  await screen.findByRole('table');
  await user.click(screen.getByRole('button', { name: 'Move down Animal' }));
  await screen.findByText('Moved Animal to position 2 of 2');
  expect(getRowNames()).toEqual(['Plant', 'Animal']);
  expect(writeRequests).toHaveLength(1);
  const [request] = writeRequests;
  expect(request.method).toBe('PATCH');
  expect(request.url.pathname).toBe('/admin/api/v1/taxonomy/terms/2');
  expect(request.body).toEqual({ Sort: 2 });
  expect(request.headers.get('X-SecurityID')).toBe('test-token');
  expect(request.headers.get('Content-Type')).toBe('application/json');
});

test('TermsListScreen puts the old order back and shows a toast when a move fails', async () => {
  const user = userEvent.setup();
  server.use(http.patch(`*/${API_URL}/terms/:id`, () => respondWithFixture(error403Update)));
  const { store } = renderScreen();
  await screen.findByRole('table');
  await user.click(screen.getByRole('button', { name: 'Move down Animal' }));
  await waitFor(() => expect(store.getState().toasts.toasts).toHaveLength(1));
  expect(store.getState().toasts.toasts[0]).toMatchObject({
    type: 'error',
    text: 'Could not move "Animal". You do not have permission to do this.',
  });
  expect(getRowNames()).toEqual(['Animal', 'Plant']);
  expect(screen.queryByText(/^Moved/)).not.toBeInTheDocument();
});
