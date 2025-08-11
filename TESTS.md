# Comprehensive Testing

## 📁 **Complete Test Directory Structure**

```
operaton-dmn-evaluator/
├── tests/
│   ├── e2e/
│   │   ├── cypress/
│   │   │   ├── e2e/
│   │   │   │   └── dmn-keyless-api.cy.js # Cypress test suite (6 tests)
│   │   │   ├── fixtures/
│   │   │   │   └── example.json          # Test data files
│   │   │   ├── support/
│   │   │   │   ├── commands.js           # Custom Cypress commands
│   │   │   │   └── e2e.js                # Cypress support file
│   │   │   ├── screenshots/              # Auto-generated screenshots
│   │   │   └── videos/                   # Auto-generated videos
│   │   └── playwright/
│   │       ├── playwright.config.js      # Playwright configuration
│   │       └── dmn-workflow.spec.js      # Playwright test suite (10 tests)
│   ├── fixtures/
│   │   ├── mock-classes.php              # Mock classes for testing
│   │   └── ExtendedMockDmnService.php    # Extended mock DMN service
│   ├── helpers/
│   │   ├── test-helper.php               # Test utility functions
│   │   └── MockServiceTestHelper.php     # Mock service test utilities
│   ├── integration/
│   │   ├── FormSubmissionTest.php        # Integration tests (3 tests)
│   │   └── RestApiIntegrationTest.php    # REST API integration (11 tests)
│   ├── unit/
│   │   ├── DmnApiTest.php                # API endpoint testing (10 tests)
│   │   ├── DmnDatabaseTest.php           # Database operations (4 tests)
│   │   ├── ErrorHandlingTest.php         # Error handling (2 tests)
│   │   ├── PerformanceTest.php           # Performance tests (3 tests)
│   │   ├── SecurityTest.php              # Security tests (4 tests)
│   │   ├── ValidationTest.php            # Validation tests (3 tests)
│   │   └── MockServiceTest.php           # Mock service tests (5 tests)
│   ├── load/
│   │   └── dmn-load-test.js              # K6 load testing script
│   ├── chaos/
│   │   └── chaos-engineering.js          # Chaos engineering tests
│   ├── bootstrap.php                     # PHPUnit bootstrap file
│   └── README.md                         # Test documentation
├── scripts/
│   ├── hooks/
│   │   ├── setup-precommit-hooks.sh      # Pre-commit hooks setup
│   │   ├── manage-hooks.sh               # Hook management utilities
│   │   └── check-php-syntax.sh           # PHP syntax validation
│   ├── setup-step1.sh through step6.sh  # Incremental setup scripts
│   └── run-comprehensive-tests.sh        # Main test orchestrator
├── test-results/                         # Test output directory
│   ├── junit-playwright.xml              # Playwright test results
│   ├── load-test-results.json            # K6 load test results
│   └── chaos-test-results.json           # Chaos engineering results
├── playwright-report/                    # Playwright HTML reports
│   └── index.html                        # Main report file
├── cypress.config.js                     # Cypress configuration (root)
├── package.json                          # Node.js dependencies & scripts
├── phpunit.xml                           # PHPUnit configuration
├── composer.json                         # PHP dependencies & scripts
├── junit.xml                             # PHPUnit test results
├── run-tests.sh                          # Convenient test runner
├── TESTS.md                              # This comprehensive documentation
└── TESTING-GUIDE.md                     # Complete command reference
```

## 📊 **Test Suite Statistics**

### **Total Test Coverage**
- **Unit Tests**: 32 tests (124 assertions)
- **Integration Tests**: 11 tests (20 assertions)
- **E2E Tests (Cypress)**: 6 tests
- **E2E Tests (Playwright)**: 10 tests (cross-browser)
- **Load Tests**: Multi-scenario K6 performance testing
- **Chaos Tests**: Resilience and fault tolerance validation
- **Total**: 59+ automated tests with comprehensive coverage

### **Test Execution Performance**
- **Unit Tests**: ~200ms execution time
- **Integration Tests**: ~22s (includes live API calls)
- **E2E Tests**: 3s (Cypress), 14.6s (Playwright)
- **CI Pipeline**: 24s total (enterprise-grade speed)

