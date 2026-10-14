import React, { useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { Link, useLocation, useSearchParams } from 'react-router-dom';
import { useDispatch } from 'react-redux';
import i18n from 'i18n';
import Loading from 'components/Loading/Loading';
import Paginator from 'components/Paginator/Paginator';
import getJsonErrorMessage from 'lib/getJsonErrorMessage';
import { success as createSuccessToast, error as createErrorToast } from 'state/toasts/ToastsActions';
import DeleteTermModal from 'components/DeleteTermModal/DeleteTermModal';
import SectionNav, { SECTION_TYPES } from 'components/SectionNav/SectionNav';
import TypesTable from 'components/TypesTable/TypesTable';
import { getClientPath } from 'lib/clientPaths';
import { deleteType, readTypes } from 'lib/taxonomyApi';
import { DEFAULT_PAGE_SIZE } from 'constants/section';

const STATUS_LOADING = 'loading';
const STATUS_LOADED = 'loaded';
const STATUS_ERROR = 'error';

const parsePage = (value) => {
  const page = Number(value);
  return Number.isInteger(page) && page > 0 ? page : 1;
};

const TypesListScreen = ({ pageSize = DEFAULT_PAGE_SIZE }) => {
  const dispatch = useDispatch();
  const [searchParams, setSearchParams] = useSearchParams();
  const page = parsePage(searchParams.get('page'));
  const offset = (page - 1) * pageSize;
  const [state, setState] = useState({ status: STATUS_LOADING });
  const [attempt, setAttempt] = useState(0);
  const [typeToDelete, setTypeToDelete] = useState(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const headingRef = useRef(null);
  const shouldFocusAfterModal = useRef(false);
  const location = useLocation();
  const shouldFocusHeading = location.state?.shouldFocusHeading === true;

  useEffect(() => {
    let isCurrent = true;
    setState({ status: STATUS_LOADING });
    readTypes({ limit: pageSize, offset })
      .then((list) => {
        if (isCurrent) {
          setState({ status: STATUS_LOADED, list });
        }
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
  }, [page, pageSize, attempt]);

  useEffect(() => {
    if (shouldFocusHeading) {
      headingRef.current.focus();
    }
  }, [shouldFocusHeading]);

  const showPage = (newPage, options = {}) => {
    setSearchParams(newPage > 1 ? { page: String(newPage) } : {}, options);
  };

  useEffect(() => {
    if (state.status === STATUS_LOADED && state.list.items.length === 0 && state.list.total > 0 && page > 1) {
      showPage(Math.ceil(state.list.total / pageSize), { replace: true });
    }
  });

  const handleModalExited = () => {
    if (shouldFocusAfterModal.current) {
      shouldFocusAfterModal.current = false;
      // reactstrap calls onClosed before it removes the focus trap, which would pull the focus back into the modal
      setTimeout(() => headingRef.current.focus(), 0);
    }
  };

  const handleConfirmDelete = async () => {
    setIsDeleting(true);
    try {
      await deleteType(typeToDelete.ID);
      dispatch(createSuccessToast(i18n.inject(
        i18n._t('Taxonomy.DELETED_TYPE', 'Deleted "{name}"'),
        { name: typeToDelete.Name }
      )));
      shouldFocusAfterModal.current = true;
      setAttempt((current) => current + 1);
    } catch (error) {
      const message = await getJsonErrorMessage(error);
      dispatch(createErrorToast(i18n.inject(
        i18n._t('Taxonomy.DELETE_ERROR', 'Could not delete "{name}". {message}'),
        { name: typeToDelete.Name, message }
      )));
    }
    setIsDeleting(false);
    setTypeToDelete(null);
  };

  const renderResults = () => {
    if (state.status === STATUS_LOADING) {
      return (
        <div role="status">
          <Loading />
          <span className="visually-hidden">{i18n._t('Taxonomy.LOADING_TYPES', 'Loading types')}</span>
        </div>
      );
    }
    if (state.status === STATUS_ERROR) {
      return (
        <div className="alert alert-danger" role="alert">
          <p>{i18n._t('Taxonomy.LOAD_TYPES_ERROR', 'Failed to load types')}</p>
          <p>{state.message}</p>
          <button type="button" className="btn btn-secondary" onClick={() => setAttempt(attempt + 1)}>
            {i18n._t('Taxonomy.TRY_AGAIN', 'Try again')}
          </button>
        </div>
      );
    }
    const { items, total } = state.list;
    if (items.length === 0) {
      return <p role="status">{i18n._t('Taxonomy.NO_TYPES', 'No types found')}</p>;
    }
    const start = offset + 1;
    const summary = i18n.inject(
      i18n._t('Taxonomy.SHOWING_RANGE', 'Showing {start} to {end} of {total}'),
      { start, end: start + items.length - 1, total }
    );
    return (
      <>
        <p role="status">{summary}</p>
        <TypesTable
          types={items}
          caption={i18n._t('Taxonomy.TYPES_TABLE_CAPTION', 'Taxonomy types')}
          onDelete={setTypeToDelete}
        />
        {total > pageSize && (
          <Paginator
            title={i18n._t('Taxonomy.TYPES_TITLE', 'Taxonomy types')}
            totalItems={total}
            maxItemsPerPage={pageSize}
            currentPage={page}
            onChangePage={showPage}
          />
        )}
      </>
    );
  };

  const canCreate = state.status === STATUS_LOADED && state.list.can.create;

  return (
    <div className="taxonomy-terms">
      <SectionNav current={SECTION_TYPES} />
      <div className="taxonomy-terms__header">
        <h1 className="taxonomy-terms__title" tabIndex={-1} ref={headingRef}>{i18n._t('Taxonomy.TYPES_TITLE', 'Taxonomy types')}</h1>
        {canCreate && (
          <Link className="btn btn-primary font-icon-plus-thin" to={getClientPath('types', 'new')}>
            {i18n._t('Taxonomy.ADD_TYPE', 'Add type')}
          </Link>
        )}
      </div>
      {renderResults()}
      <DeleteTermModal
        term={typeToDelete}
        title={i18n._t('Taxonomy.DELETE_TYPE_TITLE', 'Delete type')}
        isDeleting={isDeleting}
        onConfirm={handleConfirmDelete}
        onCancel={() => setTypeToDelete(null)}
        onExited={handleModalExited}
      />
    </div>
  );
};

TypesListScreen.propTypes = {
  pageSize: PropTypes.number,
};

export default TypesListScreen;
