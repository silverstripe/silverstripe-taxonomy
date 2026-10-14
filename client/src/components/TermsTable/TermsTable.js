import React, { useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { Link } from 'react-router-dom';
import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { restrictToVerticalAxis } from '@dnd-kit/modifiers';
import { CSS } from '@dnd-kit/utilities';
import i18n from 'i18n';
import { getClientPath } from 'lib/clientPaths';

const termPropType = PropTypes.shape({
  ID: PropTypes.number.isRequired,
  Name: PropTypes.string.isRequired,
  meta: PropTypes.shape({
    typeName: PropTypes.string,
    childCount: PropTypes.number,
    can: PropTypes.shape({ edit: PropTypes.bool, delete: PropTypes.bool }),
  }).isRequired,
});

const ACTION_UP = 'up';
const ACTION_DOWN = 'down';

const getTermName = (terms, id) => terms.find((term) => term.ID === id)?.Name ?? '';

const TermRow = ({
  term,
  position,
  total,
  canReorder,
  hasReorderColumn,
  hasActionsColumn,
  showType,
  onMove,
  onDelete,
  registerButton,
}) => {
  const {
    attributes,
    listeners,
    setNodeRef,
    setActivatorNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: term.ID, disabled: !canReorder });

  return (
    <tr
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={isDragging ? 'taxonomy-table__row taxonomy-table__row--dragging' : 'taxonomy-table__row'}
    >
      {hasReorderColumn && (
        <td className="taxonomy-table__handle">
          {canReorder && (
            <button
              ref={setActivatorNodeRef}
              type="button"
              className="btn btn-link taxonomy-table__drag-handle font-icon-drag-handle"
              {...attributes}
              {...listeners}
            >
              <span className="visually-hidden">
                {i18n.inject(i18n._t('Taxonomy.REORDER_TERM', 'Reorder {name}'), { name: term.Name })}
              </span>
            </button>
          )}
        </td>
      )}
      <th scope="row">
        <Link to={getClientPath('terms', term.ID)}>{term.Name}</Link>
      </th>
      {showType && <td>{term.meta.typeName}</td>}
      <td>{term.meta.childCount}</td>
      {hasActionsColumn && (
        <td className="taxonomy-table__actions">
          {canReorder && (
            <>
              <button
                ref={(button) => registerButton(`${term.ID}:${ACTION_UP}`, button)}
                type="button"
                className="btn btn-secondary btn-sm"
                disabled={position === 1}
                onClick={() => onMove(term, position - 1, ACTION_UP)}
              >
                {i18n._t('Taxonomy.MOVE_UP', 'Move up')}
                <span className="visually-hidden">{` ${term.Name}`}</span>
              </button>
              <button
                ref={(button) => registerButton(`${term.ID}:${ACTION_DOWN}`, button)}
                type="button"
                className="btn btn-secondary btn-sm"
                disabled={position === total}
                onClick={() => onMove(term, position + 1, ACTION_DOWN)}
              >
                {i18n._t('Taxonomy.MOVE_DOWN', 'Move down')}
                <span className="visually-hidden">{` ${term.Name}`}</span>
              </button>
            </>
          )}
          {onDelete && term.meta.can.delete && (
            <button type="button" className="btn btn-outline-danger btn-sm" onClick={() => onDelete(term)}>
              {i18n._t('Taxonomy.DELETE', 'Delete')}
              <span className="visually-hidden">{` ${term.Name}`}</span>
            </button>
          )}
        </td>
      )}
    </tr>
  );
};

TermRow.propTypes = {
  term: termPropType.isRequired,
  position: PropTypes.number.isRequired,
  total: PropTypes.number.isRequired,
  canReorder: PropTypes.bool.isRequired,
  hasReorderColumn: PropTypes.bool.isRequired,
  hasActionsColumn: PropTypes.bool.isRequired,
  showType: PropTypes.bool.isRequired,
  onMove: PropTypes.func.isRequired,
  onDelete: PropTypes.func,
  registerButton: PropTypes.func.isRequired,
};

