import Backend from 'lib/Backend';
import Config from 'lib/Config';
import { getSectionConfig } from 'lib/clientPaths';
import { CSRF_HEADER } from 'constants/section';

const getApiUrl = (...segments) => [getSectionConfig().apiUrl, ...segments].join('/');

const getPageQuery = ({ limit, offset }) => new URLSearchParams({ limit, offset });

// Rejects with the error from lib/Backend when the status is not 2xx
const readJson = async (url) => {
  const response = await Backend.get(url, { Accept: 'application/json' });
  return response.json();
};

// Backend has no PATCH method, so this sends the request through Backend.fetch and rejects like Backend.get does
const sendJson = async (method, url, body) => {
  const headers = { Accept: 'application/json', [CSRF_HEADER]: Config.get('SecurityID') };
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }
  const response = await Backend.fetch(url, {
    method,
    credentials: 'same-origin',
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  if (!response.ok) {
    const error = new Error(response.statusText);
    error.response = response;
    throw error;
  }
  return response.status === 204 ? null : response.json();
};

/**
 * Reads the errors from a rejected API request, in the shape of the "errors" key of the API error body.
 * Returns an empty array when the response has no such body.
 */
export const readApiErrors = async (error) => {
  try {
    const body = await error.response.json();
    return Array.isArray(body.errors) ? body.errors : [];
  } catch (readError) {
    return [];
  }
};

export const readIndex = () => readJson(getApiUrl());

export const readTerms = ({ query = '', limit, offset }) => {
  const params = getPageQuery({ limit, offset });
  if (query !== '') {
    params.set('q', query);
  }
  return readJson(`${getApiUrl('terms')}?${params}`);
};

export const readTerm = (id) => readJson(getApiUrl('terms', id));

export const readChildTerms = (id, page) => readJson(`${getApiUrl('terms', id, 'children')}?${getPageQuery(page)}`);

export const getTermSchemaUrl = (id) => getApiUrl('terms', id, 'schema');

export const getNewTermSchemaUrl = (parentID = 0) => {
  const url = getApiUrl('terms', 'new', 'schema');
  return parentID ? `${url}?parentID=${parentID}` : url;
};

export const createTerm = (data, parentID = 0) => sendJson(
  'POST',
  parentID ? getApiUrl('terms', parentID, 'children') : getApiUrl('terms'),
  data
);

export const updateTerm = (id, data) => sendJson('PATCH', getApiUrl('terms', id), data);

export const deleteTerm = (id) => sendJson('DELETE', getApiUrl('terms', id));

export const moveTerm = (id, position) => sendJson('PATCH', getApiUrl('terms', id), { Sort: position });

export const readTypes = ({ limit, offset }) => readJson(`${getApiUrl('types')}?${getPageQuery({ limit, offset })}`);

export const readType = (id) => readJson(getApiUrl('types', id));

export const getTypeSchemaUrl = (id) => getApiUrl('types', id, 'schema');

export const getNewTypeSchemaUrl = () => getApiUrl('types', 'new', 'schema');

export const createType = (data) => sendJson('POST', getApiUrl('types'), data);

export const updateType = (id, data) => sendJson('PATCH', getApiUrl('types', id), data);

export const deleteType = (id) => sendJson('DELETE', getApiUrl('types', id));
