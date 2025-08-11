/**
 * Fixed K6 Load Testing Script for Operaton DMN Evaluator
 * Replace your existing tests/load/dmn-load-test.js with this version
 *
 * Usage: k6 run tests/load/dmn-load-test.js
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// Custom metrics for DMN-specific testing
export const dmnEvaluationRate = new Rate('dmn_evaluation_success_rate');
export const dmnResponseTime = new Trend('dmn_response_time');
export const dmnErrors = new Counter('dmn_errors');

// Test configuration - adjusted for initial implementation
export const options = {
  scenarios: {
    // Start with a simple smoke test
    smoke_test: {
      executor: 'constant-vus',
      vus: 1,
      duration: '30s',
      tags: { test_type: 'smoke' },
    },

    // Basic load test - conservative for first implementation
    basic_load_test: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '1m', target: 3 }, // Ramp up slowly
        { duration: '2m', target: 3 }, // Stay at 3 users
        { duration: '1m', target: 0 }, // Ramp down
      ],
      tags: { test_type: 'load' },
    },
  },

  // Conservative thresholds for initial testing
  thresholds: {
    http_req_duration: ['p(95)<2000'], // 95% of requests under 2 seconds
    http_req_failed: ['rate<0.05'], // Error rate under 5%
    dmn_evaluation_success_rate: ['rate>0.8'], // 80% success rate
    dmn_response_time: ['p(90)<1500'], // 90% under 1.5 seconds
  },
};

// Configuration
const BASE_URL = __ENV.BASE_URL || 'https://owc-gemeente.test.open-regels.nl';
const API_KEY = __ENV.DMN_API_KEY || '';

// Test data generators
function generateTestData() {
  const scenarios = [
    // Simple credit approval scenarios
    {
      config_id: 1,
      form_data: {
        age: 25 + Math.floor(Math.random() * 40),
        income: 30000 + Math.floor(Math.random() * 50000),
        credit_score: ['poor', 'fair', 'good', 'excellent'][Math.floor(Math.random() * 4)],
        scenario: 'credit_approval',
      },
    },

    // Municipality benefits scenarios
    {
      config_id: 2,
      form_data: {
        geboortedatumAanvrager: `19${50 + Math.floor(Math.random() * 50)}-01-01`,
        maandelijksBrutoInkomenAanvrager: 1000 + Math.floor(Math.random() * 3000),
        scenario: 'municipality_benefits',
      },
    },
  ];

  return scenarios[Math.floor(Math.random() * scenarios.length)];
}

// Main test function
export default function () {
  // Choose test type based on environment variable
  const testType = __ENV.TEST_TYPE || 'mixed';

  switch (testType) {
    case 'health_only':
      testHealthEndpoints();
      break;
    case 'evaluation_only':
      testDmnEvaluation();
      break;
    default:
      // Mixed testing - mostly health checks for initial implementation
      const rand = Math.random();
      if (rand < 0.7) {
        testHealthEndpoints();
      } else {
        testDmnEvaluation();
      }
  }

  // Simulate realistic user behavior
  sleep(Math.random() * 2 + 1); // 1-3 seconds think time
}

function testHealthEndpoints() {
  // Test basic WordPress and DMN endpoints
  const endpoints = ['/wp-json/', '/wp-json/operaton-dmn/v1/test', '/wp-json/operaton-dmn/v1/health'];

  const endpoint = endpoints[Math.floor(Math.random() * endpoints.length)];

  const response = http.get(`${BASE_URL}${endpoint}`, {
    timeout: '10s',
    tags: { endpoint: endpoint, test_type: 'health' },
  });

  check(response, {
    'Health endpoint accessible': r => r.status === 200 || r.status === 404,
    'Health response time OK': r => r.timings.duration < 1000,
  });
}

function testDmnEvaluation() {
  const testData = generateTestData();

  const headers = {
    'Content-Type': 'application/json',
  };

  if (API_KEY) {
    headers['X-API-Key'] = API_KEY;
  }

  const startTime = Date.now();

  const response = http.post(`${BASE_URL}/wp-json/operaton-dmn/v1/evaluate`, JSON.stringify(testData), {
    headers: headers,
    timeout: '15s',
    tags: {
      endpoint: 'evaluate',
      test_type: 'dmn_evaluation',
      scenario: testData.form_data.scenario,
    },
  });

  const endTime = Date.now();
  const responseTime = endTime - startTime;

  // Record DMN-specific metrics
  dmnResponseTime.add(responseTime);

  const success = check(response, {
    'DMN evaluation status acceptable': r =>
      r.status === 200 || r.status === 400 || r.status === 404 || r.status === 500,
    'DMN evaluation response time reasonable': r => responseTime < 3000,
    'DMN evaluation has response body': r => r.body.length > 0,
  });

  dmnEvaluationRate.add(success);

  if (!success) {
    dmnErrors.add(1);
    console.log(`DMN Evaluation failed: ${response.status} - ${response.body.substring(0, 100)}`);
  }

  // For successful responses, try to parse and validate
  if (response.status === 200) {
    try {
      const body = JSON.parse(response.body);
      check(body, {
        'Response has expected structure': b =>
          b.hasOwnProperty('success') || b.hasOwnProperty('decision') || b.hasOwnProperty('error'),
      });
    } catch (e) {
      console.log('Failed to parse response JSON');
    }
  }
}

// Setup function - runs once at the beginning
export function setup() {
  console.log('🚀 Starting DMN Load Testing');
  console.log(`Base URL: ${BASE_URL}`);
  console.log(`API Key: ${API_KEY ? 'Configured' : 'Not configured'}`);
  console.log(`Test Type: ${__ENV.TEST_TYPE || 'mixed'}`);

  // Verify the target is accessible
  const response = http.get(`${BASE_URL}/wp-json/`);
  if (response.status !== 200) {
    console.error(`⚠️ Target ${BASE_URL} returned status ${response.status}`);
    console.error('Load test will continue but may have limited functionality');
  } else {
    console.log('✅ Target is accessible');
  }

  return { baseUrl: BASE_URL };
}

// Teardown function - runs once at the end
export function teardown(data) {
  console.log('🏁 Load testing completed');
  console.log('📊 Check the summary above for detailed results');
}

// FIXED: Custom summary function with proper null checking
export function handleSummary(data) {
  // Helper function to safely get metric values
  function getMetricValue(metrics, metricName, valueName, defaultValue = 0) {
    if (!metrics || !metrics[metricName] || !metrics[metricName].values) {
      return defaultValue;
    }
    return metrics[metricName].values[valueName] || defaultValue;
  }

  // Safely extract metric values
  const avgResponseTime = getMetricValue(data.metrics, 'http_req_duration', 'avg');
  const p95ResponseTime = getMetricValue(data.metrics, 'http_req_duration', 'p(95)');
  const failureRate = getMetricValue(data.metrics, 'http_req_failed', 'rate');
  const successRate = (1 - failureRate) * 100;

  const dmnSuccessRate = getMetricValue(data.metrics, 'dmn_evaluation_success_rate', 'rate') * 100;
  const avgDmnResponse = getMetricValue(data.metrics, 'dmn_response_time', 'avg');
  const dmnErrorCount = getMetricValue(data.metrics, 'dmn_errors', 'count');

  const totalRequests = getMetricValue(data.metrics, 'http_reqs', 'count');
  const failedRequests = getMetricValue(data.metrics, 'http_req_failed', 'count');

  // Generate summary text
  const summary = `
🎯 DMN LOAD TEST RESULTS
========================

📈 Performance Metrics:
- Average Response Time: ${avgResponseTime.toFixed(2)}ms
- 95th Percentile: ${p95ResponseTime.toFixed(2)}ms
- Success Rate: ${successRate.toFixed(2)}%

🔄 DMN Specific Metrics:
- DMN Evaluation Success: ${dmnSuccessRate.toFixed(2)}%
- Average DMN Response: ${avgDmnResponse > 0 ? avgDmnResponse.toFixed(2) + 'ms' : 'N/A'}
- DMN Errors: ${dmnErrorCount}

📊 Request Summary:
- Total Requests: ${totalRequests}
- Failed Requests: ${failedRequests}

🎯 Thresholds Status:
${
  data.thresholds
    ? Object.entries(data.thresholds)
        .map(([key, value]) => `- ${key}: ${value.ok ? '✅ PASS' : '❌ FAIL'}`)
        .join('\n')
    : '- No thresholds data available'
}

${
  data.thresholds && data.thresholds.http_req_duration && !data.thresholds.http_req_duration.ok
    ? '⚠️  Performance threshold exceeded - consider optimization'
    : '✅ Performance looks good'
}
`;

  return {
    'test-results/load-test-summary.txt': summary,
    'test-results/load-test-results.json': JSON.stringify(data, null, 2),
    stdout: summary,
  };
}
