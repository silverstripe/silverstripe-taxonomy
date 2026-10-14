/* global test, expect, document */
import reactRouteRegister from 'lib/ReactRouteRegister';
import TermEditScreen from 'containers/TermEditScreen/TermEditScreen';
import TermsListScreen from 'containers/TermsListScreen/TermsListScreen';
import TypeEditScreen from 'containers/TypeEditScreen/TypeEditScreen';
import TypesListScreen from 'containers/TypesListScreen/TypesListScreen';
import 'boot';

test('boot registers the term and type screens under the section route path', () => {
  document.dispatchEvent(new Event('DOMContentLoaded'));
  expect(reactRouteRegister.getChildRoutes()).toEqual([
    { path: 'taxonomy/terms/new', component: TermEditScreen },
    { path: 'taxonomy/terms/:id', component: TermEditScreen },
    { path: 'taxonomy/terms', component: TermsListScreen },
    { path: 'taxonomy/types/new', component: TypeEditScreen },
    { path: 'taxonomy/types/:id', component: TypeEditScreen },
    { path: 'taxonomy/types', component: TypesListScreen },
    { path: 'taxonomy', component: TermsListScreen },
  ]);
});