// A table of terms; a reorderable row reports a move as a 1-based position among all siblings (hence offset and total).
const TermsTable = ({
  terms,
  caption,
  total = terms.length,
  offset = 0,
  showType = true,
  isReorderable = false,
  onMove,
  onDelete,
}) => {
  const [status, setStatus] = useState('');
  const buttons = useRef({});
  const focusTarget = useRef(null);
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  );

  // The buttons of a row that moved are new DOM nodes after React re-orders the rows
  useEffect(() => {
    if (!focusTarget.current) {
      return;
    }
    const { id, action } = focusTarget.current;
    focusTarget.current = null;
    const otherAction = action === ACTION_UP ? ACTION_DOWN : ACTION_UP;
    const button = [buttons.current[`${id}:${action}`], buttons.current[`${id}:${otherAction}`]]
      .find((candidate) => candidate && !candidate.disabled);
    if (button) {
      button.focus();
    }
  });

  const registerButton = (key, button) => {
    if (button) {
      buttons.current[key] = button;
    } else {
      delete buttons.current[key];
    }
  };

  const hasReorderColumn = isReorderable && terms.some((term) => term.meta.can.edit);
  const hasActionsColumn = hasReorderColumn || (!!onDelete && terms.some((term) => term.meta.can.delete));

  const moveTerm = async (term, position, action = null) => {
    if (action) {
      focusTarget.current = { id: term.ID, action };
    }
    const hasMoved = await onMove(term, position);
    if (hasMoved) {
      setStatus(i18n.inject(
        i18n._t('Taxonomy.MOVED_TERM', 'Moved {name} to position {position} of {total}'),
        { name: term.Name, position, total }
      ));
    }
  };

  const handleDragEnd = ({ active, over }) => {
    if (!over || active.id === over.id) {
      return;
    }
    const newIndex = terms.findIndex((term) => term.ID === over.id);
    moveTerm(terms.find((term) => term.ID === active.id), offset + newIndex + 1);
  };

  const getPosition = (id) => i18n.inject(
    i18n._t('Taxonomy.DRAG_POSITION', 'position {position} of {total}'),
    { position: offset + terms.findIndex((term) => term.ID === id) + 1, total }
  );

  const announcements = {
    onDragStart: ({ active }) => i18n.inject(
      i18n._t('Taxonomy.DRAG_PICKED_UP', 'Picked up {name}, {position}'),
      { name: getTermName(terms, active.id), position: getPosition(active.id) }
    ),
    onDragOver: ({ active, over }) => (over && i18n.inject(
      i18n._t('Taxonomy.DRAG_MOVED', '{name} is over {position}'),
      { name: getTermName(terms, active.id), position: getPosition(over.id) }
    )),
    onDragEnd: ({ active, over }) => i18n.inject(
      over
        ? i18n._t('Taxonomy.DRAG_DROPPED', 'Dropped {name} at {position}')
        : i18n._t('Taxonomy.DRAG_DROPPED_BACK', 'Dropped {name} at its original position'),
      { name: getTermName(terms, active.id), position: over ? getPosition(over.id) : '' }
    ),
    onDragCancel: ({ active }) => i18n.inject(
      i18n._t('Taxonomy.DRAG_CANCELLED', 'Cancelled. {name} is back at its original position'),
      { name: getTermName(terms, active.id) }
    ),
  };

  const screenReaderInstructions = {
    draggable: i18n._t(
      'Taxonomy.DRAG_INSTRUCTIONS',
      'Press space or enter to pick up a term. Use the up and down arrow keys to move it, then press space or enter to drop it. Press escape to cancel.'
    ),
  };

  return (
    <>
      <DndContext
        sensors={sensors}
        collisionDetection={closestCenter}
        modifiers={[restrictToVerticalAxis]}
        onDragEnd={handleDragEnd}
        accessibility={{ announcements, screenReaderInstructions }}
      >
        <table className="table table-hover taxonomy-table">
          <caption className="visually-hidden">{caption}</caption>
          <thead>
            <tr>
              {hasReorderColumn && (
                <th scope="col"><span className="visually-hidden">{i18n._t('Taxonomy.COLUMN_ORDER', 'Order')}</span></th>
              )}
              <th scope="col">{i18n._t('Taxonomy.COLUMN_NAME', 'Name')}</th>
              {showType && <th scope="col">{i18n._t('Taxonomy.COLUMN_TYPE', 'Type')}</th>}
              <th scope="col">{i18n._t('Taxonomy.COLUMN_CHILD_TERMS', 'Child terms')}</th>
              {hasActionsColumn && (
                <th scope="col"><span className="visually-hidden">{i18n._t('Taxonomy.COLUMN_ACTIONS', 'Actions')}</span></th>
              )}
            </tr>
          </thead>
          <SortableContext items={terms.map((term) => term.ID)} strategy={verticalListSortingStrategy}>
            <tbody>
              {terms.map((term, index) => (
                <TermRow
                  key={term.ID}
                  term={term}
                  position={offset + index + 1}
                  total={total}
                  canReorder={isReorderable && term.meta.can.edit}
                  hasReorderColumn={hasReorderColumn}
                  hasActionsColumn={hasActionsColumn}
                  showType={showType}
                  onMove={moveTerm}
                  onDelete={onDelete}
                  registerButton={registerButton}
                />
              ))}
            </tbody>
          </SortableContext>
        </table>
      </DndContext>
      <p className="visually-hidden" role="status">{status}</p>
    </>
  );
};

TermsTable.propTypes = {
  terms: PropTypes.arrayOf(termPropType).isRequired,
  caption: PropTypes.string.isRequired,
  total: PropTypes.number,
  offset: PropTypes.number,
  showType: PropTypes.bool,
  isReorderable: PropTypes.bool,
  onMove: PropTypes.func,
  onDelete: PropTypes.func,
};

export default TermsTable;
