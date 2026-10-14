import React, { useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
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
import DeleteTermModal from 'components/DeleteTermModal/DeleteTermModal';
import { getClientPath } from 'lib/clientPaths';
import {
  createType,
  deleteType,
  getNewTypeSchemaUrl,
  getTypeSchemaUrl,
  readApiErrors,
  readType,
  updateType,
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

const getCrumbs = (typeID, record) => [
  { text: i18n._t('Taxonomy.TYPES_TITLE', 'Taxonomy types'), href: getClientPath('types') },
  typeID
    ? { text: record.Name, href: getClientPath('types', typeID) }
    : { text: i18n._t('Taxonomy.ADD_TYPE', 'Add type'), href: getClientPath('types', 'new') },
];

/**
 * Edits a type (typeID is set), or creates a type (typeID is 0).
 */
const TypeEditor = ({ typeID }) => {
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
  const schemaUrl = typeID ? getTypeSchemaUrl(typeID) : getNewTypeSchemaUrl();
  const schema = useSelector((reduxState) => reduxState.form.formSchemas[schemaUrl]);

  useEffect(() => {
    if (!typeID) {
      setState({ status: STATUS_LOADED });
      return undefined;
    }
    let isCurrent = true;
    readType(typeID)
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
  }, [typeID, reloadCount]);

  useEffect(() => {
    if (state.status === STATUS_LOADED) {
      dispatch(setBreadcrumbs(getCrumbs(typeID, state.record)));
    }
  }, [state.status, state.record]);

  useEffect(() => {
    if (state.status === STATUS_LOADED && shouldFocusHeader) {
      headerRef.current.focus();
    }
  }, [state.status, shouldFocusHeader]);

  const handleSubmitFailure = async (error) => {
    if (error.response?.status !== 422) {
      const message = await getJsonErrorMessage(error);
      setFormMessages([message]);
      return createSubmissionError({}, [message]);
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
      if (typeID) {
        const type = await updateType(typeID, body);
        dispatch(createSuccessToast(i18n.inject(i18n._t('Taxonomy.SAVED_TYPE', 'Saved "{name}"'), { name: type.Name })));
        setReloadCount((count) => count + 1);
        return undefined;
      }
      const type = await createType(body);
      dispatch(createSuccessToast(i18n.inject(i18n._t('Taxonomy.CREATED_TYPE', 'Created "{name}"'), { name: type.Name })));
      navigate(getClientPath('types', type.ID), { state: { shouldFocusHeading: true } });
      return undefined;
    } catch (error) {
      throw await handleSubmitFailure(error);
    }
  };

  const handleConfirmDelete = async () => {
    setIsDeleting(true);
    try {
      await deleteType(typeID);
      dispatch(createSuccessToast(i18n.inject(
        i18n._t('Taxonomy.DELETED_TYPE', 'Deleted "{name}"'),
        { name: state.record.Name }
      )));
      navigate(getClientPath('types'), { state: { shouldFocusHeading: true } });
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
        <span className="visually-hidden">{i18n._t('Taxonomy.LOADING_TYPE', 'Loading type')}</span>
      </div>
    );
  }
  if (state.status === STATUS_ERROR) {
    return (
      <div className="taxonomy-term">
        <div className="alert alert-danger" role="alert">
          <p>{i18n._t('Taxonomy.LOAD_TYPE_ERROR', 'Failed to load the type')}</p>
          <p>{state.message}</p>
          <button type="button" className="btn btn-secondary" onClick={handleRetry}>
            {i18n._t('Taxonomy.TRY_AGAIN', 'Try again')}
          </button>
        </div>
      </div>
    );
  }

  const { record } = state;

  return (
    <div className="taxonomy-term">
      <div className="taxonomy-term__header" tabIndex={-1} ref={headerRef}>
        <Toolbar showBackButton onBackButtonClick={() => navigate(getClientPath('types'))}>
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
          identifier={`TaxonomyType_${typeID || 'new'}`}
          schemaUrl={schemaUrl}
          refetchSchemaOnMount
          refetchSchemaCriteria={String(reloadCount)}
          onSubmit={handleSubmit}
          onLoadingError={({ errors }) => setSchemaError(errors[0].value)}
        />
        {typeID > 0 && record.meta.can.delete && (
          <button type="button" className="btn btn-outline-danger taxonomy-term__delete" onClick={() => setIsConfirmingDelete(true)}>
            {i18n._t('Taxonomy.DELETE_TYPE', 'Delete type')}
          </button>
        )}
      </div>
      {typeID > 0 && (
        <DeleteTermModal
          term={isConfirmingDelete ? record : null}
          title={i18n._t('Taxonomy.DELETE_TYPE_TITLE', 'Delete type')}
          isDeleting={isDeleting}
          onConfirm={handleConfirmDelete}
          onCancel={() => setIsConfirmingDelete(false)}
        />
      )}
    </div>
  );
};

TypeEditor.propTypes = {
  typeID: PropTypes.number.isRequired,
};

const TypeEditScreen = () => {
  const { id } = useParams();
  const typeID = toPositiveInteger(id);
  if (id !== undefined && typeID === 0) {
    return (
      <div className="taxonomy-term">
        <div className="alert alert-danger" role="alert">
          {i18n._t('Taxonomy.INVALID_ADDRESS', 'The address of this page is not valid')}
        </div>
      </div>
    );
  }
  // A new key resets the screen, because react-router reuses this component for "types/new" and "types/:id"
  return <TypeEditor key={typeID} typeID={typeID} />;
};

export default TypeEditScreen;
