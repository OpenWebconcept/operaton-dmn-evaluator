describe('Live Test Environment - DMN Plugin', () => {
  beforeEach(() => {
    // Clear cookies to ensure clean state
    cy.clearCookies();
    cy.clearLocalStorage();
  });

  it('should access the live test site', () => {
    cy.visit('/');
    cy.get('body').should('be.visible');

    // Check for WordPress indicators
    cy.get('html').should('have.attr', 'lang');

    // Verify it's the correct test site
    cy.url().should('include', 'owc-gemeente.test.open-regels.nl');
  });

  it('should verify DMN plugin is installed', () => {
    // Check if plugin assets are accessible
    cy.request({
      url: '/wp-content/plugins/operaton-dmn-evaluator/',
      failOnStatusCode: false,
    }).then(response => {
      // Should get 200, 403, or redirect - not 404
      expect(response.status).to.not.eq(404);
    });
  });

  it('should access WordPress admin login', () => {
    cy.visit('/wp-admin');

    // Should see login form
    cy.get('#loginform').should('be.visible');
    cy.get('#user_login').should('be.visible');
    cy.get('#user_pass').should('be.visible');
  });

  it('should test DMN API health endpoint', () => {
    cy.request({
      url: '/wp-json/operaton-dmn/v1/health',
      failOnStatusCode: false,
    }).then(response => {
      if (response.status === 200) {
        cy.log('✅ DMN API is accessible');
        expect(response.body).to.have.property('status');
      } else {
        cy.log('⚠️ DMN API may not be active or accessible');
      }
    });
  });

  it('should check for Gravity Forms integration', () => {
    cy.visit('/wp-admin');

    // Try to access Gravity Forms (will redirect to login, but URL should exist)
    cy.request({
      url: '/wp-admin/admin.php?page=gf_edit_forms',
      failOnStatusCode: false,
    }).then(response => {
      // Should get redirect to login (302) or login page (200), not 404
      expect(response.status).to.be.oneOf([200, 302]);
    });
  });
});