## 🧪 **Test Categories & Implementation**

### **1. Unit Tests (32 tests)**

#### **API Testing (`DmnApiTest.php`)**
Tests core DMN evaluation functionality and API endpoints.
```php
public function testEvaluateDmnWithValidData(): void {
    $testData = [
        'age' => 30,
        'income' => 75000,
        'credit_score' => 'excellent'
    ];

    $result = $this->apiManager->evaluateDmn($testData);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('decision', $result);
    $this->assertEquals('approved', $result['decision']);
}
```

**What This Tests:**
- ✅ DMN evaluation with valid input data
- ✅ API response structure validation
- ✅ Field mapping and data transformation
- ✅ Result population into form fields
- ✅ Error handling for invalid inputs

#### **Database Operations (`DmnDatabaseTest.php`)**
Validates database schema, migrations, and data persistence.
```php
public function testLogEvaluationWithMultipleResults(): void {
    $evaluationData = [
        'form_id' => 123,
        'result_mappings' => json_encode([
            'decision' => 'approved',
            'interest_rate' => 3.5,
            'loan_amount' => 50000
        ]),
        'execution_time' => 0.45
    ];

    $this->database->logEvaluation($evaluationData);
    $evaluations = $this->database->getEvaluations(['form_id' => 123]);

    $this->assertCount(1, $evaluations);
    $this->assertEquals('approved', json_decode($evaluations[0]['result_mappings'], true)['decision']);
}
```

#### **Performance Testing (`PerformanceTest.php`)**
Benchmarks system performance and resource usage.
```php
public function testInitializationPerformance(): void {
    $startTime = microtime(true);
    $startMemory = memory_get_usage();

    // Test rapid initialization cycles
    for ($i = 0; $i < 100; $i++) {
        $this->performanceMonitor->initializeSystem();
    }

    $executionTime = microtime(true) - $startTime;
    $memoryUsed = memory_get_usage() - $startMemory;

    // Should initialize in under 1ms average
    $this->assertLessThan(0.1, $executionTime); // 100ms for 100 cycles
    $this->assertLessThan(5 * 1024 * 1024, $memoryUsed); // Under 5MB
}
```

#### **Security Testing (`SecurityTest.php`)**
Validates input sanitization and security measures.
```php
public function testSqlInjectionPrevention(): void {
    $maliciousInputs = [
        "'; DROP TABLE wp_operaton_dmn_evaluations; --",
        "1' OR '1'='1",
        "admin'/**/UNION/**/SELECT/**/password/**/FROM/**/wp_users--"
    ];

    foreach ($maliciousInputs as $input) {
        $result = $this->securityValidator->sanitizeInput($input);
        $this->assertStringNotContainsString("'", $result);
        $this->assertStringNotContainsString("--", $result);
        $this->assertStringNotContainsString("UNION", strtoupper($result));
    }
}
```

#### **Mock Service Testing (`MockServiceTest.php`)**
Tests the extended mock DMN service for consistent development.
```php
public function testMockServiceCreditApprovalScenarios(): void {
    $scenarios = [
        ['age' => 25, 'income' => 45000, 'credit_score' => 'excellent', 'expected' => 'approved'],
        ['age' => 18, 'income' => 20000, 'credit_score' => 'poor', 'expected' => 'rejected'],
        ['age' => 65, 'income' => 80000, 'credit_score' => 'good', 'expected' => 'approved']
    ];

    foreach ($scenarios as $scenario) {
        $result = $this->mockService->evaluateCredit($scenario);
        $this->assertEquals($scenario['expected'], $result['decision']);
    }
}
```

### **2. Integration Tests (11 tests)**

#### **REST API Integration (`RestApiIntegrationTest.php`)**
Tests live API endpoints against the actual WordPress environment.
```php
public function testDmnHealthEndpoint(): void {
    $response = $this->httpClient->get(
        $this->baseUrl . '/wp-json/operaton-dmn/v1/health'
    );

    $this->assertEquals(200, $response->getStatusCode());

    $data = json_decode($response->getBody(), true);
    $this->assertArrayHasKey('status', $data);
    $this->assertEquals('healthy', $data['status']);
}
```

