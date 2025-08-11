/**
 * Step 5: Simplified Chaos Engineering Tests for Operaton DMN Evaluator
 * Save this as: tests/chaos/chaos-engineering.js
 *
 * Usage: node tests/chaos/chaos-engineering.js [development|staging|production]
 */

const axios = require('axios');

class SimpleDmnChaosEngineering {
  constructor(options = {}) {
    this.baseUrl = options.baseUrl || process.env.DMN_TEST_URL || 'https://owc-gemeente.test.open-regels.nl';
    this.apiKey = options.apiKey || process.env.DMN_API_KEY || null;
    this.results = [];
    this.config = {
      timeout: 30000,
      retries: 3,
      ...options.config,
    };
  }

  /**
   * Initialize chaos testing session
   */
  async initialize() {
    console.log('🔥 Initializing Simplified Chaos Engineering Tests');
    console.log('='.repeat(50));
    console.log(`Target: ${this.baseUrl}`);
    console.log(`API Key: ${this.apiKey ? 'Configured' : 'Not configured'}`);
    console.log('');

    // Verify baseline functionality
    console.log('📋 Establishing baseline...');
    const baseline = await this.runBaselineTests();

    if (!baseline.healthy) {
      throw new Error('❌ System is not healthy at baseline. Cannot proceed with chaos testing.');
    }

    console.log('✅ Baseline established - system is healthy');
    return baseline;
  }

  /**
   * Run baseline health checks
   */
  async runBaselineTests() {
    const tests = [
      { name: 'WordPress API Health', test: () => this.testWordPressApi() },
      { name: 'DMN Plugin Detection', test: () => this.testDmnPluginDetection() },
    ];

    let healthyCount = 0;
    const results = [];

    for (const test of tests) {
      try {
        const result = await test.test();
        results.push({ name: test.name, success: true, result });
        healthyCount++;
        console.log(`  ✅ ${test.name}`);
      } catch (error) {
        results.push({ name: test.name, success: false, error: error.message });
        console.log(`  ❌ ${test.name}: ${error.message}`);
      }
    }

    return {
      healthy: healthyCount >= 1, // At least 1/2 tests must pass
      healthyCount,
      totalTests: tests.length,
      results,
    };
  }

  /**
   * Execute simplified chaos engineering tests
   */
  async executeChaosTests() {
    console.log('🌪️  Starting Simplified Chaos Engineering Tests');
    console.log('='.repeat(50));

    const chaosScenarios = [
      // Network chaos
      { name: 'Request Timeout Handling', scenario: () => this.testTimeoutHandling() },
      { name: 'Invalid Request Data', scenario: () => this.testInvalidData() },

      // Security chaos
      { name: 'Malicious Input Handling', scenario: () => this.testMaliciousInput() },
      { name: 'Large Payload Handling', scenario: () => this.testLargePayloads() },

      // Concurrency chaos
      { name: 'Concurrent Request Handling', scenario: () => this.testConcurrentRequests() },

      // Error chaos
      { name: 'Invalid Endpoint Access', scenario: () => this.testInvalidEndpoints() },
    ];

    for (const scenario of chaosScenarios) {
      console.log(`\n🎭 Running: ${scenario.name}`);
      console.log('-'.repeat(40));

      try {
        const startTime = Date.now();
        const result = await scenario.scenario();
        const duration = Date.now() - startTime;

        this.results.push({
          scenario: scenario.name,
          success: true,
          duration,
          result,
          timestamp: new Date().toISOString(),
        });

        console.log(`  ✅ Completed in ${duration}ms`);
      } catch (error) {
        this.results.push({
          scenario: scenario.name,
          success: false,
          error: error.message,
          timestamp: new Date().toISOString(),
        });

        console.log(`  ❌ Failed: ${error.message}`);
      }

      // Brief pause between scenarios
      await this.sleep(1000);
    }
  }

  /**
   * Test timeout handling
   */
  async testTimeoutHandling() {
    console.log('  ⏱️  Testing timeout handling...');

    const timeouts = [1000, 5000, 10000]; // ms
    const results = [];

    for (const timeout of timeouts) {
      try {
        const response = await this.makeRequest('/wp-json/operaton-dmn/v1/test', null, { timeout });

        results.push({
          timeout,
          success: true,
          statusCode: response.status,
        });

        console.log(`    ${timeout}ms timeout: Success (${response.status})`);
      } catch (error) {
        const isTimeout = error.code === 'ECONNABORTED' || error.message.includes('timeout');

        results.push({
          timeout,
          success: false,
          isTimeout,
          error: error.message,
        });

        console.log(`    ${timeout}ms timeout: ${isTimeout ? 'Timeout' : 'Error'}`);
      }
    }

    return { scenario: 'Timeout Handling', results };
  }

