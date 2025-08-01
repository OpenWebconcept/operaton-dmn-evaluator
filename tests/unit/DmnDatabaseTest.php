<?php

/**
 * DMN Database Tests
 */

declare(strict_types=1);

namespace Operaton\DMN\Tests\Unit;

use PHPUnit\Framework\TestCase;

class DmnDatabaseTest extends TestCase
{
    private $database;

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/fixtures/mock-classes.php';
        $this->database = new \Operaton\DMN\Tests\Fixtures\MockDmnDatabase();
    }

    public function testLogEvaluation(): void
    {
        $evaluationData = [
            'form_id' => 999,
            'entry_id' => 123,
            'decision_result' => 'approved',
            'confidence_score' => 0.95,
            'execution_time' => 0.15
        ];

        $id = $this->database->logEvaluation($evaluationData);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testLogPerformanceMetrics(): void
    {
        $performanceData = [
            'form_id' => 999,
            'endpoint' => '/api/evaluate',
            'execution_time' => 0.234,
            'memory_usage' => 1024000
        ];

        $id = $this->database->logPerformance($performanceData);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testGetEvaluations(): void
    {
        // Add test data
        $this->database->logEvaluation([
            'form_id' => 999,
            'entry_id' => 1,
            'decision_result' => 'approved'
        ]);

        $this->database->logEvaluation([
            'form_id' => 999,
            'entry_id' => 2,
            'decision_result' => 'rejected'
        ]);

        $evaluations = $this->database->getEvaluations(['form_id' => 999]);

        $this->assertCount(2, $evaluations);
        $this->assertEquals('approved', $evaluations[0]['decision_result']);
        $this->assertEquals('rejected', $evaluations[1]['decision_result']);
    }

    public function testCleanupOldData(): void
    {
        $deletedCount = $this->database->cleanupOldData(30);

        $this->assertIsInt($deletedCount);
        $this->assertGreaterThanOrEqual(0, $deletedCount);
    }
}
