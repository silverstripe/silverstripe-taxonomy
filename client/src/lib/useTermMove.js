import { useDispatch } from 'react-redux';
import i18n from 'i18n';
import getJsonErrorMessage from 'lib/getJsonErrorMessage';
import { error as createErrorToast } from 'state/toasts/ToastsActions';
import { moveTerm } from 'lib/taxonomyApi';

// Returns a function that moves a term to a 1-based position among its siblings, rolling back if the API refuses.
const useTermMove = ({ items, setItems, offset, reload }) => {
  const dispatch = useDispatch();

  return async (term, position) => {
    const index = position - offset - 1;
    const isOnPage = index >= 0 && index < items.length;
    if (isOnPage) {
      const reordered = items.filter((item) => item.ID !== term.ID);
      reordered.splice(index, 0, term);
      setItems(reordered);
    }
    try {
      await moveTerm(term.ID, position);
      if (!isOnPage) {
        reload();
      }
      return true;
    } catch (error) {
      setItems(items);
      const message = await getJsonErrorMessage(error);
      dispatch(createErrorToast(i18n.inject(
        i18n._t('Taxonomy.MOVE_ERROR', 'Could not move "{name}". {message}'),
        { name: term.Name, message }
      )));
      return false;
    }
  };
};

export default useTermMove;
