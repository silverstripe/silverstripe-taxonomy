const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: 'tests/playwright',
  workers: 1,
  globalSetup: require.resolve('./tests/playwright/globalSetup'),
  globalTeardown: require.resolve('./tests/playwright/globalTeardown'),
  retries: 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.SS_BASE_URL || 'http://bugfix31.test',
    trace: 'retain-on-failure',
    launchOptions: {
      executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/chrome-linux64/chrome',
      args: ['--no-sandbox'],
    },
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
});
