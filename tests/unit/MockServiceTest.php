<?php

/**
 * Step 2: Mock Service Verification Test
 * Save this as: tests/unit/MockServiceTest.php
 */

declare(strict_types=1);

namespace Operaton\DMN\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MockServiceTest extends TestCase
{
    private $mockServiceHelper;

    protected function setUp(): void
    {
        // Load the files
        require_once dirname(__DIR__) . '/fixtures/ExtendedMockDmnService.php';
        require_once dirname(__DIR__) . '/helpers/MockServiceTestHelper.php';

        $this->mockServiceHelper = new \Operaton\DMN\Tests\Helpers\MockServiceTestHelper();
    }

    protected function tearDown(): void
    {
        $this->mockServiceHelper->reset();
    }

    public function testMockServiceExists(): void
    {
        $this->assertInstanceOf(
            \Operaton\DMN\Tests\Helpers\MockServiceTestHelper::class,
            $this->mockServiceHelper
        );
    }

    public function testCreditApprovalScenarios(): void
    {
        $mockService = $this->mockServiceHelper->getMockService();

        // Test high approval scenario
        $result = $mockService->evaluateDecision(1, [
            'age' => 35,
            'income' => 75000,
            'credit_score' => 'excellent'
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('approved', $result['decision_results']['decision']);
        $this->assertEquals(3.5, $result['decision_results']['interest_rate']);
    }

    public function testMunicipalityBenefitsScenarios(): void
    {
        $mockService = $this->mockServiceHelper->getMockService();

        // Test senior eligible scenario
        $result = $mockService->evaluateDecision(2, [
            'geboortedatumAanvrager' => '1950-01-01',
            'maandelijksBrutoInkomenAanvrager' => 1200
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['decision_results']['aanmerkingHeusdenPas']);
    }

    public function testErrorHandling(): void
    {
        $mockService = $this->mockServiceHelper->getMockService();

        $this->expectException(\InvalidArgumentException::class);
        $mockService->evaluateDecision(999, ['age' => 30]);
    }

    public function testLatencySimulation(): void
    {
        $latencyTest = $this->mockServiceHelper->testLatencySimulation();

        $this->assertArrayHasKey('latency_working', $latencyTest);
        $this->assertTrue($latencyTest['reasonable_latency']);
    }

    public function testAllScenarios(): void
    {
        $results = $this->mockServiceHelper->runAllTestScenarios();

        $this->assertArrayHasKey('credit_approval_scenarios', $results);
        $this->assertArrayHasKey('municipality_scenarios', $results);

        // Check that all scenarios ran successfully
        foreach ($results as $category => $scenarios)
        {
            foreach ($scenarios as $scenarioName => $result)
            {
                $this->assertTrue(
                    $result['success'],
                    "Scenario {$category}::{$scenarioName} should succeed"
                );
            }
        }
    }
}
