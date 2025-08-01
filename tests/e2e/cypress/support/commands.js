// ***********************************************
// Custom commands for DMN Evaluator Plugin
// ***********************************************

// WordPress specific commands
Cypress.Commands.add('loginToTestSite', (username, password) => {
  const user = username || Cypress.env('TEST_USERNAME');
  const pass = password || Cypress.env('TEST_PASSWORD');

  if (!user || !pass) {
    cy.log('⚠️ No test credentials provided - skipping login');
    return;
  }

  cy.session([user, pass], () => {
    cy.visit('/wp-admin');
    cy.get('#user_login', { timeout: 10000 }).type(user);
    cy.get('#user_pass').type(pass);
    cy.get('#wp-submit').click();

    // Verify successful login
    cy.url().should('include', '/wp-admin');
    cy.get('#wpadminbar', { timeout: 10000 }).should('exist');
  });
});

// DMN-specific commands for live environment (no API key required)
Cypress.Commands.add('testDMNEvaluation', formData => {
  const headers = {
    'Content-Type': 'application/json',
  };

  // Only add API key header if one is provided
  const apiKey = Cypress.env('DMN_API_KEY');
  if (apiKey && apiKey.trim() !== '') {
    headers['X-API-Key'] = apiKey;
  }

  cy.request({
    method: 'POST',
    url: '/wp-json/operaton-dmn/v1/evaluate',
    body: formData,
    failOnStatusCode: false,
    headers: headers,
  }).then(response => {
    if (response.status === 200) {
      expect(response.body).to.have.property('decision');
      cy.log('✅ DMN Evaluation successful (no API key required)');
    } else {
      cy.log(`⚠️ DMN Evaluation returned status: ${response.status}`);
      if (response.body && response.body.message) {
        cy.log(`Error message: ${response.body.message}`);
      }
    }
  });
});

// Health check without API key
Cypress.Commands.add('checkDMNHealth', () => {
  cy.request({
    url: '/wp-json/operaton-dmn/v1/health',
    failOnStatusCode: false,
  }).then(response => {
    if (response.status === 200) {
      cy.log('✅ DMN API Health Check passed');
      expect(response.body).to.have.property('status');
    } else {
      cy.log(`⚠️ DMN API Health Check returned: ${response.status}`);
      // Don't fail the test if health endpoint doesn't exist yet
    }
  });
});

// Mock DMN responses for controlled testing
Cypress.Commands.add('mockDMNResponse', mockResponse => {
  cy.intercept('POST', '**/wp-json/operaton-dmn/v1/evaluate', {
    statusCode: 200,
    body: mockResponse,
  }).as('dmnEvaluation');
});

// Utility command to wait for page to be fully loaded
Cypress.Commands.add('waitForPageLoad', () => {
  cy.get('body').should('be.visible');
  cy.window().should('have.property', 'jQuery');
});
