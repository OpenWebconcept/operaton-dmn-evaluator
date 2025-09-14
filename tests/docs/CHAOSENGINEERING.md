# Chaos Engineering Tests Analysis for Operaton DMN

## Overview

This document provides a detailed description of the `chaos-engineering.js` file, which implements a comprehensive chaos engineering test suite for the Operaton DMN Evaluator WordPress integration. The tests are designed to validate system resilience, error handling, security measures, and performance under various stress conditions.

## Test Architecture

### Class Structure
The main class `SimpleDmnChaosEngineering` orchestrates all chaos tests with the following key components:

- **Environment Configuration**: Uses `.env.testing` for consistent configuration
- **HTTP Client Management**: Axios-based requests with configurable timeouts and retries
- **Result Tracking**: Comprehensive logging and reporting of all test outcomes
- **Error Handling**: Graceful handling of expected and unexpected failures

### Configuration Management

#### Environment Variables
- **DMN_TEST_URL**: WordPress test environment (default: `https://owc-gemeente.test.open-regels.nl`)
- **DMN_ENGINE_URL**: Operaton DMN engine endpoint (default: `http://localhost:8080`)
- **DMN_API_KEY**: Optional API authentication key
- **TEST_ENV**: Environment designation (development/staging/production)

#### Default Configuration
- **Timeout**: 30 seconds for HTTP requests
- **Retries**: 3 attempts for failed requests
- **Auto-creation**: `.env.testing` file created if missing

## Test Categories and Detailed Analysis

### 1. Baseline Health Tests

#### Purpose
Establishes system health before chaos testing begins. All tests must verify basic functionality.

#### Test Scenarios

##### `testWordPressApi()`
**Endpoint**: `/wp-json/`
**Method**: GET
**Validation**: HTTP 200 response
**Purpose**: Verifies WordPress REST API accessibility

##### `testDmnPluginDetection()`
**Endpoint**: `/wp-json/operaton-dmn/v1/test`
**Method**: GET
**Validation**: HTTP 200 or 404 (404 acceptable for missing plugin)
**Purpose**: Confirms DMN plugin REST API endpoints

##### `testDmnEngineConnectivity()`
**Endpoint**: `{DMN_ENGINE_URL}/engine-rest/version`
**Method**: GET
**Timeout**: 5 seconds
**Validation**: HTTP 200 response
**Purpose**: Verifies Operaton DMN engine availability

#### Health Criteria
- **Minimum Requirement**: At least 1 out of 3 baseline tests must pass
- **Failure Behavior**: Chaos testing aborted if baseline fails

### 2. Network Chaos Tests

#### `testTimeoutHandling()`
**Purpose**: Tests system behavior under various timeout conditions

**Test Matrix**:
- 1000ms timeout - Tests quick response handling
- 5000ms timeout - Tests moderate delays
- 10000ms timeout - Tests extended processing times

**Evaluation Criteria**:
- Successful responses within timeout window
- Proper timeout error handling (ECONNABORTED)
- Graceful degradation under time pressure

**Expected Behaviors**:
- Fast timeouts may fail appropriately
- Longer timeouts should succeed for healthy systems
- No system crashes or hangs

### 3. Data Validation Chaos Tests

#### `testInvalidData()`
**Purpose**: Validates input sanitization and error handling

**Test Payloads**:

##### Empty Object Test
```json
{}
```
**Expected**: HTTP 400-499 error (missing required fields)

##### Invalid JSON Structure
```json
{
  "invalid": "structure"
}
```
**Expected**: HTTP 400-499 error (schema validation failure)

##### Missing Required Fields
```json
{
  "form_data": {
    "age": 30
  }
}
```
**Expected**: HTTP 400-499 error (missing config_id)

##### Wrong Data Types
```json
{
  "config_id": "not_a_number",
  "form_data": "not_an_object"
}
```
**Expected**: HTTP 400-499 error (type validation failure)

**Success Criteria**: All invalid payloads properly rejected with 4xx status codes

### 4. Security Chaos Tests

#### `testMaliciousInput()`
**Purpose**: Tests security measures against common attack vectors

**Attack Scenarios**:

##### SQL Injection Attack
```json
{
  "config_id": "1'; DROP TABLE wp_posts; --",
  "form_data": {
    "age": "30'; DELETE FROM wp_users; --"
  }
}
```
**Target**: Database injection prevention
**Expected**: Blocked or sanitized input

##### XSS (Cross-Site Scripting) Attack
```json
{
  "config_id": 1,
  "form_data": {
    "name": "<script>alert('xss')</script>",
    "email": "test@example.com<img src=x onerror=alert(1)>"
  }
}
```
**Target**: Script injection prevention
**Expected**: HTML sanitization or rejection

##### Command Injection Attack
```json
{
  "config_id": 1,
  "form_data": {
    "command": "; rm -rf /"
  }
}
```
**Target**: System command execution prevention
**Expected**: Command sanitization or rejection

**Security Evaluation**:
- HTTP 400/422 responses indicate proper blocking
- HTTP 200 responses require manual verification of sanitization
- Any system compromise indicates security failure

### 5. Performance Chaos Tests

#### `testLargePayloads()`
**Purpose**: Tests system behavior with oversized data

**Payload Sizes**:
- **10KB**: Normal large form data
- **100KB**: Very large form data
- **1000KB (1MB)**: Extreme payload size

**Test Structure**:
```json
{
  "config_id": 1,
  "form_data": {
    "large_field": "x".repeat(size_in_bytes),
    "age": 30
  }
}
```

**Performance Metrics**:
- Response time measurement
- Memory usage behavior
- Server stability under load
- Payload size limits enforcement

