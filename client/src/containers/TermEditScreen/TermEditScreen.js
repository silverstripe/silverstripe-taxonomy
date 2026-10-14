import React, { useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import i18n from 'i18n';
import Breadcrumb from 'components/Breadcrumb/Breadcrumb';
import FormAlert from 'components/FormAlert/FormAlert';
import Loading from 'components/Loading/Loading';
import Toolbar from 'components/Toolbar/Toolbar';
import FormBuilderLoader from 'containers/FormBuilderLoader/FormBuilderLoader';
import getJsonErrorMessage from 'lib/getJsonErrorMessage';
import { setBreadcrumbs } from 'state/breadcrumbs/BreadcrumbsActions';
import { success as createSuccessToast } from 'state/toasts/ToastsActions';
import ChildTerms from 'containers/ChildTerms/ChildTerms';
import DeleteTermModal from 'components/DeleteTermModal/DeleteTermModal';
import { getClientPath } from 'lib/clientPaths';
import {
  createTerm,
  deleteTerm,
  getNewTermSchemaUrl,
  getTermSchemaUrl,
  readApiErrors,
  readTerm,
  updateTerm,
} from 'lib/taxonomyApi';
import {
  createRequestBody,
  createSubmissionError,
  getFieldNames,
  sortApiErrors,
} from 'lib/termForm';

const STATUS_LOADING = 'loading';
const STATUS_LOADED = 'loaded';
const STATUS_ERROR = 'error';

const toPositiveInteger = (value) => {
  const number = Number(value);
  return Number.isInteger(number) && number > 0 ? number : 0;
};

const getCrumbs = (termID, record) => {
  const crumbs = [{
    text: i18n._t('Taxonomy.TERMS_TITLE', 'Taxonomy terms'),
    href: getClientPath('terms'),
  }];
  if (record) {
    // The ancestors do not include the record itself
    [...record.meta.ancestors, ...(termID ? [] : [record])].forEach(({ ID, Name }) => {
      crumbs.push({ text: Name, href: getClientPath('terms', ID) });
    });
  }
  if (termID) {
    crumbs.push({ text: record.Name, href: getClientPath('terms', termID) });
  } else {
    crumbs.push({ text: i18n._t('Taxonomy.ADD_TERM', 'Add term'), href: getClientPath('terms', 'new') });
  }
  return crumbs;
};

/**
 * Edits a term (termID is set), or creates a term (termID is 0), as a child of parentID when that is set.
 */
const TermEditor = ({ termID, parentID }) => {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const location = useLocation();
  const headerRef = useRef(null);
  const [state, setState] = useState({ status: STATUS_LOADING });
  const [reloadCount, setReloadCount] = useState(0);
  const [formMessages, setFormMessages] = useState([]);
  const [schemaError, setSchemaError] = useState(null);
  const [isConfirmingDelete, setIsConfirmingDelete] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);
  const shouldFocusHeader = location.state?.shouldFocusHeading === true;
  const schemaUrl = termID ? getTermSchemaUrl(termID) : getNewTermSchemaUrl(parentID);
  const schema = useSelector((reduxState) => reduxState.form.formSchemas[schemaUrl]);
  // A new term needs its parent for the breadcrumbs, and an existing term is the record itself
  const recordID = termID || parentID;

  useEffect(() => {
    if (!recordID) {
      setState({ status: STATUS_LOADED });
      return undefined;
    }
    let isCurrent = true;
    readTerm(recordID)
      .then((record) => {
        if (isCurrent) {
          setState({ status: STATUS_LOADED, record });
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
  }, [recordID, reloadCount]);

  useEffect(() => {
    if (state.status === STATUS_LOADED) {
      dispatch(setBreadcrumbs(getCrumbs(termID, state.record)));
    }
  }, [state.status, state.record]);

  useEffect(() => {
    if (state.status === STATUS_LOADED && shouldFocusHeader) {
      headerRef.current.focus();
    }
  }, [state.status, shouldFocusHeader]);

  const showFailure = (messages) => {
    setFormMessages(messages);
    return createSubmissionError({}, messages);
  };

  const handleSubmitFailure = async (error) => {
    if (error.response?.status !== 422) {
      return showFailure([await getJsonErrorMessage(error)]);
    }
    const { fieldMessages, formMessages: messages } = sortApiErrors(
      await readApiErrors(error),
      getFieldNames(schema?.schema?.fields)
    );
    setFormMessages(messages);
    return createSubmissionError(fieldMessages, messages);
  };

  const handleSubmit = async (values) => {
    setFormMessages([]);
    try {
      const body = createRequestBody(values, schema?.schema?.fields);
      if (termID) {
        const term = await updateTerm(termID, body);
        dispatch(createSuccessToast(i18n.inject(i18n._t('Taxonomy.SAVED_TERM', 'Saved "{name}"'), { name: term.Name })));
        setReloadCount((count) => count + 1);
        return undefined;
      }
      const term = await createTerm(body, parentID);
      dispatch(createSuccessToast(i18n.inject(i18n._t('Taxonomy.CREATED_TERM', 'Created "{name}"'), { name: term.Name })));
      navigate(getClientPath('terms', term.ID), { state: { shouldFocusHeading: true } });
      return undefined;
    } catch (error) {
      throw await handleSubmitFailure(error);
    }
  };

  const getParentPath = (term) => (
    term.ParentID ? getClientPath('terms', term.ParentID) : getClientPath('terms')
  );

  const handleSchemaError = ({ errors }) => {
    setSchemaError(errors[0].value);
  };

  const handleConfirmDelete = async () => {
    setIsDeleting(true);
    try {
      await deleteTerm(termID);
      dispatch(createSuccessToast(i18n.inject(
        i18n._t('Taxonomy.DELETED_TERM', 'Deleted "{name}"'),
        { name: state.record.Name }
      )));
      navigate(getParentPath(state.record), { state: { shouldFocusHeading: true } });
    } catch (error) {
      setFormMessages([await getJsonErrorMessage(error)]);
      setIsDeleting(false);
      setIsConfirmingDelete(false);
    }
  };

  const handleRetry = () => {
    setState({ status: STATUS_LOADING });
    setReloadCount((count) => count + 1);
  };

  if (state.status === STATUS_LOADING) {
    return (
      <div className="taxonomy-term" role="status">
        <Loading />
        <span className="visually-hidden">{i18n._t('Taxonomy.LOADING_TERM', 'Loading term')}</span>
      </div>
    );
  }
  if (state.status === STATUS_ERROR) {
    return (
      <div className="taxonomy-term">
        <div className="alert alert-danger" role="alert">
          <p>{i18n._t('Taxonomy.LOAD_TERM_ERROR', 'Failed to load the term')}</p>
          <p>{state.message}</p>
          <button type="button" className="btn btn-secondary" onClick={handleRetry}>
            {i18n._t('Taxonomy.TRY_AGAIN', 'Try again')}
          </button>
        </div>
      </div>
    );
  }

  const { record } = state;
  const handleBack = () => navigate(termID ? getParentPath(record) : getClientPath('terms'));

  return (
    <div className="taxonomy-term">
      <div className="taxonomy-term__header" tabIndex={-1} ref={headerRef}>
        <Toolbar showBackButton onBackButtonClick={handleBack}>
          <Breadcrumb />
        </Toolbar>
      </div>
      <div className="taxonomy-term__body">
        {formMessages.map((message) => (
          <FormAlert key={message} type="error" value={message} />
        ))}
        {schemaError && (
          <div className="alert alert-danger" role="alert">
            <p>{i18n._t('Taxonomy.LOAD_FORM_ERROR', 'Failed to load the form')}</p>
            <p>{schemaError}</p>
          </div>
        )}
        <FormBuilderLoader
          identifier={`TaxonomyTerm_${termID || `new_${parentID}`}`}
          schemaUrl={schemaUrl}
          refetchSchemaOnMount
          refetchSchemaCriteria={String(reloadCount)}
          onSubmit={handleSubmit}
          onLoadingError={handleSchemaError}
        />
        {termID > 0 && record.meta.can.delete && (
          <button type="button" className="btn btn-outline-danger taxonomy-term__delete" onClick={() => setIsConfirmingDelete(true)}>
            {i18n._t('Taxonomy.DELETE_TERM', 'Delete term')}
          </button>
        )}
        {termID > 0 && <ChildTerms parent={record} />}
      </div>
      {termID > 0 && (
        <DeleteTermModal
          term={isConfirmingDelete ? record : null}
          isDeleting={isDeleting}
          onConfirm={handleConfirmDelete}
          onCancel={() => setIsConfirmingDelete(false)}
        />
      )}
    </div>
  );
};

TermEditor.propTypes = {
  termID: PropTypes.number.isRequired,
  parentID: PropTypes.number.isRequired,
};

const TermEditScreen = () => {
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const termID = toPositiveInteger(id);
  const parentID = toPositiveInteger(searchParams.get('parentID'));
  const isValid = (id === undefined || termID > 0) && (!searchParams.has('parentID') || parentID > 0);
  if (!isValid) {
    return (
      <div className="taxonomy-term">
        <div className="alert alert-danger" role="alert">
          {i18n._t('Taxonomy.INVALID_ADDRESS', 'The address of this page is not valid')}
        </div>
      </div>
    );
  }
  // A new key resets the screen, because react-router reuses this component for "terms/new" and "terms/:id"
  return <TermEditor key={`${termID}-${parentID}`} termID={termID} parentID={parentID} />;
};

export default TermEditScreen;
