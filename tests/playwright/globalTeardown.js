const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const { request: playwrightRequest } = require('@playwright/test');

const { FIXTURE_LINK_DIRECTORY, endTestSession } = require('./session');
const { getWebroot } = require('./globalSetup');

const STATE_FILE = 'TESTS_RUNNING.json';

const readLeftoverDatabase = (stateFile) => {
  try {
    return JSON.parse(fs.readFileSync(stateFile, 'utf8')).database;
  } catch (error) {
    return null;
  }
};

module.exports = async (config) => {
  const webroot = getWebroot();
  const request = await playwrightRequest.newContext({ baseURL: config.projects[0].use.baseURL });
  try {
    await endTestSession(request);
  } finally {
    await request.dispose();
    // The end request drops the test database, but the testsession middleware then starts a new session with a new
    // database. The site keeps using that session until its state file is gone.
    const stateFile = path.join(webroot, STATE_FILE);
    const leftoverDatabase = readLeftoverDatabase(stateFile);
    fs.rmSync(stateFile, { force: true });
    if (leftoverDatabase) {
      spawnSync('php', [path.join(__dirname, 'dropTestDatabase.php'), leftoverDatabase], { stdio: 'inherit' });
    }
    fs.rmSync(path.join(webroot, FIXTURE_LINK_DIRECTORY), { recursive: true, force: true });
  }
};
