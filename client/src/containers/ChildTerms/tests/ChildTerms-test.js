/* global test, expect, beforeAll, afterAll, afterEach, beforeEach */
import React from 'react';
import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import { term } from 'tests/fixtures';
import { requestedUrls, server, writeRequests } from 'tests/server';
import { renderWithAdmin } from 'tests/renderWithAdmin';
import ChildTerms from '../ChildTerms';

const plant = term.body;

const renderChildren = (props = {}) => renderWithAdmin(<ChildTerms parent={plant} {...props} />);

const getRowNames = () => within(screen.getByRole('table')).getAllByRole('rowheader').map((header) => header.textContent);

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
beforeEach(() => {
  requestedUrls.length = 0;
  writeRequests.length = 0;
});
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

test('ChildTerms lists the child terms in a table with headers', async () => {
  renderChildren();
  const table = await screen.findByRole('table', { name: 'Child terms of Plant' });
  expect(within(table).getAllByRole('columnheader').map((header) => header.textContent))
    .toEqual(['Order', 'Name', 'Child terms', 'Actions']);
  expect(getRowNames()).toEqual(['Vegetable', 'Bean']);
  expect(within(table).getByRole('link', { name: 'Vegetable' })).toHaveAttribute('href', '/taxonomy/terms/3');
  expect(screen.getByRole('heading', { name: 'Child terms' })).toBeInTheDocument();
});

test('ChildTerms moves a child term to a position among its siblings', async () => {
  const user = userEvent.setup();
  renderChildren();
  await screen.findByRole('table');
  await user.click(screen.getByRole('button', { name: 'Move down Vegetable' }));
  await screen.findByText('Moved Vegetable to position 2 of 2');
  expect(getRowNames()).toEqual(['Bean', 'Vegetable']);
  expect(writeRequests).toHaveLength(1);
  expect(writeRequests[0].url.pathname).toBe('/admin/api/v1/taxonomy/terms/3');
  expect(writeRequests[0].body).toEqual({ Sort: 2 });
});
