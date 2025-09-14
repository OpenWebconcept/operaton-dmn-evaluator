# K6 Load Testing Analysis for Operaton DMN Evaluator

## Overview

This document provides a detailed description of the `dmn-load-test.js` file, which implements a comprehensive K6 load testing suite for the Operaton DMN Evaluator WordPress integration. The tests are designed to validate system performance, scalability, and reliability under various load conditions using realistic DMN decision scenarios.

## Test Framework Configuration

### Environment Variables
- **DMN_TEST_URL**: WordPress test environment (default: `https://owc-gemeente.test.open-regels.nl`)
- **DMN_ENGINE_URL**: Operaton DMN engine endpoint (default: `https://operaton-dev.open-regels.nl`)
- **DMN_API_KEY**: Optional API authentication key

### Custom Metrics Definition

#### Performance Metrics
- **dmn_evaluation_rate**: Success rate of DMN evaluation requests
- **dmn_evaluation_duration**: Response time trend for DMN evaluations
- **wordpress_api_rate**: WordPress API accessibility success rate
- **dmn_engine_rate**: DMN engine health check success rate

#### Error Tracking Metrics
- **server_error_rate**: Rate of 5xx HTTP status codes
- **client_error_rate**: Rate of 4xx HTTP status codes
- **errorCounter**: Total count of all errors during test execution

## Test Scenarios Analysis

### 1. Smoke Test Scenario
**Executor**: `constant-vus`
**Configuration**:
- Virtual Users: 1
- Duration: 30 seconds
- Purpose: Basic functionality validation

**Validation Criteria**:
- System responds to basic requests
- Core endpoints are accessible
- No critical failures in minimal load

### 2. DMN Evaluation Load Test
**Executor**: `ramping-vus`
**Load Profile**:
- 0-30s: Ramp up to 1 VU (warm-up phase)
- 30s-1m30s: Maintain 2 VUs (steady load)
- 1m30s-2m: Scale down to 1 VU (step down)
- 2m-2m30s: Cool down to 0 VUs

**Start Time**: 35 seconds (after smoke test completion)
**Target**: Core DMN evaluation functionality under moderate load

### 3. Stress Test Scenario
**Executor**: `ramping-vus`
**Load Profile**:
- 0-30s: Ramp up to 1 VU (initialization)
- 30s-1m30s: Scale to 3 VUs (moderate stress)
- 1m30s-2m: Peak at 5 VUs (high stress)
- 2m-2m30s: Scale down to 3 VUs (recovery)
- 2m30s-3m: Cool down to 0 VUs

**Start Time**: 4 minutes (delayed to avoid scenario conflicts)
**Target**: System behavior under high concurrent load

## Performance Thresholds

### HTTP Request Metrics
- **http_req_duration**: 95th percentile < 2000ms (realistic for complex DMN operations)
- **http_req_failed**: Failure rate < 20% (accounts for load testing conditions)

### DMN-Specific Thresholds
- **dmn_evaluation_success**: Success rate > 70% (realistic for load conditions)
- **wordpress_api_success**: Success rate > 90% (higher reliability expected)
- **dmn_engine_success**: Success rate > 80% (engine health monitoring)

### Error Rate Thresholds
- **server_error_rate**: < 30% (tracks 5xx errors)
- **client_error_rate**: < 10% (tracks 4xx errors)

## Test Data and Scenarios

### DMN Decision Test Cases

#### High Priority Test Cases (60% execution probability)
1. **Summer 8 guests**
   - Season: Summer
   - Guest Count: 8
   - Expected Results: light salad, salad, steak
   - Use Case: Popular scenario, high traffic validation

2. **Winter 4 guests**
   - Season: Winter
   - Guest Count: 4
   - Expected Results: roastbeef, beef
   - Use Case: Core winter decision logic

#### Medium Priority Test Cases (Included in 40% random selection)
3. **Fall 6 guests**
   - Season: Fall
   - Guest Count: 6
   - Expected Results: spareribs, ribs
   - Use Case: Seasonal variation testing

4. **Spring 3 guests**
   - Season: Spring
   - Guest Count: 3
   - Expected Results: gourmet steak, steak
   - Use Case: Small group decision logic