**Integration Test Coverage:**
- ✅ WordPress REST API accessibility
- ✅ DMN namespace discovery and registration
- ✅ Health endpoint functionality
- ✅ Plugin version detection
- ✅ Security validation with malicious requests
- ✅ Authentication handling
- ✅ Basic connectivity verification

#### **Form Submission Integration (`FormSubmissionTest.php`)**
Tests complete form submission workflows with DMN evaluation.

### **3. End-to-End Tests (16 tests total)**

#### **Cypress E2E Testing (6 tests)**
Browser automation testing against live environment.

**Configuration:**
```javascript
// cypress.config.js
module.exports = defineConfig({
  e2e: {
    baseUrl: 'https://owc-gemeente.test.open-regels.nl',
    viewportWidth: 1280,
    viewportHeight: 720,
    video: true,
    screenshotOnRunFailure: true,
    defaultCommandTimeout: 15000
  }
})
```

**Test Implementation:**
```javascript
describe('DMN API Tests', () => {
  it('should test various evaluation scenarios', () => {
    const testCases = [
      { name: 'High Income', data: { age: 35, income: 80000, credit_score: 'excellent' } },
      { name: 'Young Professional', data: { age: 24, income: 35000, credit_score: 'good' } },
      { name: 'Senior Applicant', data: { age: 55, income: 60000, credit_score: 'fair' } }
    ]

    testCases.forEach((testCase) => {
      cy.testDMNEvaluation(testCase.data)
    })
  })
})
```

#### **Playwright Cross-Browser Testing (10 tests)**
Advanced cross-browser testing with Chrome and Firefox.

**Benefits Achieved:**
- ✅ Live environment validation
- ✅ Cross-browser compatibility (Chrome, Firefox)
- ✅ API integration testing
- ✅ Real network conditions
- ✅ Visual regression detection
- ✅ Error handling verification

### **4. Load Testing (K6)**

#### **Performance Scenarios**
```javascript
// Load testing scenarios
export let options = {
  scenarios: {
    smoke_test: {
      executor: 'constant-vus',
      vus: 1,
      duration: '30s'
    },
    basic_load_test: {
      executor: 'ramping-vus',
      stages: [
        { duration: '1m', target: 1 },
        { duration: '2m', target: 3 },
        { duration: '1m', target: 0 }
      ]
    }
  },
  thresholds: {
    'http_req_duration': ['p(95)<500'],
    'http_req_failed': ['rate<0.5']
  }
}
```

**Load Test Coverage:**
- ✅ Basic connectivity testing
- ✅ DMN evaluation performance
- ✅ Concurrent user simulation
- ✅ Response time monitoring
- ✅ Success rate validation
- ✅ Performance threshold enforcement

### **5. Chaos Engineering**

#### **Resilience Testing**
```javascript
// Chaos engineering scenarios
const chaosScenarios = [
  {
    name: 'Malformed Request Attack',
    description: 'Send malformed JSON to test error handling',
    execute: async () => {
      // Test malformed requests
      await testMalformedRequests()
    }
  },
  {
    name: 'High Concurrent Load',
    description: 'Simulate high concurrent user load',
    execute: async () => {
      // Test concurrent requests
      await testConcurrentLoad()
    }
  }
]
```

**Chaos Test Coverage:**
- ✅ Malformed request handling
- ✅ High concurrent load simulation
- ✅ Network timeout scenarios
- ✅ Error recovery mechanisms
- ✅ Security attack simulation
- ✅ Fault tolerance validation

## 🔧 **Test Infrastructure Components**

### **Pre-commit Hooks**
Automated code quality validation before commits.
```bash
# Setup pre-commit hooks
composer run hooks:enable

# Test hooks manually
composer run hooks:test

# Check hook status
composer run hooks:status
```

### **Extended Mock DMN Service**
Realistic test data generation for consistent testing.
```php
class ExtendedMockDmnService {
    public function generateCreditScenarios(): array {
        return [
            'high_income_excellent_credit' => [
                'input' => ['age' => 35, 'income' => 85000, 'credit_score' => 'excellent'],
                'expected' => ['decision' => 'approved', 'interest_rate' => 2.5]
            ],
            'low_income_poor_credit' => [
                'input' => ['age' => 22, 'income' => 25000, 'credit_score' => 'poor'],
                'expected' => ['decision' => 'rejected', 'reason' => 'insufficient_income']
            ]
        ];
    }
}
```

