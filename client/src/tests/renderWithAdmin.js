import React from 'react';
import { Provider } from 'react-redux';
import { render } from '@testing-library/react';
import { MemoryRouter, parsePath } from 'react-router-dom';
import { combineReducers, createStore, applyMiddleware } from 'redux';
import { thunk } from 'redux-thunk';
import Injector, { provideInjector } from 'lib/Injector';
import registerComponents from 'boot/registerComponents';
import registerReducers from 'boot/registerReducers';

let isBooted = false;

const InjectorRoot = provideInjector(({ children }) => children);

const createAdminStore = () => {
  if (!isBooted) {
    registerComponents();
    registerReducers();
    Injector.load();
    isBooted = true;
  }
  return applyMiddleware(thunk)(createStore)(combineReducers(Injector.reducer.getAll()), {});
};

export const renderWithAdmin = (ui, { url = '/taxonomy', state = null } = {}) => {
  const store = createAdminStore();
  const result = render(
    <Provider store={store}>
      <MemoryRouter initialEntries={[{ ...parsePath(url), state }]} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <InjectorRoot>{ui}</InjectorRoot>
      </MemoryRouter>
    </Provider>
  );
  return { ...result, store };
};
