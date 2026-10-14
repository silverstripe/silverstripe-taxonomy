/* global test, expect, jest, afterEach */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import { MemoryRouter } from 'react-router-dom';
import { termsList, termsListViewOnly } from 'tests/fixtures';
import TermsTable from '../TermsTable';

const ROW_HEIGHT = 50;

const { items: terms } = termsList.body;
const { items: viewOnlyTerms } = termsListViewOnly.body;

const renderTable = (props = {}) => render(
  <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
    <TermsTable terms={terms} caption="Test terms" isReorderable onMove={jest.fn()} {...props} />
  </MemoryRouter>
);

const originalGetBoundingClientRect = Element.prototype.getBoundingClientRect;

// jsdom has no layout, so the keyboard sensor of dnd-kit finds no row to move to unless the rows have a position
const mockRowLayout = () => {
  Element.prototype.getBoundingClientRect = function getBoundingClientRect() {
    const rows = Array.from(document.querySelectorAll('tbody tr'));
    const top = Math.max(rows.indexOf(this), 0) * ROW_HEIGHT;
    return {
      top, bottom: top + ROW_HEIGHT, left: 0, right: 500, width: 500, height: ROW_HEIGHT, x: 0, y: top,
    };
  };
};

afterEach(() => {
  Element.prototype.getBoundingClientRect = originalGetBoundingClientRect;
});

test('TermsTable reports the move as a position among all siblings', async () => {
  const user = userEvent.setup();
  const onMove = jest.fn().mockResolvedValue(true);
  renderTable({ onMove, offset: 20, total: 40 });
  await user.click(screen.getByRole('button', { name: 'Move down Animal' }));
  expect(onMove).toHaveBeenCalledWith(terms[0], 22);
  await user.click(screen.getByRole('button', { name: 'Move up Plant' }));
  expect(onMove).toHaveBeenCalledWith(terms[1], 21);
});

test('TermsTable has no reorder controls for terms that the member cannot edit', () => {
  renderTable({ terms: viewOnlyTerms });
  expect(screen.queryByRole('button', { name: /^Move/ })).not.toBeInTheDocument();
  expect(screen.queryByRole('columnheader', { name: 'Order' })).not.toBeInTheDocument();
});

test('TermsTable moves a term with the keyboard', async () => {
  mockRowLayout();
  const user = userEvent.setup();
  const onMove = jest.fn().mockResolvedValue(true);
  renderTable({ onMove });
  screen.getByRole('button', { name: 'Reorder Animal' }).focus();
  await user.keyboard(' ');
  await user.keyboard('{ArrowDown}');
  await user.keyboard(' ');
  expect(onMove).toHaveBeenCalledWith(terms[0], 2);
});