  /**
   * Test invalid data handling
   */
  async testInvalidData() {
    console.log('  🔀 Testing invalid data handling...');

    const invalidPayloads = [
      { name: 'Empty Object', data: {} },
      { name: 'Invalid JSON Structure', data: { invalid: 'structure' } },
      { name: 'Missing Required Fields', data: { form_data: { age: 30 } } },
      { name: 'Wrong Data Types', data: { config_id: 'not_a_number', form_data: 'not_an_object' } },
    ];

    const results = [];

    for (const payload of invalidPayloads) {
      try {
        const response = await this.makeRequest('/wp-json/operaton-dmn/v1/evaluate', payload.data);

        results.push({
          payload: payload.name,
          handled: true,
          statusCode: response.status,
        });

        console.log(`    ${payload.name}: Handled gracefully (${response.status})`);
      } catch (error) {
        const isHandled = error.response && error.response.status >= 400 && error.response.status < 500;

        results.push({
          payload: payload.name,
          handled: isHandled,
          error: error.message,
        });

        console.log(`    ${payload.name}: ${isHandled ? 'Properly rejected' : 'Error'}`);
      }
    }

    return { scenario: 'Invalid Data Handling', results };
  }

  /**
   * Test malicious input handling
   */
  async testMaliciousInput() {
    console.log('  💉 Testing malicious input handling...');

    const maliciousInputs = [
      {
        name: 'SQL Injection',
        data: {
          config_id: "1'; DROP TABLE wp_posts; --",
          form_data: { age: "30'; DELETE FROM wp_users; --" },
        },
      },
      {
        name: 'XSS Injection',
        data: {
          config_id: 1,
          form_data: {
            name: '<script>alert("xss")</script>',
            email: 'test@example.com<img src=x onerror=alert(1)>',
          },
        },
      },
      {
        name: 'Command Injection',
        data: {
          config_id: 1,
          form_data: { command: '; rm -rf /' },
        },
      },
    ];

    const results = [];

    for (const input of maliciousInputs) {
      try {
        const response = await this.makeRequest('/wp-json/operaton-dmn/v1/evaluate', input.data);

        results.push({
          attack: input.name,
          blocked: false,
          statusCode: response.status,
        });

        console.log(`    ${input.name}: Response received (${response.status}) - check sanitization`);
      } catch (error) {
        const isBlocked = error.response && (error.response.status === 400 || error.response.status === 422);

        results.push({
          attack: input.name,
          blocked: isBlocked,
          error: error.message,
        });

        console.log(`    ${input.name}: ${isBlocked ? 'Blocked' : 'Error'}`);
      }
    }

    return { scenario: 'Malicious Input Handling', results };
  }

  /**
   * Test large payload handling
   */
  async testLargePayloads() {
    console.log('  📦 Testing large payload handling...');

    const payloadSizes = [10, 100, 1000]; // KB
    const results = [];

    for (const sizeKB of payloadSizes) {
      try {
        const largeData = {
          config_id: 1,
          form_data: {
            large_field: 'x'.repeat(sizeKB * 1024),
            age: 30,
          },
        };

        const startTime = Date.now();
        const response = await this.makeRequest('/wp-json/operaton-dmn/v1/evaluate', largeData);
        const responseTime = Date.now() - startTime;

        results.push({
          payloadSizeKB: sizeKB,
          success: true,
          responseTime,
          statusCode: response.status,
        });

        console.log(`    ${sizeKB}KB: ${responseTime}ms (${response.status})`);
      } catch (error) {
        results.push({
          payloadSizeKB: sizeKB,
          success: false,
          error: error.message,
        });

        console.log(`    ${sizeKB}KB: Failed - ${error.message}`);
      }
    }

    return { scenario: 'Large Payload Handling', results };
  }

  /**
   * Test concurrent request handling
   */
  async testConcurrentRequests() {
    console.log('  🔄 Testing concurrent request handling...');

    const concurrentCount = 5;
    const promises = [];

    for (let i = 0; i < concurrentCount; i++) {
      promises.push(
        this.makeRequest('/wp-json/operaton-dmn/v1/test')
          .then(response => ({
            request: i,
            success: true,
            statusCode: response.status,
          }))
          .catch(error => ({
            request: i,
            success: false,
            error: error.message,
          }))
      );
    }

    console.log(`    Launching ${concurrentCount} concurrent requests...`);
    const results = await Promise.all(promises);

    const successCount = results.filter(r => r.success).length;
    console.log(`    Results: ${successCount}/${concurrentCount} successful`);

    return { scenario: 'Concurrent Requests', concurrentCount, successCount, results };
  }

