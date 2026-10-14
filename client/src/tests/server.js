import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import {
  error404,
  error404Type,
  index,
  term,
  termChild,
  termChildCreated,
  termChildSchema,
  termChildren,
  termChildrenEmpty,
  termCreated,
  termNewChildSchema,
  termNewSchema,
  termSchema,
  termUpdated,
  termsList,
  termsListEmpty,
  termsListPage1,
  termsListPage2,
  termsListSearch,
  type,
  typeCreated,
  typeNewSchema,
  typeSchema,
  typeUpdated,
  typesList,
} from './fixtures';

export const API_URL = 'admin/api/v1/taxonomy';

export const respondWithFixture = (fixture) => HttpResponse.json(fixture.body, { status: fixture.status });

const selectTermsFixture = (url) => {
  const query = url.searchParams.get('q');
  if (query === 'Pla') {
    return termsListSearch;
  }
  if (query) {
    return termsListEmpty;
  }
  if (url.searchParams.get('limit') === '1') {
    return url.searchParams.get('offset') === '1' ? termsListPage2 : termsListPage1;
  }
  return termsList;
};

// The IDs are the ones that the fixtures use: 1 is Plant, 2 is Animal, 3 is Vegetable and 4 is Carrot
const TERM_FIXTURES = { 1: term, 4: termChild, 6: termCreated };

const CHILDREN_FIXTURES = { 2: termChildrenEmpty, 4: termChildrenEmpty };

// The IDs are the ones that the fixtures use: 1 is Biology and 2 is the type that the create fixture returns
const TYPE_FIXTURES = { 1: type, 2: typeCreated };

export const requestedUrls = [];

/**
 * The write requests that the handlers received, as { method, url, headers, body }
 */
export const writeRequests = [];

const recordWrite = async (request) => {
  const text = await request.text();
  writeRequests.push({
    method: request.method,
    url: new URL(request.url),
    headers: request.headers,
    body: text === '' ? null : JSON.parse(text),
  });
};

export const server = setupServer(
  http.get(/\/admin\/api\/v1\/taxonomy$/, () => respondWithFixture(index)),
  http.get(`*/${API_URL}/types`, ({ request }) => {
    requestedUrls.push(new URL(request.url));
    return respondWithFixture(typesList);
  }),
  http.get(`*/${API_URL}/types/new/schema`, () => respondWithFixture(typeNewSchema)),
  http.get(`*/${API_URL}/types/:id/schema`, () => respondWithFixture(typeSchema)),
  http.get(`*/${API_URL}/types/:id`, ({ params }) => respondWithFixture(TYPE_FIXTURES[params.id] ?? error404Type)),
  http.post(`*/${API_URL}/types`, async ({ request }) => {
    await recordWrite(request);
    return respondWithFixture(typeCreated);
  }),
  http.patch(`*/${API_URL}/types/:id`, async ({ request }) => {
    await recordWrite(request);
    return respondWithFixture(typeUpdated);
  }),
  http.delete(`*/${API_URL}/types/:id`, async ({ request }) => {
    await recordWrite(request);
    return new HttpResponse(null, { status: 204 });
  }),
  http.get(`*/${API_URL}/terms`, ({ request }) => {
    const url = new URL(request.url);
    requestedUrls.push(url);
    return respondWithFixture(selectTermsFixture(url));
  }),
  http.get(`*/${API_URL}/terms/new/schema`, ({ request }) => (
    respondWithFixture(new URL(request.url).searchParams.has('parentID') ? termNewChildSchema : termNewSchema)
  )),
  http.get(`*/${API_URL}/terms/:id/schema`, ({ params }) => (
    respondWithFixture(params.id === '4' ? termChildSchema : termSchema)
  )),
  http.get(`*/${API_URL}/terms/:id/children`, ({ params, request }) => {
    requestedUrls.push(new URL(request.url));
    return respondWithFixture(CHILDREN_FIXTURES[params.id] ?? termChildren);
  }),
  http.get(`*/${API_URL}/terms/:id`, ({ params }) => respondWithFixture(TERM_FIXTURES[params.id] ?? error404)),
  http.post(`*/${API_URL}/terms`, async ({ request }) => {
    await recordWrite(request);
    return respondWithFixture(termCreated);
  }),
  http.post(`*/${API_URL}/terms/:id/children`, async ({ request }) => {
    await recordWrite(request);
    return respondWithFixture(termChildCreated);
  }),
  http.patch(`*/${API_URL}/terms/:id`, async ({ request }) => {
    await recordWrite(request);
    return respondWithFixture(termUpdated);
  }),
  http.delete(`*/${API_URL}/terms/:id`, async ({ request }) => {
    await recordWrite(request);
    return new HttpResponse(null, { status: 204 });
  })
);