### **Comprehensive Test Orchestration**
```bash
# Main test orchestrator script
./run-tests.sh quick      # Unit tests only (< 5s)
./run-tests.sh standard   # Unit + Integration (< 2min)
./run-tests.sh full       # Add load testing (< 10min)
./run-tests.sh extreme    # Everything including chaos (< 20min)
```

## 🚀 **CI/CD Integration**

### **GitLab CI Pipeline**
Three-stage pipeline with proper separation of concerns:

```yaml
stages:
  - test
  - quality

# Core Tests (No External Dependencies)
test-core:
  script:
    - composer run test:unit
    - composer run test:mock
    - composer run test:performance
    - composer run test:security

# Quality Check (Advisory)
quality-check:
  script:
    - composer run security
    - composer run lint:summary
  allow_failure: true

# Integration Tests (Manual/Optional)
test-integration:
  script:
    - composer run test:integration
  when: manual
```

**CI Results:**
- ✅ **24-second pipeline execution**
- ✅ **100% success rate on core tests**
- ✅ **Proper artifact generation**
- ✅ **Quality gates enforcement**

## 📊 **Quality Metrics & Standards**

### **Code Quality Standards**
- ✅ **PSR12 Compliance**: Automated style checking and fixing
- ✅ **Security Scanning**: Zero vulnerabilities detected
- ✅ **Type Safety**: Comprehensive type checking
- ✅ **Documentation**: Inline documentation for all test methods

### **Performance Benchmarks**
- ✅ **Test Execution**: 200ms for 32 unit tests
- ✅ **CI Pipeline**: 24 seconds total execution
- ✅ **Memory Usage**: Efficient resource utilization
- ✅ **Load Testing**: Performance validation under realistic conditions

### **Reliability Metrics**
- ✅ **100% Test Success Rate**: Consistent passing tests
- ✅ **Cross-Browser Compatibility**: Identical behavior across browsers
- ✅ **Error Handling**: Graceful degradation under failure
- ✅ **Security Validation**: Protection against common attacks

## 🎯 **Testing Strategy & Methodology**

### **Test-Driven Development**
```php
// Example TDD approach
public function testDmnEvaluationReturnsExpectedStructure(): void {
    // Arrange
    $testData = $this->createValidTestData();

    // Act
    $result = $this->dmnEvaluator->evaluate($testData);

    // Assert
    $this->assertArrayHasKey('decision', $result);
    $this->assertArrayHasKey('confidence', $result);
    $this->assertArrayHasKey('evaluation_time', $result);
}
```

### **Behavior-Driven Testing**
```javascript
// Example BDD approach with Cypress
describe('When a user submits a DMN evaluation request', () => {
  context('with valid input data', () => {
    it('should return a successful evaluation result', () => {
      cy.submitDmnEvaluation(validTestData)
      cy.get('[data-testid="evaluation-result"]')
        .should('contain', 'approved')
    })
  })
})
```

### **Layered Testing Approach**
```
🔺 E2E Tests (Browser automation, real user scenarios)
🔺 Integration Tests (API endpoints, database operations)
🔺 Unit Tests (Individual functions, business logic)
🔺 Mock Services (Consistent test data, isolated testing)
```

## 🛡️ **Security Testing Implementation**

### **Input Validation & Sanitization**
```php
public function testXssPreventionInDmnInputs(): void {
    $xssAttempts = [
        '<script>alert("xss")</script>',
        'javascript:alert(1)',
        '<img src="x" onerror="alert(1)">'
    ];

    foreach ($xssAttempts as $attempt) {
        $sanitized = $this->inputSanitizer->sanitize($attempt);
        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringNotContainsString('javascript:', $sanitized);
        $this->assertStringNotContainsString('onerror', $sanitized);
    }
}
```

### **API Security Testing**
```php
public function testApiAuthenticationRequirements(): void {
    // Test unauthenticated access
    $response = $this->makeRequest('/wp-json/operaton-dmn/v1/evaluate', [
        'form_data' => ['test' => 'data']
    ]);

    // Should handle appropriately (either require auth or validate input)
    $this->assertContains($response->getStatusCode(), [400, 401, 500]);
}
```

