const { defineConfig } = require('cypress');

module.exports = defineConfig({
  e2e: {
    baseUrl: 'https://owc-gemeente.test.open-regels.nl',
    supportFile: 'tests/e2e/cypress/support/e2e.js',
    specPattern: 'tests/e2e/cypress/e2e/**/*.cy.js',
    fixturesFolder: 'tests/e2e/cypress/fixtures',
    screenshotsFolder: 'tests/e2e/cypress/screenshots',
    videosFolder: 'tests/e2e/cypress/videos',
    viewportWidth: 1280,
    viewportHeight: 720,
    video: true,
    screenshotOnRunFailure: true,

    // Extended timeouts for live server
    defaultCommandTimeout: 15000,
    requestTimeout: 20000,
    responseTimeout: 20000,
    pageLoadTimeout: 30000,

    // Security settings for HTTPS
    chromeWebSecurity: false,

    // Retry settings for network reliability
    retries: {
      runMode: 2,
      openMode: 0,
    },

    // Environment variables
    env: {
      login_url: '/wp-admin',
      api_base: '/wp-json/operaton-dmn/v1',
    },
  },
});
