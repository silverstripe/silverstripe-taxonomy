/* global document */
import reactRouteRegister from 'lib/ReactRouteRegister';
import { joinUrlPaths } from 'lib/urls';
import TermEditScreen from 'containers/TermEditScreen/TermEditScreen';
import TermsListScreen from 'containers/TermsListScreen/TermsListScreen';
import TypeEditScreen from 'containers/TypeEditScreen/TypeEditScreen';
import TypesListScreen from 'containers/TypesListScreen/TypesListScreen';
import { getSectionConfig } from 'lib/clientPaths';

document.addEventListener('DOMContentLoaded', () => {
  const baseURL = getSectionConfig().reactRoutePath;
  reactRouteRegister.add({
    path: '/',
    routes: [
      { path: joinUrlPaths(baseURL, 'terms/new'), component: TermEditScreen },
      { path: joinUrlPaths(baseURL, 'terms/:id'), component: TermEditScreen },
      { path: joinUrlPaths(baseURL, 'terms'), component: TermsListScreen },
      { path: joinUrlPaths(baseURL, 'types/new'), component: TypeEditScreen },
      { path: joinUrlPaths(baseURL, 'types/:id'), component: TypeEditScreen },
      { path: joinUrlPaths(baseURL, 'types'), component: TypesListScreen },
      { path: baseURL, component: TermsListScreen },
    ],
  });
});