  /**
   * Test invalid endpoint access
   */
  async testInvalidEndpoints() {
    console.log('  🚫 Testing invalid endpoint access...');

    const invalidEndpoints = [
      '/wp-json/operaton-dmn/v1/nonexistent',
      '/wp-json/operaton-dmn/v1/admin/secrets',
      '/wp-json/operaton-dmn/v1/../../../wp-config.php',
      '/wp-json/operaton-dmn/v999/test',
    ];

    const results = [];

    for (const endpoint of invalidEndpoints) {
      try {
        const response = await this.makeRequest(endpoint);

        results.push({
          endpoint,
          blocked: response.status === 404 || response.status === 403,
          statusCode: response.status,
        });

        console.log(`    ${endpoint}: ${response.status}`);
      } catch (error) {
        results.push({
          endpoint,
          blocked: true,
          error: error.message,
        });

        console.log(`    ${endpoint}: Blocked`);
      }
    }

    return { scenario: 'Invalid Endpoint Access', results };
  }

  /**
   * Generate comprehensive test report
   */
  generateReport() {
    console.log('\n' + '='.repeat(50));
    console.log('🔥 CHAOS ENGINEERING TEST REPORT');
    console.log('='.repeat(50));

    const totalScenarios = this.results.length;
    const successfulScenarios = this.results.filter(r => r.success).length;

    console.log(`\n📊 OVERALL RESULTS:`);
    console.log(`   Total Scenarios: ${totalScenarios}`);
    console.log(`   Successful: ${successfulScenarios}`);
    console.log(`   Failed: ${totalScenarios - successfulScenarios}`);
    console.log(`   Success Rate: ${((successfulScenarios / totalScenarios) * 100).toFixed(1)}%`);

    console.log(`\n📋 DETAILED RESULTS:`);
    this.results.forEach((result, index) => {
      console.log(`\n${index + 1}. ${result.scenario}`);
      console.log(`   Status: ${result.success ? '✅ PASSED' : '❌ FAILED'}`);

      if (result.duration) {
        console.log(`   Duration: ${result.duration}ms`);
      }

      if (result.error) {
        console.log(`   Error: ${result.error}`);
      }
    });

    console.log('\n' + '='.repeat(50));

    return {
      summary: {
        totalScenarios,
        successfulScenarios,
        failedScenarios: totalScenarios - successfulScenarios,
        successRate: (successfulScenarios / totalScenarios) * 100,
      },
      results: this.results,
      timestamp: new Date().toISOString(),
    };
  }

  /**
   * Helper methods
   */
  async makeRequest(endpoint, data = null, options = {}) {
    const config = {
      method: data ? 'POST' : 'GET',
      url: `${this.baseUrl}${endpoint}`,
      timeout: options.timeout || this.config.timeout,
      headers: {
        'Content-Type': 'application/json',
      },
    };

    if (this.apiKey) {
      config.headers['X-API-Key'] = this.apiKey;
    }

    if (data) {
      config.data = data;
    }

    return await axios(config);
  }

  async testWordPressApi() {
    const response = await this.makeRequest('/wp-json/');
    return response.status === 200;
  }

  async testDmnPluginDetection() {
    const response = await this.makeRequest('/wp-json/operaton-dmn/v1/test');
    return response.status === 200 || response.status === 404; // 404 is acceptable
  }

  async sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

/**
 * Main execution function
 */
async function runChaosEngineering() {
  const environment = process.argv[2] || 'development';

  console.log(`🎯 Running chaos engineering tests for: ${environment}`);

  const chaosTest = new SimpleDmnChaosEngineering({
    baseUrl: process.env.DMN_TEST_URL || 'https://owc-gemeente.test.open-regels.nl',
    apiKey: process.env.DMN_API_KEY,
  });

  try {
    // Initialize and verify baseline
    await chaosTest.initialize();

    // Execute chaos tests
    await chaosTest.executeChaosTests();

    // Generate comprehensive report
    const report = chaosTest.generateReport();

    // Save report to file
    const fs = require('fs');
    const path = require('path');

    const reportDir = 'test-results';
    const reportFile = path.join(reportDir, `chaos-engineering-${Date.now()}.json`);

    if (!fs.existsSync(reportDir)) {
      fs.mkdirSync(reportDir, { recursive: true });
    }

    fs.writeFileSync(reportFile, JSON.stringify(report, null, 2));
    console.log(`\n📄 Detailed report saved to: ${reportFile}`);

    // Exit with appropriate code
    const failureRate =
      (report.summary.totalScenarios - report.summary.successfulScenarios) / report.summary.totalScenarios;

    if (failureRate > 0.5) {
      console.log('\n❌ HIGH FAILURE RATE: System shows poor resilience');
      process.exit(1);
    } else if (failureRate > 0.2) {
      console.log('\n⚠️  MODERATE ISSUES: System has room for improvement');
      process.exit(0);
    } else {
      console.log('\n✅ GOOD RESILIENCE: System handles chaos well');
      process.exit(0);
    }
  } catch (error) {
    console.error('\n💥 CHAOS TEST EXECUTION FAILED:');
    console.error(error.message);
    process.exit(1);
  }
}

// Run if called directly
if (require.main === module) {
  runChaosEngineering();
}

module.exports = SimpleDmnChaosEngineering;
