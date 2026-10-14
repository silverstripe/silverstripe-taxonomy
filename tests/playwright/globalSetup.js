const fs = require('fs');
const path = require('path');
const { request: playwrightRequest } = require('@playwright/test');

const { FIXTURE_LINK_DIRECTORY, startTestSession } = require('./session');

const getWebroot = () => {
  const webroot = path.resolve(__dirname, '../../../../..');
  if (!fs.existsSync(path.join(webroot, 'vendor/autoload.php'))) {
    throw new Error(`Expected the module in vendor/silverstripe/taxonomy, but ${webroot} has no vendor/autoload.php`);
  }
  return webroot;
};

/**
 * silverstripe/testsession only loads a fixture whose path starts with one folder and then "tests/", which no file in
 * vendor/ matches. This links the fixtures folder under a name that does. The teardown removes the link.
 */
module.exports = async (config) => {
  const linkDirectory = path.join(getWebroot(), FIXTURE_LINK_DIRECTORY);
  fs.rmSync(linkDirectory, { recursive: true, force: true });
  fs.mkdirSync(linkDirectory);
  fs.symlinkSync(path.join(__dirname, 'fixtures'), path.join(linkDirectory, 'tests'));

  const request = await playwrightRequest.newContext({ baseURL: config.projects[0].use.baseURL });
  await startTestSession(request);
  await request.dispose();
};

module.exports.getWebroot = getWebroot;
