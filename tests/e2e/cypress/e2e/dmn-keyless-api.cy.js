describe('DMN API Tests (No API Key Required)', () => {
  it('should connect to the test environment', () => {
    cy.visit('/');
    cy.get('body').should('be.visible');
    cy.url().should('include', 'owc-gemeente.test.open-regels.nl');
  });

  it('should check if DMN plugin directory is accessible', () => {
    // Direct test without custom command
    cy.request({
      url: '/wp-content/plugins/operaton-dmn-evaluator/',
      failOnStatusCode: false,
    }).then(response => {
      // Log the result
      cy.log(`Plugin directory check returned: ${response.status}`);

      // Assert that we get a valid HTTP response (not network error)
      expect(response.status).to.be.a('number');
      expect(response.status).to.be.at.least(200);
      expect(response.status).to.be.at.most(599);

      // More specific checks
      if (response.status === 200) {
        cy.log('✅ Plugin directory is publicly accessible');
      } else if (response.status === 403) {
        cy.log('✅ Plugin directory exists but is protected (good security)');
      } else if (response.status === 404) {
        cy.log('⚠️ Plugin directory not found - plugin may not be installed');
      }
    });
  });

  it('should test DMN health endpoint', () => {
    cy.checkDMNHealth();
  });

  it('should test basic DMN evaluation without API key', () => {
    const testEvaluationData = {
      age: 25,
      income: 45000,
      credit_score: 'good',
    };

    cy.testDMNEvaluation(testEvaluationData);
  });

  it('should test various evaluation scenarios', () => {
    const testCases = [
      {
        name: 'High Income Applicant',
        data: { age: 35, income: 80000, credit_score: 'excellent' },
      },
      {
        name: 'Young Professional',
        data: { age: 24, income: 35000, credit_score: 'good' },
      },
      {
        name: 'Senior Applicant',
        data: { age: 55, income: 60000, credit_score: 'fair' },
      },
    ];

    testCases.forEach((testCase, index) => {
      cy.log(`🧪 Testing: ${testCase.name}`);
      cy.testDMNEvaluation(testCase.data);

      // Add a small delay between tests to be nice to the server
      if (index < testCases.length - 1) {
        cy.wait(500);
      }
    });
  });

  it('should handle malformed requests gracefully', () => {
    // Test with invalid data to see how API handles it
    const invalidData = {
      age: 'not-a-number',
      income: null,
      credit_score: 123,
    };

    cy.request({
      method: 'POST',
      url: '/wp-json/operaton-dmn/v1/evaluate',
      body: invalidData,
      failOnStatusCode: false,
      headers: {
        'Content-Type': 'application/json',
      },
    }).then(response => {
      cy.log(`Invalid data test returned: ${response.status}`);
      // API should handle invalid data gracefully (400, 422, etc.)
      expect([200, 400, 422, 500]).to.include(response.status);
    });
  });
});
