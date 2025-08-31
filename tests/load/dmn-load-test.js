/**
 * K6 Load Testing Script for Operaton DMN Evaluator
 * FIXED: Now properly uses environment variables from .env.testing
 *
 * Save as: tests/load/dmn-load-test.js
 *
 * Usage:
 *   k6 run tests/load/dmn-load-test.js
 *   npm run test:load:smoke
 *   npm run test:load:stress
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.1/index.js';

// FIXED: Get environment variables from K6 environment
// These are passed via npm scripts or command line
const testUrl = __ENV.DMN_TEST_URL || 'https://owc-gemeente.test.open-regels.nl';
const engineUrl = __ENV.DMN_ENGINE_URL || 'http://localhost:8080';
const apiKey = __ENV.DMN_API_KEY || '';

console.log(`🚀 Starting DMN Load Testing`);
console.log(`Base URL: ${testUrl}`);
console.log(`DMN Engine URL: ${engineUrl}`); // This should now show
console.log(`API Key: ${apiKey ? 'configured' : 'Not configured'}`);

// Custom metrics
const dmnEvaluationRate = new Rate('dmn_evaluation_success');
const dmnEvaluationDuration = new Trend('dmn_evaluation_duration');
const wordpressApiRate = new Rate('wordpress_api_success');
const dmnEngineRate = new Rate('dmn_engine_success');

// Test configuration
export let options = {
  scenarios: {
    // Smoke test - basic functionality
    smoke_test: {
      executor: 'constant-vus',
      vus: 1,
      duration: '30s',
      tags: { test_type: 'smoke' },
    },

    // DMN evaluation load test
    dmn_evaluation_load: {
      executor: 'ramping-vus',
      stages: [
        { duration: '1m', target: 1 }, // Warm up
        { duration: '2m', target: 3 }, // Normal load
        { duration: '1m', target: 0 }, // Cool down
      ],
      tags: { test_type: 'load' },
    },

    // Stress test - higher load
    stress_test: {
      executor: 'ramping-vus',
      stages: [
        { duration: '30s', target: 2 },
        { duration: '1m', target: 5 },
        { duration: '30s', target: 8 },
        { duration: '1m', target: 5 },
        { duration: '30s', target: 0 },
      ],
      tags: { test_type: 'stress' },
      startTime: '5m', // Start after other scenarios
    },
  },

  thresholds: {
    // HTTP request duration should be less than 500ms for 95% of requests
    http_req_duration: ['p(95)<500'],

    // HTTP request failure rate should be less than 5%
    http_req_failed: ['rate<0.05'],

    // DMN evaluation success rate should be at least 90%
    dmn_evaluation_success: ['rate>0.9'],

    // WordPress API success rate should be at least 95%
    wordpress_api_success: ['rate>0.95'],

    // DMN Engine success rate should be at least 90%
    dmn_engine_success: ['rate>0.9'],
  },
};

// Test data for DMN evaluations
const dmnTestCases = [
  {
    name: 'Summer 8 guests',
    season: 'Summer',
    guestCount: 8,
    expected: 'light salad',
  },
  {
    name: 'Winter 4 guests',
    season: 'Winter',
    guestCount: 4,
    expected: 'roastbeef',
  },
  {
    name: 'Fall 6 guests',
    season: 'Fall',
    guestCount: 6,
    expected: 'spareribs',
  },
  {
    name: 'Spring 3 guests',
    season: 'Spring',
    guestCount: 3,
    expected: 'gourmet steak',
  },
  {
    name: 'Winter 12 guests',
    season: 'Winter',
    guestCount: 12,
    expected: 'stew',
  },
  {
    name: 'Spring 7 guests',
    season: 'Spring',
    guestCount: 7,
    expected: 'steak',
  },
];

export default function () {
  const testCase = dmnTestCases[Math.floor(Math.random() * dmnTestCases.length)];

  // Test WordPress API health
  testWordPressApi();

  // Test DMN Plugin endpoint
  testDmnPluginEndpoint();

  // Test DMN evaluation
  testDmnEvaluation(testCase);

  // Test DMN Engine directly (now uses the correct engine URL)
  testDmnEngineHealth();

  // Sleep between iterations
  sleep(1 + Math.random() * 2);
}

function testWordPressApi() {
  const response = http.get(`${testUrl}/wp-json/`);

  const success = check(response, {
    'WordPress API is accessible': r => r.status === 200,
    'WordPress API response time < 1000ms': r => r.timings.duration < 1000,
  });

  wordpressApiRate.add(success);
}

function testDmnPluginEndpoint() {
  const headers = {
    'Content-Type': 'application/json',
  };

  if (apiKey) {
    headers['X-API-Key'] = apiKey;
  }

  const response = http.get(`${testUrl}/wp-json/operaton-dmn/v1/test`, {
    headers: headers,
  });

  check(response, {
    'DMN Plugin endpoint responds': r => [200, 404, 405].includes(r.status),
    'DMN Plugin response time < 2000ms': r => r.timings.duration < 2000,
  });
}

function testDmnEvaluation(testCase) {
  const payload = {
    config_id: 1,
    form_data: {
      season: testCase.season,
      guestCount: testCase.guestCount,
    },
  };

  const headers = {
    'Content-Type': 'application/json',
  };

  if (apiKey) {
    headers['X-API-Key'] = apiKey;
  }

  const startTime = new Date().getTime();
  const response = http.post(`${testUrl}/wp-json/operaton-dmn/v1/evaluate`, JSON.stringify(payload), {
    headers: headers,
  });
  const endTime = new Date().getTime();
  const duration = endTime - startTime;

  const success = check(response, {
    'DMN evaluation request succeeds': r => r.status === 200 || r.status === 201,
    'DMN evaluation response time < 3000ms': r => r.timings.duration < 3000,
    'DMN evaluation returns valid JSON': r => {
      try {
        const body = JSON.parse(r.body);
        return typeof body === 'object';
      } catch (e) {
        return false;
      }
    },
  });

  dmnEvaluationRate.add(success);
  dmnEvaluationDuration.add(duration);

  if (!success) {
    console.log(`DMN evaluation failed for ${testCase.name}: Status ${response.status}`);
  }
}

function testDmnEngineHealth() {
  // FIXED: Now uses the correct engine URL from environment variables
  const response = http.get(`${engineUrl}/engine-rest/version`, {
    timeout: '5s',
  });

  const success = check(response, {
    'DMN Engine is accessible': r => r.status === 200,
    'DMN Engine response time < 2000ms': r => r.timings.duration < 2000,
  });

  dmnEngineRate.add(success);

  if (!success) {
    console.log(`DMN Engine health check failed: Status ${response.status} at ${engineUrl}`);
  }
}

export function handleSummary(data) {
  // Create a basic summary object for JSON output
  const summary = {
    testRunId: `load-test-${Date.now()}`,
    timestamp: new Date().toISOString(),
    environment: {
      testUrl: testUrl,
      engineUrl: engineUrl,
      apiKeyConfigured: !!apiKey,
    },
    metrics: data.metrics,
    thresholds: data.thresholds,
  };

  // Return both file outputs AND the default K6 console output
  return {
    stdout: textSummary(data, { indent: ' ', enableColors: true }), // This restores the default output
    'test-results/load-test-summary.json': JSON.stringify(summary, null, 2),
    'test-results/load-test-detailed.json': JSON.stringify(data, null, 2),
  };
}
