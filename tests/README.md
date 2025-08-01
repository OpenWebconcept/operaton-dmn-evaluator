
## 📋 **File Breakdown by Category**

### **🧪 PHPUnit Tests (29 total tests, 117 assertions)**

#### **Unit Tests (26 tests)**
```
tests/unit/
├── DmnApiTest.php                    # 10 tests - API endpoint testing
│   ├── testApiManagerExists()
│   ├── testEvaluateDmnWithValidData()
│   ├── testEvaluateDmnWithEmptyDataThrowsException()
│   ├── testEvaluateDmnWithLowIncomeReturnsRejection()
│   ├── testEvaluateDmnWithConditionalApproval()
│   ├── testValidateApiKeyWithValidKey()
│   ├── testValidateApiKeyWithInvalidKey()
│   ├── testTestConnectionWhenHealthy()
│   ├── testTestConnectionWithError()
│   └── testCustomMockResponse()
│
├── DmnDatabaseTest.php               # 4 tests - Database operations
│   ├── testLogEvaluation()
│   ├── testLogPerformanceMetrics()
│   ├── testGetEvaluations()
│   └── testCleanupOldData()
│
├── ErrorHandlingTest.php             # 2 tests - Error handling
│   ├── testNetworkTimeoutHandling()
│   └── testInvalidDataHandling()
│
├── PerformanceTest.php               # 3 tests - Performance benchmarking
│   ├── testSingleEvaluationPerformance()
│   ├── testMultipleEvaluationsPerformance()
│   └── testMemoryUsage()
│
├── SecurityTest.php                  # 4 tests - Security validation
│   ├── testSqlInjectionPrevention()
│   ├── testXssPrevention()
│   ├── testApiKeyValidation()
│   └── testDataSanitizationEdgeCases()
│
└── ValidationTest.php                # 3 tests - Input validation
    ├── testEmailValidation()
    ├── testAgeValidation()
    └── testIncomeValidation()
```

#### **Integration Tests (3 tests)**
```
tests/integration/
└── FormSubmissionTest.php           # 3 tests - Multi-component workflow
    ├── testCompleteFormSubmissionFlow()
    ├── testErrorHandlingInIntegration()
    └── testMultipleFormsIntegration()
```

### **🌐 E2E Tests (16 total tests)**

#### **Cypress Tests (6 tests, 3s execution)**
```
tests/e2e/cypress/
├── e2e/
│   └── dmn-keyless-api.cy.js         # 6 tests - Live environment testing
│       ├── should connect to the test environment
│       ├── should check if DMN plugin directory is accessible
│       ├── should test DMN health endpoint
│       ├── should test basic DMN evaluation without API key
│       ├── should test various evaluation scenarios
│       └── should handle malformed requests gracefully
│
├── support/
│   ├── commands.js                   # Custom commands
│   │   ├── testDMNEvaluation()
│   │   ├── checkDMNHealth()
│   │   └── mockDMNResponse()
│   └── e2e.js                        # Global configuration
│
└── fixtures/
    └── example.json                  # Test data
```

#### **Playwright Tests (10 tests, 14.6s execution, 2 browsers)**
```
tests/e2e/playwright/
├── playwright.config.js             # Configuration for cross-browser testing
└── dmn-workflow.spec.js             # 10 tests (5 per browser)
    ├── should connect to live environment
    ├── should test DMN API test endpoint
    ├── should test DMN evaluation with correct format
    ├── should test WordPress REST API namespace discovery
    └── should check available DMN endpoints
```

### **🛠️ Test Support Files**

#### **Test Helpers & Utilities**
```
tests/
├── fixtures/
│   └── mock-classes.php              # Mock implementations
│       ├── MockDmnApi                # DMN API mock
│       └── MockDmnDatabase           # Database mock
│
├── helpers/
│   └── test-helper.php               # Test utilities
│       ├── mockDmnResponse()
│       ├── createTestForm()
│       ├── createTestEntry()
│       └── generateEvaluationHistory()
│
├── bootstrap.php                     # PHPUnit initialization
└── README.md                         # This test suite documentation
```

