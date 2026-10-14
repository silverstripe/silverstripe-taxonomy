import React, { useId, useState } from 'react';
import PropTypes from 'prop-types';
import i18n from 'i18n';

const TermsSearchForm = ({ initialQuery = '', onSearch }) => {
  const inputId = useId();
  const [query, setQuery] = useState(initialQuery);

  const handleSubmit = (event) => {
    event.preventDefault();
    onSearch(query.trim());
  };

  const handleClear = () => {
    setQuery('');
    onSearch('');
  };

  return (
    <form className="taxonomy-search" role="search" onSubmit={handleSubmit}>
      <label className="taxonomy-search__label" htmlFor={inputId}>
        {i18n._t('Taxonomy.SEARCH_LABEL', 'Search terms by name')}
      </label>
      <input
        id={inputId}
        className="form-control taxonomy-search__input"
        type="search"
        value={query}
        onChange={(event) => setQuery(event.target.value)}
      />
      <button type="submit" className="btn btn-primary">
        {i18n._t('Taxonomy.SEARCH', 'Search')}
      </button>
      {initialQuery !== '' && (
        <button type="button" className="btn btn-secondary" onClick={handleClear}>
          {i18n._t('Taxonomy.CLEAR_SEARCH', 'Clear search')}
        </button>
      )}
    </form>
  );
};

TermsSearchForm.propTypes = {
  initialQuery: PropTypes.string,
  onSearch: PropTypes.func.isRequired,
};

export default TermsSearchForm;
