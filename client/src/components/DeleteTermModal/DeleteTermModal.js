import React, { useRef } from 'react';
import PropTypes from 'prop-types';
import { Modal as ReactstrapModal } from 'reactstrap';
import i18n from 'i18n';
import Modal from 'components/Modal/Modal';

const DeleteTermModal = ({
  term,
  title = i18n._t('Taxonomy.DELETE_TERM_TITLE', 'Delete term'),
  isDeleting = false,
  onConfirm,
  onCancel,
  onExited,
}) => {
  const onExitedRef = useRef(onExited);
  onExitedRef.current = onExited;
  // The admin Modal does not turn on trapFocus and does not pass on onClosed, which reactstrap calls after the exit
  // transition. The component type must stay the same between renders, or the modal mounts again each time.
  const ModalComponent = useRef((props) => (
    <ReactstrapModal {...props} trapFocus onClosed={() => onExitedRef.current?.()} />
  )).current;

  const getMessage = () => {
    if (!term) {
      return '';
    }
    const message = term.meta.childCount > 0
      ? i18n._t('Taxonomy.DELETE_TERM_WITH_CHILDREN', 'Delete "{name}"? This also deletes all the terms nested under it. This cannot be undone.')
      : i18n._t('Taxonomy.DELETE_TERM_MESSAGE', 'Delete "{name}"? This cannot be undone.');
    return i18n.inject(message, { name: term.Name });
  };

  return (
    <Modal
      ModalComponent={ModalComponent}
      isOpen={term !== null}
      onClosed={onCancel}
      title={title}
    >
      <div className="modal-body">
        <p>{getMessage()}</p>
      </div>
      <div className="modal-footer">
        <button type="button" className="btn btn-secondary" onClick={onCancel}>
          {i18n._t('Taxonomy.CANCEL', 'Cancel')}
        </button>
        <button type="button" className="btn btn-danger" disabled={isDeleting} onClick={onConfirm}>
          {i18n._t('Taxonomy.DELETE', 'Delete')}
        </button>
      </div>
    </Modal>
  );
};

DeleteTermModal.propTypes = {
  term: PropTypes.shape({
    Name: PropTypes.string.isRequired,
    meta: PropTypes.shape({ childCount: PropTypes.number }).isRequired,
  }),
  title: PropTypes.string,
  isDeleting: PropTypes.bool,
  onConfirm: PropTypes.func.isRequired,
  onCancel: PropTypes.func.isRequired,
  onExited: PropTypes.func,
};

export default DeleteTermModal;
