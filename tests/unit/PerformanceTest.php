<?php

/**
 * Performance Tests
 */

declare(strict_types=1);

namespace Operaton\DMN\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PerformanceTest extends TestCase
{
    private $apiManager;

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/fixtures/mock-classes.php';
        $this->apiManager = new \Operaton\DMN\Tests\Fixtures\MockDmnApi();
    }

    public function testSingleEvaluationPerformance(): void
    {
        $testData = ['age' => 30, 'income' => 50000];

        $startTime = microtime(true);
        $result = $this->apiManager->evaluateDmn($testData);
        $executionTime = microtime(true) - $startTime;

        // Should complete in reasonable time (under 100ms for mock)
        $this->assertLessThan(0.1, $executionTime);
        $this->assertIsArray($result);
    }

    public function testMultipleEvaluationsPerformance(): void
    {
        $testData = ['age' => 30, 'income' => 50000];

        $startTime = microtime(true);

        // Run 100 evaluations
        for ($i = 0; $i < 100; $i++)
        {
            $this->apiManager->evaluateDmn($testData);
        }

        $totalTime = microtime(true) - $startTime;
        $avgTime = $totalTime / 100;

        // Average should be under 1ms per evaluation for mocks
        $this->assertLessThan(0.001, $avgTime);
    }

    public function testMemoryUsage(): void
    {
        $initialMemory = memory_get_usage();

        // Run multiple evaluations
        for ($i = 0; $i < 50; $i++)
        {
            $this->apiManager->evaluateDmn(['age' => $i + 18, 'income' => 50000]);
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable (under 1MB for 50 evals)
        $this->assertLessThan(1024 * 1024, $memoryIncrease);
    }
}