#### Low Priority Test Cases (Edge cases)
5. **Winter 12 guests**
   - Season: Winter
   - Guest Count: 12
   - Expected Results: stew
   - Use Case: Large group handling

6. **Spring 7 guests**
   - Season: Spring
   - Guest Count: 7
   - Expected Results: steak
   - Use Case: Mid-size group scenarios

### Test Case Selection Algorithm
- **60% probability**: High priority test cases
- **40% probability**: Random selection from all test cases
- **Purpose**: Focuses load on most important scenarios while ensuring edge case coverage

## Test Functions Analysis

### Main Test Function (`default function`)

#### Execution Flow
1. **Test Case Selection**: Priority-weighted random selection
2. **WordPress API Test**: 30% execution probability (load reduction)
3. **DMN Evaluation Test**: 100% execution (core functionality)
4. **DMN Engine Health Test**: 20% execution probability (resource conservation)
5. **Sleep Pattern**: 0.5-2.5 seconds (realistic user behavior simulation)

#### Error Handling
- Try-catch wrapper for all test operations
- Error counter increment for tracking
- Continuation despite individual test failures

### WordPress API Testing (`testWordPressApi`)

#### Test Endpoint
`GET {testUrl}/wp-json/`

#### Validation Checks
- **Accessibility**: HTTP status 200
- **Performance**: Response time < 2000ms
- **Content Type**: Valid JSON response headers

#### Success Criteria
- All three checks must pass for success
- Failure triggers warning logging with diagnostics

### DMN Evaluation Testing (`testDmnEvaluation`)

#### Request Configuration
**Endpoint**: `POST {testUrl}/wp-json/operaton-dmn/v1/evaluate`
**Timeout**: 15 seconds (extended for complex operations)
**Headers**:
- Content-Type: application/json
- Accept: application/json
- User-Agent: K6-Load-Test/1.0
- X-API-Key: (if configured)

#### Payload Structure
```json
{
  "config_id": 1,
  "form_data": {
    "season": "Summer",
    "guestCount": 8
  }
}
```

#### Validation Checks
1. **Request Success**: HTTP status 200 or 201
2. **Response Time**: < 10,000ms (10 seconds)
3. **Content Validation**: Non-empty response body
4. **JSON Structure**: Valid object with expected properties
5. **Response Format**: Contains success, results, decision, result, data, or string content

#### Enhanced Error Handling
- **Server Errors (5xx)**: Detailed logging with response body
- **Client Errors (4xx)**: Status code tracking
- **Parse Errors**: JSON parsing failure handling
- **Debug Information**: Full request details for 500 errors

### DMN Engine Health Testing (`testDmnEngineHealth`)

#### Test Endpoint
`GET {engineUrl}/engine-rest/version`

#### Validation Checks
- **Accessibility**: HTTP status 200
- **Performance**: Response time < 5000ms
- **Version Information**: Valid version field in JSON response

#### Health Status Reporting
- Success: Version number extraction and display
- Failure: Network connectivity diagnostics
- Status 0: Network connectivity issue identification

## Metrics and Monitoring

### Performance Tracking
- **Request Duration**: Individual request timing
- **Success Rates**: Per-endpoint success percentage
- **Error Classification**: Server vs. client error categorization
- **Response Time Trends**: Performance degradation monitoring

### Load Distribution
- **Endpoint Coverage**: Balanced testing across all endpoints
- **Resource Management**: Reduced frequency for health checks
- **User Behavior**: Realistic sleep patterns between requests
- **Priority Weighting**: Focus on high-impact scenarios

## Report Generation and Analysis

### Summary Report Structure
```json
{
  "testRunId": "load-test-{timestamp}",
  "timestamp": "2025-09-14T12:00:00.000Z",
  "environment": {
    "testUrl": "https://...",
    "engineUrl": "https://...",
    "apiKeyConfigured": true
  },
  "performance": {
    "totalRequests": 150,
    "failedRequests": 12,
    "successRate": "92.0%",
    "avgResponseTime": "1250.5ms"
  },
  "metrics": { /* detailed K6 metrics */ },
  "thresholds": { /* threshold results */ },
  "recommendations": [ /* automated recommendations */ ]
}
```

