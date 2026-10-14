const { expect } = require('@playwright/test');

const FIXTURE_LINK_DIRECTORY = 'taxonomy-e2e';

const FIXTURE = `${FIXTURE_LINK_DIRECTORY}/tests/taxonomy.yml`;

const PASSWORD = 'Taxonomy-e2e-1!';

const requestTestSession = async (request, action, parameters = {}) => {
  const query = new URLSearchParams(parameters).toString();
  const response = await request.get(`/dev/testsession/${action}${query ? `?${query}` : ''}`);
  if (!response.ok()) {
    throw new Error(`The test session request "${action}" failed with status ${response.status()}`);
  }
};

// The installed testsession cannot load a fixture into a running session, so the tests share one global session.
const startTestSession = (request) => requestTestSession(request, 'start', {
  globalTestSession: '1',
  createDatabase: '1',
  taxonomyKeepTestDatabase: '1',
  fixture: FIXTURE,
});

const endTestSession = (request) => requestTestSession(request, 'end');

/**
 * Logs in at the CMS login form. The MFA module shows a registration screen after the login form for a member
 * with no MFA method, which this skips with "Setup later".
 */
const logIn = async (page, email) => {
  await page.goto('/Security/login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(PASSWORD);
  await page.getByRole('button', { name: 'Log in' }).click();
  const skip = page.getByRole('button', { name: 'Setup later' });
  const loggedIn = page.getByText(/You're logged in as/);
  await expect(skip.or(loggedIn)).toBeVisible();
  if (await skip.isVisible()) {
    await skip.click();
  }
  await expect(loggedIn).toBeVisible();
};

module.exports = {
  FIXTURE_LINK_DIRECTORY,
  startTestSession,
  endTestSession,
  logIn,
};
