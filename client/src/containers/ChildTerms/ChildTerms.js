import React, { useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { Link } from 'react-router-dom';
import { useDispatch } from 'react-redux';
import i18n from 'i18n';
import Loading from 'components/Loading/Loading';
import Paginator from 'components/Paginator/Paginator';
import getJsonErrorMessage from 'lib/getJsonErrorMessage';
import { success as createSuccessToast, error as createErrorToast } from 'state/toasts/ToastsActions';
import DeleteTermModal from 'components/DeleteTermModal/DeleteTermModal';
import TermsTable from 'components/TermsTable/TermsTable';
import { getClientPath } from 'lib/clientPaths';
import { deleteTerm, readChildTerms } from 'lib/taxonomyApi';
import useTermMove from 'lib/useTermMove';
import { DEFAULT_PAGE_SIZE } from 'constants/section';

const STATUS_LOADING = 'loading';
const STATUS_LOADED = 'loaded';
const STATUS_ERROR = 'error';

/**
 * The child terms of a term, with add, delete and reorder. Shown below the form of the parent term.
 */
const ChildTerms = ({ parent, pageSize = DEFAULT_PAGE_SIZE }) => {
  const dispatch = useDispatch();
  const [page, setPage] = useState(1);
  const [state, setState] = useState({ status: STATUS_LOADING });
  const [attempt, setAttempt] = useState(0);
  const [termToDelete, setTermToDelete] = useState(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const headingRef = useRef(null);
  const shouldFocusHeading = useRef(false);
  const offset = (page - 1) * pageSize;

  useEffect(() => {
    let isCurrent = true;
    setState({ status: STATUS_LOADING });
    readChildTerms(parent.ID, { limit: pageSize, offset })
      .then((list) => {
        if (!isCurrent) {
          return;
        }
        if (list.items.length === 0 && list.total > 0 && page > 1) {
          setPage(Math.ceil(list.total / pageSize));
          return;
        }
        setState({ status: STATUS_LOADED, list });
      })
      .catch(async (error) => {
        const message = await getJsonErrorMessage(error);
        if (isCurrent) {
          setState({ status: STATUS_ERROR, message });
        }
      });
    return () => {
      isCurrent = false;
    };
  }, [parent.ID, page, pageSize, attempt]);

  const handleModalExited = () => {
    if (shouldFocusHeading.current) {
      shouldFocusHeading.current = false;
      // reactstrap calls onClosed before it removes the focus trap, which would pull the focus back into the modal
      setTimeout(() => headingRef.current.focus(), 0);
    }
  };

  const handleMove = useTermMove({
    items: state.list?.items ?? [],
    setItems: (items) => setState((current) => ({ ...current, list: { ...current.list, items } })),
    offset,
    reload: () => setAttempt((current) => current + 1),
  });

  const handleConfirmDelete = async () => {
    setIsDeleting(true);
    try {
      await deleteTerm(termToDelete.ID);
      dispatch(createSuccessToast(i18n.inject(
        i18n._t('Taxonomy.DELETED_TERM', 'Deleted "{name}"'),
        { name: termToDelete.Name }
      )));
      shouldFocusHeading.current = true;
      setAttempt((current) => current + 1);
    } catch (error) {
      const message = await getJsonErrorMessage(error);
      dispatch(createErrorToast(i18n.inject(
        i18n._t('Taxonomy.DELETE_ERROR', 'Could not delete "{name}". {message}'),
        { name: termToDelete.Name, message }
      )));
    }
    setIsDeleting(false);
    setTermToDelete(null);
  };

  const renderResults = () => {
    if (state.status === STATUS_LOADING) {
      return (
        <div role="status">
          <Loading />
          <span className="visually-hidden">{i18n._t('Taxonomy.LOADING_CHILD_TERMS', 'Loading child terms')}</span>
        </div>
      );
    }
    if (state.status === STATUS_ERROR) {
      return (
        <div className="alert alert-danger" role="alert">
          <p>{i18n._t('Taxonomy.LOAD_CHILD_TERMS_ERROR', 'Failed to load child terms')}</p>
          <p>{state.message}</p>
          <button type="button" className="btn btn-secondary" onClick={() => setAttempt(attempt + 1)}>
            {i18n._t('Taxonomy.TRY_AGAIN', 'Try again')}
          </button>
        </div>
      );
    }
    const { items, total } = state.list;
    if (items.length === 0) {
      return <p role="status">{i18n._t('Taxonomy.NO_CHILD_TERMS', 'This term has no child terms')}</p>;
    }
    return (
      <>
        <TermsTable
          terms={items}
          caption={i18n.inject(
            i18n._t('Taxonomy.CHILD_TERMS_TABLE_CAPTION', 'Child terms of {name}'),
            { name: parent.Name }
          )}
          total={total}
          offset={offset}
          showType={false}
          isReorderable={parent.meta.can.edit}
          onMove={handleMove}
          onDelete={setTermToDelete}
        />
        {total > pageSize && (
          <Paginator
            title={i18n._t('Taxonomy.CHILD_TERMS_TITLE', 'Child terms')}
            totalItems={total}
            maxItemsPerPage={pageSize}
            currentPage={page}
            onChangePage={setPage}
          />
        )}
      </>
    );
  };

  return (
    <section className="taxonomy-children" aria-labelledby="taxonomy-children-title">
      <div className="taxonomy-children__header">
        <h2 id="taxonomy-children-title" className="taxonomy-children__title" tabIndex={-1} ref={headingRef}>
          {i18n._t('Taxonomy.CHILD_TERMS_TITLE', 'Child terms')}
        </h2>
        {parent.meta.can.createChild && (
          <Link
            className="btn btn-primary font-icon-plus-thin"
            to={`${getClientPath('terms', 'new')}?parentID=${parent.ID}`}
          >
            {i18n._t('Taxonomy.ADD_CHILD_TERM', 'Add child term')}
          </Link>
        )}
      </div>
      {renderResults()}
      <DeleteTermModal
        term={termToDelete}
        isDeleting={isDeleting}
        onConfirm={handleConfirmDelete}
        onCancel={() => setTermToDelete(null)}
        onExited={handleModalExited}
      />
    </section>
  );
};

ChildTerms.propTypes = {
  parent: PropTypes.shape({
    ID: PropTypes.number.isRequired,
    Name: PropTypes.string.isRequired,
    meta: PropTypes.shape({
      can: PropTypes.shape({ edit: PropTypes.bool, createChild: PropTypes.bool }),
    }).isRequired,
  }).isRequired,
  pageSize: PropTypes.number,
};

export default ChildTerms;
