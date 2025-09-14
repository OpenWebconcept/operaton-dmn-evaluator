# DMN REST API Integration Tests

## Overview

This document provides a detailed description of the `RestApiIntegrationTest.php` file, which is a comprehensive test suite for the Operaton DMN REST API integration with WordPress. The test class extends PHPUnit's `TestCase` and covers decision evaluation, definitions, deployments, and engine information.

## Test Class Configuration

### Setup and Environment
The test class initializes with configurable environment variables:

- **Base URL**: `DMN_TEST_URL` (default: `https://owc-gemeente.test.open-regels.nl`)
- **DMN Engine URL**: `DMN_ENGINE_URL` (default: `http://localhost:8080`)
- **API Key**: `DMN_API_KEY` (optional)
- **Environment File**: `.env.testing` (auto-created if missing)

### HTTP Clients
- **WordPress Client**: Tests WordPress REST API endpoints
- **DMN Engine Client**: Tests direct Operaton DMN engine endpoints

## Detailed Test Analysis

### 1. Environment and Configuration Tests

#### `testEnvironmentConfiguration()`
**Purpose**: Validates environment variable configuration
**Assertions**:
- `assertNotEmpty($this->dmnEngineUrl)` - DMN_ENGINE_URL must be configured
- `assertNotEmpty($this->baseUrl)` - DMN_TEST_URL must be configured  
- `assertTrue(filter_var($this->baseUrl, FILTER_VALIDATE_URL) !== false)` - DMN_TEST_URL must be valid URL
- `assertTrue(filter_var($this->dmnEngineUrl, FILTER_VALIDATE_URL) !== false)` - DMN_ENGINE_URL must be valid URL

### 2. WordPress REST API Tests

#### `testWordPressRestApiAccessibility()`
**Purpose**: Tests basic WordPress REST API functionality
**Assertions**:
- `assertEquals(200, $response->getStatusCode())` - WordPress REST API should be accessible
- `assertIsArray($body)` - REST API should return valid JSON
- `assertArrayHasKey('namespaces', $body)` - REST API should include namespaces

#### `testDmnNamespaceDiscovery()`
**Purpose**: Discovers DMN plugin namespace in WordPress REST API
**Assertions**:
- `assertTrue(true)` - Positive assertion when namespace found
- `markTestIncomplete()` - When namespace not found (informational only)

#### `testBasicConnectivity()`
**Purpose**: Basic connectivity test that should always pass
**Assertions**:
- `assertLessThan(600, $response->getStatusCode())` - Should get valid HTTP response
- `assertGreaterThanOrEqual(200, $response->getStatusCode())` - Should get valid HTTP response

### 3. DMN Plugin Health and Status Tests

#### `testDmnHealthEndpoint()`
**Purpose**: Tests DMN plugin health endpoint
**Assertions**:
- `assertArrayHasKey('status', $body)` - Health response should have status field (when successful)
- `assertContains($response->getStatusCode(), [404, 405, 500])` - Should return valid HTTP status codes

#### `testDmnTestEndpoint()`
**Purpose**: Tests DMN plugin test endpoint for version and status
**Assertions**:
- `assertArrayHasKey('status', $body)` - Test response should have status field (when successful)
- `assertContains($response->getStatusCode(), [404, 405, 500])` - Should return valid HTTP status codes

#### `testDmnHealthEndpointDetailed()`
**Purpose**: Detailed health endpoint testing with error handling
**Assertions**:
- `assertIsInt($statusCode)` - Should return valid HTTP status code
- `assertIsArray($body)` - Response should be valid JSON array (when successful)
- `assertArrayHasKey('status', $body)` - Health response should have status field
- `assertContains($statusCode, [200, 404, 405, 500])` - Should return valid HTTP status codes
- `markTestIncomplete()` - For various error conditions (database issues, HTML responses, etc.)

### 4. Operaton DMN Engine Tests

#### `testOperatonEngineVersion()`
**Purpose**: Tests DMN engine version endpoint
**Assertions**:
- `assertArrayHasKey('version', $body)` - Response should contain version field
- `assertMatchesRegularExpression('/^\d+\.\d+/', $body['version'])` - Version should be in semantic format
- `markTestIncomplete()` - When engine not accessible

#### `testEngineList()`
**Purpose**: Tests available engines endpoint
**Assertions**:
- `assertIsArray($body)` - Engine list should be an array
- `markTestIncomplete()` - When engine list not accessible

#### `testDecisionDefinitionList()`
**Purpose**: Tests decision definition list endpoint
**Assertions**:
- `assertIsArray($body)` - Decision definition list should be an array
- `assertGreaterThan(0, count($body))` - Should have at least one decision definition
- `markTestIncomplete()` - When decision definitions not accessible

#### `testDecisionDefinitionByKey()`
**Purpose**: Tests specific decision definition retrieval (dish example)
**Assertions**:
- `assertArrayHasKey('id', $body)` - Response should have ID field
- `assertArrayHasKey('key', $body)` - Response should have key field
- `assertEquals('dish', $body['key'])` - Key should match requested 'dish'
- `assertContains($response->getStatusCode(), [404, 500])` - Should handle errors appropriately