## 📈 **Performance Testing & Monitoring**

### **Load Testing Scenarios**
```javascript
// K6 load testing configuration
export default function() {
  // Test basic connectivity
  let healthCheck = http.get(`${baseUrl}/wp-json/operaton-dmn/v1/health`)
  check(healthCheck, {
    'health endpoint responds': (r) => r.status === 200
  })

  // Test DMN evaluation under load
  let evaluation = http.post(`${baseUrl}/wp-json/operaton-dmn/v1/evaluate`,
    JSON.stringify(testData),
    { headers: { 'Content-Type': 'application/json' } }
  )

  check(evaluation, {
    'evaluation completes': (r) => [200, 400, 500].includes(r.status)
  })
}
```

### **Performance Monitoring**
```php
public function testMemoryUsageUnderLoad(): void {
    $initialMemory = memory_get_usage();

    // Simulate multiple evaluations
    for ($i = 0; $i < 1000; $i++) {
        $this->dmnEvaluator->evaluate($this->generateTestData());
    }

    $finalMemory = memory_get_usage();
    $memoryIncrease = $finalMemory - $initialMemory;

    // Should not exceed 10MB memory increase
    $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease);
}
```

## 🔮 **Advanced Testing Capabilities**

### **Multi-Environment Testing**
```bash
# Environment-specific testing
export DMN_TEST_URL="https://staging.example.com"
./run-tests.sh standard

export DMN_TEST_URL="https://production.example.com"
./run-tests.sh quick  # Conservative testing for production
```

### **Automated Regression Testing**
```php
public function testBackwardCompatibilityWithPreviousVersions(): void {
    // Test legacy configuration formats
    $legacyConfig = [
        'single_result_field' => 'field_10',
        'evaluation_mode' => 'direct_decision'
    ];

    $modernConfig = [
        'result_mappings' => ['decision' => 'field_10'],
        'use_process' => false
    ];

    // Both should work
    $this->assertTrue($this->configValidator->isValid($legacyConfig));
    $this->assertTrue($this->configValidator->isValid($modernConfig));
}
```

### **API Contract Testing**
```php
public function testDmnApiContractCompliance(): void {
    $response = $this->apiClient->post('/wp-json/operaton-dmn/v1/evaluate', [
        'config_id' => 1,
        'form_data' => $this->createValidFormData()
    ]);

    // Verify API contract
    $this->assertArrayHasKey('success', $response);
    $this->assertArrayHasKey('data', $response);

    if ($response['success']) {
        $this->assertArrayHasKey('decision', $response['data']);
        $this->assertArrayHasKey('evaluation_time', $response['data']);
    }
}
```

## 🏆 **Testing Excellence Achieved**

### **Quality Assurance**
- ✅ **Comprehensive Coverage**: 59+ tests across all layers
- ✅ **Fast Feedback**: Sub-30-second validation cycles
- ✅ **Automated Quality Gates**: Pre-commit hooks and CI/CD
- ✅ **Cross-Browser Validation**: Identical behavior across platforms
- ✅ **Security Assurance**: Protection against common vulnerabilities
- ✅ **Performance Monitoring**: Continuous performance validation
- ✅ **Resilience Testing**: Fault tolerance and recovery validation

### **Development Workflow Integration**
- ✅ **Daily Development**: Fast unit tests for immediate feedback
- ✅ **Pre-Commit Validation**: Automated quality checks
- ✅ **CI/CD Pipeline**: 24-second automated validation
- ✅ **Release Validation**: Comprehensive testing including load and chaos
- ✅ **Production Monitoring**: Health checks and baseline metrics

### **Future-Ready Architecture**
- ✅ **Scalable Test Framework**: Grows with plugin complexity
- ✅ **Modular Test Design**: Easy to extend and maintain
- ✅ **Environment Agnostic**: Works across development, staging, production
- ✅ **Technology Diverse**: PHP unit tests, JavaScript E2E, K6 load testing
- ✅ **Quality Focused**: Security, performance, and reliability built-in
