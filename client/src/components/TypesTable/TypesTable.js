import React from 'react';
import PropTypes from 'prop-types';
import { Link } from 'react-router-dom';
import i18n from 'i18n';
import { getClientPath } from 'lib/clientPaths';

const TypesTable = ({ types, caption, onDelete }) => {
  const hasActionsColumn = types.some((type) => type.meta.can.delete);
  return (
    <table className="table table-hover taxonomy-table">
      <caption className="visually-hidden">{caption}</caption>
      <thead>
        <tr>
          <th scope="col">{i18n._t('Taxonomy.COLUMN_NAME', 'Name')}</th>
          {hasActionsColumn && (
            <th scope="col"><span className="visually-hidden">{i18n._t('Taxonomy.COLUMN_ACTIONS', 'Actions')}</span></th>
          )}
        </tr>
      </thead>
      <tbody>
        {types.map((type) => (
          <tr key={type.ID}>
            <th scope="row"><Link to={getClientPath('types', type.ID)}>{type.Name}</Link></th>
            {hasActionsColumn && (
              <td className="taxonomy-table__actions">
                {type.meta.can.delete && (
                  <button type="button" className="btn btn-outline-danger btn-sm" onClick={() => onDelete(type)}>
                    {i18n._t('Taxonomy.DELETE', 'Delete')}
                    <span className="visually-hidden">{` ${type.Name}`}</span>
                  </button>
                )}
              </td>
            )}
          </tr>
        ))}
      </tbody>
    </table>
  );
};

TypesTable.propTypes = {
  types: PropTypes.arrayOf(PropTypes.shape({
    ID: PropTypes.number.isRequired,
    Name: PropTypes.string.isRequired,
    meta: PropTypes.shape({
      can: PropTypes.shape({ delete: PropTypes.bool }).isRequired,
    }).isRequired,
  })).isRequired,
  caption: PropTypes.string.isRequired,
  onDelete: PropTypes.func.isRequired,
};

export default TypesTable;
