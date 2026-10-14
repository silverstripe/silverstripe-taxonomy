/* global window, fetch, jest */
import Backend from 'lib/Backend';

// node-fetch (via isomorphic-fetch) rejects the relative URLs that the real client sends.
const mockFetchWithBase = (url, options) => fetch(new URL(url, window.location.href).href, options);
jest.mock('isomorphic-fetch', () => (url, options) => mockFetchWithBase(url, options));
Backend.fetch = mockFetchWithBase;

window.ss = {
  config: {
    SecurityID: 'test-token',
    sections: [
      { name: 'SilverStripe\\Taxonomy\\TaxonomyAdmin', apiUrl: 'admin/api/v1/taxonomy', reactRoutePath: 'taxonomy' },
    ],
  },
};

// Admin components log these warnings on every render
const IGNORED_WARNINGS = [
  'legacy childContextTypes API',
  'The prop `timeout` is marked as required in `Fade`',
  'The prop `passRef` is marked as required in `SearchableDropdownField`',
  /Invalid DOM property .* class className/,
  'Support for defaultProps will be removed from function components',
];
const { error } = window.console;
window.console.error = (...args) => {
  // React passes the details of a warning as format arguments
  const text = args.filter((arg) => typeof arg === 'string').join(' ');
  if (IGNORED_WARNINGS.some((warning) => (warning instanceof RegExp ? warning.test(text) : text.includes(warning)))) {
    return;
  }
  error(...args);
};