### Output Files Generated
1. **load-test-summary.json**: Executive summary with key metrics
2. **load-test-detailed.json**: Complete K6 metrics dump
3. **load-test-recommendations.txt**: Automated performance recommendations
4. **stdout**: Color-coded console summary

### Automated Recommendations System

#### High Failure Rate Detection
- **Trigger**: > 10% failure rate
- **Recommendation**: "High failure rate ({rate}%) - investigate server stability"

#### Performance Degradation Detection
- **Trigger**: 95th percentile > 2000ms
- **Recommendation**: "Slow response times (95th percentile: {time}ms) - optimize performance"

#### DMN Evaluation Issues
- **Trigger**: < 80% DMN success rate
- **Recommendation**: "Low DMN evaluation success ({rate}%) - check DMN engine connectivity"

#### Positive Performance
- **Trigger**: All metrics within thresholds
- **Recommendation**: "All metrics within acceptable ranges - system performing well under load"

## Load Testing Strategy

### Progressive Load Approach
1. **Smoke Test**: Validate basic functionality
2. **Load Test**: Assess performance under normal conditions
3. **Stress Test**: Determine breaking points and recovery behavior

### Realistic User Simulation
- **Variable Sleep**: 0.5-2.5 seconds between requests
- **Priority Weighting**: Focus on common use cases
- **Random Distribution**: Ensure comprehensive coverage
- **Resource Conservation**: Reduce frequency of health checks

### Error Tolerance
- **Expected Failures**: Account for realistic load testing conditions
- **Graceful Degradation**: System should maintain partial functionality
- **Recovery Testing**: Validate system recovery after stress
- **Threshold Calibration**: Realistic performance expectations

## Usage and Integration

### Command Line Execution
```bash
# Basic execution
k6 run tests/load/dmn-load-test.js

# With environment variables
DMN_TEST_URL=https://staging.example.com k6 run tests/load/dmn-load-test.js

# With custom configuration
k6 run --vus 10 --duration 2m tests/load/dmn-load-test.js
```

### NPM Script Integration
```json
{
  "scripts": {
    "test:load:smoke": "k6 run --env DMN_TEST_URL=... tests/load/dmn-load-test.js",
    "test:load:stress": "k6 run --env STRESS_MODE=true tests/load/dmn-load-test.js"
  }
}
```

### CI/CD Pipeline Integration
- **Performance Gates**: Threshold-based pipeline controls
- **Trend Analysis**: Performance regression detection
- **Report Archiving**: Historical performance tracking
- **Alert Integration**: Automated failure notifications

## Performance Optimization Insights

### Response Time Analysis
- **Target**: 95th percentile < 2 seconds
- **Realistic**: Complex DMN evaluations require processing time
- **Optimization**: Focus on database queries and DMN engine communication

### Scalability Patterns
- **Horizontal Scaling**: Multiple WordPress instances
- **DMN Engine Scaling**: Distributed DMN processing
- **Caching Strategy**: Decision result caching for repeated scenarios
- **Load Balancing**: Request distribution across instances

### Resource Management
- **Connection Pooling**: Efficient HTTP connection reuse
- **Memory Management**: Prevent memory leaks during load
- **Database Optimization**: Query performance tuning
- **Engine Communication**: Persistent connections to DMN engine

## Troubleshooting Guide

### Common Issues and Solutions

#### High Server Error Rate (5xx)
- **Symptoms**: > 30% server error rate
- **Investigation**: Check WordPress error logs, DMN engine status
- **Solutions**: Scale resources, optimize database, fix configuration issues

#### Slow Response Times
- **Symptoms**: 95th percentile > 2000ms
- **Investigation**: Profile slow requests, check database queries
- **Solutions**: Add caching, optimize DMN engine, scale infrastructure

#### DMN Engine Connectivity Issues
- **Symptoms**: Low DMN evaluation success rate
- **Investigation**: Network connectivity, engine health, configuration
- **Solutions**: Verify URLs, check firewalls, validate engine deployment

#### Memory or Resource Exhaustion
- **Symptoms**: Increasing response times, connection failures
- **Investigation**: Monitor server resources, check for leaks
- **Solutions**: Increase resources, optimize code, add resource limits
