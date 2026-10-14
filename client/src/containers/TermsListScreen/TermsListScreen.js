import React, { useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { Link, useLocation, useSearchParams } from 'react-router-dom';
import i18n from 'i18n';
import Loading from 'components/Loading/Loading';
import Paginator from 'components/Paginator/Paginator';
import getJsonErrorMessage from 'lib/getJsonErrorMessage';
import SectionNav, { SECTION_TERMS } from 'components/SectionNav/SectionNav';
import TermsSearchForm from 'components/TermsSearchForm/TermsSearchForm';
import TermsTable from 'components/TermsTable/TermsTable';
import { getClientPath } from 'lib/clientPaths';
import { readTerms } from 'lib/taxonomyApi';
import useTermMove from 'lib/useTermMove';
import { DEFAULT_PAGE_SIZE } from 'constants/section';

const STATUS_LOADING = 'loading';
const STATUS_LOADED = 'loaded';
const STATUS_ERROR = 'error';

const parsePage = (value) => {
  const page = Number(value);
  return Number.isInteger(page) && page > 0 ? page : 1;
};

const TermsListScreen = ({ pageSize = DEFAULT_PAGE_SIZE }) => {
  const [searchParams, setSearchParams] = useSearchParams();
  const query = searchParams.get('q') ?? '';
  const page = parsePage(searchParams.get('page'));
  const offset = (page - 1) * pageSize;
  const [state, setState] = useState({ status: STATUS_LOADING });
  const [attempt, setAttempt] = useState(0);
  const headingRef = useRef(null);
  const location = useLocation();
  const shouldFocusHeading = location.state?.shouldFocusHeading === true;

  useEffect(() => {
    let isCurrent = true;
    setState({ status: STATUS_LOADING });
    readTerms({ query, limit: pageSize, offset })
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
  }, [query, page, pageSize, attempt]);

  useEffect(() => {
    if (shouldFocusHeading) {
      headingRef.current.focus();
    }
  }, [shouldFocusHeading]);

  const handleMove = useTermMove({
    items: state.list?.items ?? [],
    setItems: (items) => setState((current) => ({ ...current, list: { ...current.list, items } })),
    offset,
    reload: () => setAttempt((current) => current + 1),
  });

  const showPage = (newPage, newQuery = query, options = {}) => {
    const params = {};
    if (newQuery !== '') {
      params.q = newQuery;
    }
    if (newPage > 1) {
      params.page = String(newPage);
    }
    setSearchParams(params, options);
  };

  useEffect(() => {
    if (state.status === STATUS_LOADED && state.list.items.length === 0 && state.list.total > 0 && page > 1) {
      showPage(Math.ceil(state.list.total / pageSize), query, { replace: true });
    }
  });

  const renderResults = () => {
    if (state.status === STATUS_LOADING) {
      return (
        <div role="status">
          <Loading />
          <span className="visually-hidden">{i18n._t('Taxonomy.LOADING_TERMS', 'Loading terms')}</span>
        </div>
      );
    }
    if (state.status === STATUS_ERROR) {
      return (
        <div className="alert alert-danger" role="alert">
          <p>{i18n._t('Taxonomy.LOAD_TERMS_ERROR', 'Failed to load terms')}</p>
          <p>{state.message}</p>
          <button type="button" className="btn btn-secondary" onClick={() => setAttempt(attempt + 1)}>
            {i18n._t('Taxonomy.TRY_AGAIN', 'Try again')}
          </button>
        </div>
      );
    }
    const { items, total } = state.list;
    if (items.length === 0) {
      return <p role="status">{i18n._t('Taxonomy.NO_TERMS', 'No terms found')}</p>;
    }
    const start = offset + 1;
    const summary = i18n.inject(
      i18n._t('Taxonomy.SHOWING_RANGE', 'Showing {start} to {end} of {total}'),
      { start, end: start + items.length - 1, total }
    );
    return (
      <>
        <p role="status">{summary}</p>
        <TermsTable
          terms={items}
          caption={i18n._t('Taxonomy.TERMS_TABLE_CAPTION', 'Top-level taxonomy terms')}
          total={total}
          offset={offset}
          isReorderable={query === ''}
          onMove={handleMove}
        />
        {total > pageSize && (
          <Paginator
            title={i18n._t('Taxonomy.TERMS_TITLE', 'Taxonomy terms')}
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
      <SectionNav current={SECTION_TERMS} />
      <div className="taxonomy-terms__header">
        <h1 className="taxonomy-terms__title" tabIndex={-1} ref={headingRef}>{i18n._t('Taxonomy.TERMS_TITLE', 'Taxonomy terms')}</h1>
        {canCreate && (
          <Link className="btn btn-primary font-icon-plus-thin" to={getClientPath('terms', 'new')}>
            {i18n._t('Taxonomy.ADD_TERM', 'Add term')}
          </Link>
        )}
      </div>
      <TermsSearchForm key={query} initialQuery={query} onSearch={(newQuery) => showPage(1, newQuery)} />
      {renderResults()}
    </div>
  );
};

TermsListScreen.propTypes = {
  pageSize: PropTypes.number,
};

export default TermsListScreen;
