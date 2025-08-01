// ***********************************************************
// This example support/e2e.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
import './commands';

// Alternatively you can use CommonJS syntax:
// require('./commands')

// Global configuration
beforeEach(() => {
  // Clear cookies and localStorage before each test
  cy.clearCookies();
  cy.clearLocalStorage();

  // Handle uncaught exceptions (useful for WordPress sites)
  Cypress.on('uncaught:exception', (err, runnable) => {
    // WordPress sites often have harmless JS errors
    // Return false to prevent Cypress from failing the test
    if (err.message.includes('ResizeObserver') || err.message.includes('Non-Error promise rejection')) {
      return false;
    }
    // Let other errors fail the test
    return true;
  });
});

// Global after hook
afterEach(() => {
  // Clean up after each test
  cy.clearCookies();
});

// Hide fetch/XHR requests from command log to reduce noise
Cypress.on('window:before:load', win => {
  // Uncomment to hide all network requests
  // win.fetch = null
});