#### **Configuration Files**
```
Root Directory:
├── cypress.config.js                # Cypress E2E configuration
├── package.json                     # Node.js scripts and dependencies
├── phpunit.xml                      # PHPUnit test configuration
├── composer.json                    # PHP scripts and dependencies
└── .gitattributes                   # Line ending configuration
```

### **📊 Generated Reports & Artifacts**

#### **Test Results**
```
Root Directory:
├── junit.xml                        # PHPUnit JUnit XML results
├── test-results/
│   └── junit-playwright.xml         # Playwright JUnit XML results
│
├── playwright-report/               # Playwright HTML reports
│   ├── index.html                   # Main report
│   ├── trace files                  # Execution traces
│   └── screenshots                  # Test screenshots
│
└── tests/e2e/cypress/
    ├── screenshots/                  # Cypress failure screenshots
    └── videos/                       # Cypress test videos
```

## 🎯 **Key Features of This Structure**

### **✅ Organized by Test Type**
- **Unit**: Individual component testing
- **Integration**: Multi-component workflow testing
- **E2E**: End-to-end browser testing (Cypress + Playwright)

### **✅ Framework Separation**
- **PHPUnit**: Server-side PHP testing
- **Cypress**: Single-browser E2E testing
- **Playwright**: Cross-browser E2E testing

### **✅ Support Infrastructure**
- **Mocks**: Consistent test data and API mocking
- **Helpers**: Reusable test utilities
- **Fixtures**: Test data management
- **Configuration**: Properly configured test runners

### **✅ Reporting & Artifacts**
- **JUnit XML**: CI/CD integration
- **HTML Reports**: Human-readable test results
- **Screenshots/Videos**: Visual test debugging
- **Performance Metrics**: Execution time tracking

## 📝 **File Content Summary**

### **Configuration Files**

#### **`cypress.config.js`** (Root)
- Base URL: `https://owc-gemeente.test.open-regels.nl`
- Support file path configuration
- Timeout and retry settings
- Video and screenshot capture

#### **`tests/e2e/playwright/playwright.config.js`**
- Cross-browser configuration (Chromium + Firefox)
- Parallel execution settings
- Report generation configuration
- Trace and screenshot settings

#### **`phpunit.xml`**
- Test directory specification
- Bootstrap file configuration
- Coverage reporting setup
- JUnit XML output configuration

#### **`package.json`** (E2E Scripts)
```json
{
  "scripts": {
    "cypress:open": "cypress open",
    "cypress:run": "cypress run",
    "playwright:test": "playwright test --config=tests/e2e/playwright/playwright.config.js",
    "playwright:ui": "playwright test --config=tests/e2e/playwright/playwright.config.js --ui",
    "test:e2e": "npm run cypress:run",
    "test:e2e:playwright": "npm run playwright:test",
    "test:e2e:all": "npm run cypress:run && npm run playwright:test"
  }
}
```

#### **`composer.json`** (PHP Scripts)
```json
{
  "scripts": {
    "test": "phpunit tests/",
    "test:unit": "phpunit tests/unit/",
    "test:integration": "phpunit tests/integration/",
    "test:ci": "phpunit tests/ --log-junit junit.xml",
    "ci": "composer run test:ci && composer run security",
    "quality": "composer run lint:summary && composer run security"
  }
}
```

## 🚀 **Usage Examples**

### **Run All Tests**
```bash
# PHP Tests
composer run test                     # All PHP tests (29 tests, ~33ms)
composer run test:unit                # Unit tests only (26 tests)
composer run test:integration         # Integration tests only (3 tests)

# E2E Tests
npm run cypress:run                   # Cypress E2E (6 tests, ~3s)
npm run playwright:test               # Playwright E2E (10 tests, ~14.6s)
npm run test:e2e:all                  # Both E2E frameworks

# Quality & CI
composer run ci                       # Tests + security scan
composer run quality                  # Tests + linting + security
```

### **Development Workflow**
```bash
# Daily development
composer run ci && npm run test:e2e

# Before commits
composer run format && composer run check && npm run test:e2e:all

# Debug E2E tests
npm run cypress:open                  # Cypress GUI
npm run playwright:ui                 # Playwright GUI
```