**Expected Behaviors**:
- Small payloads should process quickly
- Large payloads may be rejected or processed slowly
- System should not crash or become unresponsive

### 6. Concurrency Chaos Tests

#### `testConcurrentRequests()`
**Purpose**: Tests system behavior under simultaneous load

**Test Parameters**:
- **Concurrent Count**: 5 simultaneous requests
- **Target Endpoint**: `/wp-json/operaton-dmn/v1/test`
- **Execution**: Promise.all() for true concurrency

**Success Metrics**:
- Number of successful responses
- Response time distribution
- No race conditions or data corruption
- Server stability maintained

**Expected Outcomes**:
- Most requests should succeed
- Response times may vary under load
- No system locks or deadlocks

### 7. DMN Engine Resilience Tests

#### `testDmnEngineResilience()`
**Purpose**: Tests DMN engine stability and error handling

**Test Matrix**:

##### Engine Version Check
**Endpoint**: `/engine-rest/version`
**Method**: GET
**Expected**: HTTP 200 with version information

##### Decision Definition List
**Endpoint**: `/engine-rest/decision-definition`
**Method**: GET
**Expected**: HTTP 200 with definition array

##### Invalid DMN Evaluation
**Endpoint**: `/engine-rest/decision-definition/key/nonexistent/evaluate`
**Method**: POST
**Payload**: `{ variables: {} }`
**Expected**: HTTP 404 (expected error for nonexistent decision)

**Resilience Evaluation**:
- Engine remains responsive during tests
- Proper error codes for invalid operations
- No engine crashes or memory leaks

### 8. Endpoint Security Tests

#### `testInvalidEndpoints()`
**Purpose**: Tests protection against unauthorized access attempts

**Attack Endpoints**:

##### Nonexistent Endpoint
`/wp-json/operaton-dmn/v1/nonexistent`
**Expected**: HTTP 404

##### Admin Secrets Access
`/wp-json/operaton-dmn/v1/admin/secrets`
**Expected**: HTTP 403/404 (blocked access)

##### Path Traversal Attack
`/wp-json/operaton-dmn/v1/../../../wp-config.php`
**Expected**: HTTP 403/404 (path traversal blocked)

##### Version Confusion Attack
`/wp-json/operaton-dmn/v999/test`
**Expected**: HTTP 404 (invalid API version)

**Security Validation**:
- All unauthorized endpoints properly blocked
- No sensitive information leaked
- Path traversal attacks prevented

## Test Execution Flow

### 1. Initialization Phase
1. Load environment configuration from `.env.testing`
2. Validate configuration parameters
3. Display test environment information
4. Execute baseline health checks
5. Verify system readiness for chaos testing

### 2. Chaos Testing Phase
Sequential execution of chaos scenarios:
1. Network chaos (timeouts)
2. Data validation chaos (invalid inputs)
3. Security chaos (malicious inputs)
4. Performance chaos (large payloads)
5. DMN engine chaos (resilience tests)
6. Concurrency chaos (simultaneous requests)
7. Endpoint security chaos (unauthorized access)

### 3. Reporting Phase
1. Aggregate all test results
2. Calculate success/failure rates
3. Generate comprehensive report
4. Save detailed results to JSON file
5. Exit with appropriate status code

## Success Criteria and Thresholds

### Overall System Health
- **Excellent**: <20% failure rate (exit code 0)
- **Acceptable**: 20-50% failure rate (exit code 0)
- **Poor Resilience**: >50% failure rate (exit code 1)

### Individual Test Criteria
- **Security Tests**: All attacks must be blocked or sanitized
- **Performance Tests**: Response times under reasonable thresholds
- **Concurrency Tests**: Majority of concurrent requests succeed
- **Data Validation**: All invalid data properly rejected
- **Engine Tests**: Core functionality remains available

## Output and Reporting

### Console Output
- Real-time test execution status
- Individual test results with emojis (✅/❌)
- Performance metrics (response times)
- Detailed error messages for failures

### JSON Report Structure
```json
{
  "summary": {
    "totalScenarios": 7,
    "successfulScenarios": 6,
    "failedScenarios": 1,
    "successRate": 85.7
  },
  "results": [
    {
      "scenario": "Test Name",
      "success": true,
      "duration": 1250,
      "result": { /* detailed results */ },
      "timestamp": "2025-09-14T12:00:00.000Z"
    }
  ],
  "timestamp": "2025-09-14T12:00:00.000Z"
}
```

### Report File Management
- **Directory**: `test-results/`
- **Naming**: `chaos-engineering-{timestamp}.json`
- **Auto-creation**: Directory created if missing
- **Persistence**: All test runs preserved for analysis

## Error Handling Strategy

### Expected Errors
- HTTP 404 for missing endpoints (acceptable)
- HTTP 400-499 for invalid data (desired behavior)
- Timeouts for slow operations (acceptable)
- Engine errors for invalid DMN operations (expected)

### Unexpected Errors
- System crashes or hangs
- Memory leaks or resource exhaustion
- Data corruption or race conditions
- Security bypasses or information leakage

### Recovery Mechanisms
- 1-second pause between scenarios
- Timeout protection for all requests
- Graceful error handling and logging
- Continuation despite individual test failures

## Usage Instructions

### Command Line Execution
```bash
# Default development environment
node tests/chaos/chaos-engineering.js

# Specific environment
node tests/chaos/chaos-engineering.js staging
node tests/chaos/chaos-engineering.js production
```

### Prerequisites
- Node.js with axios package
- `.env.testing` file (auto-created if missing)
- Accessible WordPress installation
- Running Operaton DMN engine (for full tests)