#### `testDecisionDefinitionXml()`
**Purpose**: Tests decision definition XML retrieval
**Assertions**:
- `assertArrayHasKey('dmnXml', $body)` - Response should contain XML field
- `assertStringContainsString('<?xml', $xml)` - Should contain XML declaration
- `assertStringContainsString('definitions', $xml)` - Should contain DMN definitions
- `assertStringContainsString('dish', $xml)` - Should contain dish decision logic
- `markTestIncomplete()` - When XML not accessible

#### `testDeploymentList()`
**Purpose**: Tests deployment list endpoint
**Assertions**:
- `assertIsArray($body)` - Deployment list should be an array
- `markTestIncomplete()` - When deployment list not accessible

### 5. DMN Evaluation Tests

#### `testDirectDmnServiceConnectivity()`
**Purpose**: Tests direct DMN evaluation with multiple scenarios
**Test Scenarios**:
- Summer + 8 guests → light salad
- Winter + 4 guests → roastbeef  
- Fall + 6 guests → spareribs
- Spring + 3 guests → gourmet steak

**Assertions**:
- `assertGreaterThan(0, $successCount)` - At least one DMN scenario should work
- `markTestIncomplete()` - When no scenarios work

#### `testDmnEvaluationWithPluginApi()`
**Purpose**: Tests DMN evaluation through WordPress plugin API
**Assertions**:
- `assertIsInt($response->getStatusCode())` - Should return valid HTTP status code
- `assertIsArray($body)` - Response should be valid JSON array (when successful)
- `assertEquals(404, $response->getStatusCode())` - Should return 404 when config not found
- `assertArrayHasKey('results', $body)` - Successful response should have results
- `assertContains($response->getStatusCode(), [200, 400, 404, 422, 500])` - Should handle appropriately
- `markTestIncomplete()` - When plugin API requires configuration

#### `testDmnEvaluationErrorHandling()`
**Purpose**: Tests error handling with invalid evaluation data
**Test Scenarios**:
- Missing required variables
- Invalid variable types
- Empty variables

**Assertions**:
- `assertGreaterThan(0, $errorHandlingCount)` - Should handle errors appropriately

### 6. DMN History Tests

#### `testDmnHistoryQuery()`
**Purpose**: Tests historic decision instances retrieval
**Assertions**:
- `assertIsArray($body)` - History should be an array
- `markTestIncomplete()` - When history endpoint not available

### 7. Security Tests

#### `testSecurityMalformedRequests()`
**Purpose**: Tests security with malicious payloads
**Test Scenarios**:
- SQL injection attempts
- XSS attempts  
- Buffer overflow attempts
- JSON injection

**Assertions**:
- `assertGreaterThan(0, $secureCount)` - At least some malicious requests should be handled securely

#### `testContentTypeValidation()`
**Purpose**: Tests content type validation
**Assertions**:
- `assertContains($response->getStatusCode(), [400, 415, 422, 500])` - Should reject invalid content type

### 8. Performance Tests

#### `testApiPerformanceWithCorrectFormat()`
**Purpose**: Tests API performance with multiple requests
**Performance Metrics**:
- 3 sequential requests
- Total execution time
- Average response time
- Success rate

**Assertions**:
- `assertLessThan(10, $totalTime)` - API should handle multiple requests efficiently
- `assertLessThan(5, $avgResponseTime)` - Individual requests should be reasonably fast
- `markTestIncomplete()` - When performance test needs DMN configuration

### 9. Configuration Tests

#### `testCreateTestConfiguration()`
**Purpose**: Validates test configuration structure
**Configuration Fields**:
- name, form_id, dmn_endpoint
- decision_key, field_mappings
- result_mappings, evaluation_step
- button_text, use_process

**Assertions**:
- `assertIsArray($testConfigData)` - Configuration should be array
- `assertArrayHasKey('name', $testConfigData)` - Should have name field
- `assertArrayHasKey('dmn_endpoint', $testConfigData)` - Should have endpoint field
- `assertEquals($this->dmnEngineUrl . '/engine-rest/decision-definition/key/', $testConfigData['dmn_endpoint'])` - Endpoint should match configured URL

## Test Execution Flow

### Environment Setup
1. Load `.env.testing` file (create if missing)
2. Configure HTTP clients for WordPress and DMN engine
3. Display configuration information

### Test Categories
1. **Environment Validation** - Ensures proper configuration
2. **WordPress Integration** - Tests plugin integration with WordPress
3. **DMN Engine Connectivity** - Tests direct engine communication  
4. **Decision Evaluation** - Tests actual DMN decision processing
5. **Security & Error Handling** - Tests resilience and security
6. **Performance** - Tests response times and efficiency

### Error Handling Strategy
- Use `markTestIncomplete()` for missing configurations or services
- Use `assertContains()` for acceptable error response codes
- Always make assertions to avoid risky tests
- Provide detailed diagnostic output
