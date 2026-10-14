import React, { useEffect, useState } from 'react';
import PropTypes from 'prop-types';
import { Link } from 'react-router-dom';
import i18n from 'i18n';
import { getClientPath } from 'lib/clientPaths';
import { readIndex } from 'lib/taxonomyApi';

export const SECTION_TERMS = 'terms';
export const SECTION_TYPES = 'types';

// Tabs for the collections that the API root lists as viewable
const SectionNav = ({ current }) => {
  const [collections, setCollections] = useState({});

  useEffect(() => {
    let isCurrent = true;
    readIndex()
      .then((index) => {
        if (isCurrent) {
          setCollections(index.collections);
        }
      })
      // The screen that renders this nav shows the error of its own request
      .catch(() => {
        if (isCurrent) {
          setCollections({});
        }
      });
    return () => {
      isCurrent = false;
    };
  }, []);

  const tabs = [
    { section: SECTION_TERMS, label: i18n._t('Taxonomy.TAB_TERMS', 'Terms') },
    { section: SECTION_TYPES, label: i18n._t('Taxonomy.TAB_TYPES', 'Types') },
  ].filter(({ section }) => collections[section]?.can.view);

  if (tabs.length === 0) {
    return null;
  }

  return (
    <nav className="taxonomy-nav" aria-label={i18n._t('Taxonomy.NAV_LABEL', 'Taxonomy sections')}>
      <ul className="nav nav-tabs">
        {tabs.map(({ section, label }) => (
          <li className="nav-item" key={section}>
            <Link
              className={section === current ? 'nav-link active' : 'nav-link'}
              aria-current={section === current ? 'page' : undefined}
              to={getClientPath(section)}
            >
              {label}
            </Link>
          </li>
        ))}
      </ul>
    </nav>
  );
};

SectionNav.propTypes = {
  current: PropTypes.oneOf([SECTION_TERMS, SECTION_TYPES]).isRequired,
};

export default SectionNav;
