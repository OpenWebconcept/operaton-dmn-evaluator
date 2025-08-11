<?php

/**
 * Step 2: Test Helper for Mock Service
 * Save this as: tests/helpers/MockServiceTestHelper.php
 */

namespace Operaton\DMN\Tests\Helpers;

use Operaton\DMN\Tests\Fixtures\ExtendedMockDmnService;

class MockServiceTestHelper
{
    private ExtendedMockDmnService $mockService;

    public function __construct()
    {
        $this->mockService = new ExtendedMockDmnService();
    }

    /**
     * Run all test scenarios and return results
     */
    public function runAllTestScenarios(): array
    {
        $results = [];
        $testDataSets = $this->mockService->getTestDataSets();

        foreach ($testDataSets as $category => $scenarios)
        {
            $results[$category] = [];

            foreach ($scenarios as $scenarioName => $scenario)
            {
                try
                {
                    $result = $this->mockService->evaluateDecision(
                        $scenario['config_id'],
                        $scenario['form_data']
                    );

                    $results[$category][$scenarioName] = [
                        'success' => true,
                        'result' => $result,
                        'expected' => $scenario['expected_decision'] ?? $scenario['expected_results'] ?? null,
                        'matches_expectation' => $this->validateExpectation($result, $scenario)
                    ];
                }
                catch (\Exception $e)
                {
                    $results[$category][$scenarioName] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'expected' => $scenario['expected_decision'] ?? $scenario['expected_results'] ?? null
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Validate if result matches expectation
     */
    private function validateExpectation(array $result, array $scenario): bool
    {
        if (isset($scenario['expected_decision']))
        {
            return $result['decision_results']['decision'] === $scenario['expected_decision'];
        }

        if (isset($scenario['expected_results']))
        {
            foreach ($scenario['expected_results'] as $key => $expectedValue)
            {
                if (
                    !isset($result['decision_results'][$key]) ||
                    $result['decision_results'][$key] !== $expectedValue
                )
                {
                    return false;
                }
            }
            return true;
        }

        return true; // No specific expectations
    }

    /**
     * Test error scenarios
     */
    public function testErrorScenarios(): array
    {
        $results = [];

        // Test invalid config ID
        try
        {
            $this->mockService->evaluateDecision(999, ['age' => 30]);
            $results['invalid_config'] = ['success' => false, 'message' => 'Should have thrown exception'];
        }
        catch (\Exception $e)
        {
            $results['invalid_config'] = ['success' => true, 'message' => 'Correctly threw exception: ' . $e->getMessage()];
        }

        // Test with error simulation
        $this->mockService->setErrorRate(1.0); // 100% error rate
        try
        {
            $this->mockService->evaluateDecision(1, ['age' => 30, 'income' => 50000]);
            $results['simulated_error'] = ['success' => false, 'message' => 'Should have simulated error'];
        }
        catch (\Exception $e)
        {
            $results['simulated_error'] = ['success' => true, 'message' => 'Correctly simulated error: ' . $e->getMessage()];
        }

        // Reset error rate
        $this->mockService->setErrorRate(0.0);

        return $results;
    }

    /**
     * Test latency simulation
     */
    public function testLatencySimulation(): array
    {
        $results = [];

        // Test without latency
        $startTime = microtime(true);
        $result = $this->mockService->evaluateDecision(1, ['age' => 30, 'income' => 50000, 'credit_score' => 'good']);
        $timeWithoutLatency = microtime(true) - $startTime;

        // Test with latency
        $this->mockService->setSimulateLatency(true);
        $startTime = microtime(true);
        $result = $this->mockService->evaluateDecision(1, ['age' => 30, 'income' => 50000, 'credit_score' => 'good']);
        $timeWithLatency = microtime(true) - $startTime;

        $this->mockService->setSimulateLatency(false);

        return [
            'without_latency' => $timeWithoutLatency,
            'with_latency' => $timeWithLatency,
            'latency_working' => $timeWithLatency > $timeWithoutLatency,
            'reasonable_latency' => $timeWithLatency < 1.0 // Should be under 1 second
        ];
    }

    /**
     * Generate test report
     */
    public function generateTestReport(): array
    {
        return [
            'timestamp' => date('c'),
            'all_scenarios' => $this->runAllTestScenarios(),
            'error_scenarios' => $this->testErrorScenarios(),
            'latency_test' => $this->testLatencySimulation(),
            'execution_history' => $this->mockService->getExecutionHistory()
        ];
    }

    /**
     * Reset mock service state
     */
    public function reset(): void
    {
        $this->mockService->reset();
    }

    /**
     * Get mock service instance
     */
    public function getMockService(): ExtendedMockDmnService
    {
        return $this->mockService;
    }
}
