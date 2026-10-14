/* global test, expect, beforeAll, afterAll, afterEach */
import React from 'react';
import { screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import { http, HttpResponse } from 'msw';
import { index } from 'tests/fixtures';
import { server } from 'tests/server';
import { renderWithAdmin } from 'tests/renderWithAdmin';
import SectionNav from '../SectionNav';

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

test('SectionNav links to the terms and types tabs and marks the current one', async () => {
  renderWithAdmin(<SectionNav current="types" />);
  const nav = await screen.findByRole('navigation', { name: 'Taxonomy sections' });
  expect(nav).toBeInTheDocument();
  expect(screen.getByRole('link', { name: 'Terms' })).toHaveAttribute('href', '/taxonomy/terms');
  expect(screen.getByRole('link', { name: 'Terms' })).not.toHaveAttribute('aria-current');
  expect(screen.getByRole('link', { name: 'Types' })).toHaveAttribute('href', '/taxonomy/types');
  expect(screen.getByRole('link', { name: 'Types' })).toHaveAttribute('aria-current', 'page');
});

test('SectionNav hides a tab when the API root says the member cannot view the collection', async () => {
  const hidden = {
    ...index.body,
    collections: {
      ...index.body.collections,
      types: { ...index.body.collections.types, can: { view: false, create: false } },
    },
  };
  server.use(http.get(/\/admin\/api\/v1\/taxonomy$/, () => HttpResponse.json(hidden)));
  renderWithAdmin(<SectionNav current="terms" />);
  expect(await screen.findByRole('link', { name: 'Terms' })).toBeInTheDocument();
  expect(screen.queryByRole('link', { name: 'Types' })).not.toBeInTheDocument();
});
