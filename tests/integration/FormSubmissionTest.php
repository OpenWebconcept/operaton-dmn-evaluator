<?php

/**
 * Form Submission Integration Tests
 */

declare(strict_types=1);

namespace Operaton\DMN\Tests\Integration;

use PHPUnit\Framework\TestCase;

class FormSubmissionTest extends TestCase
{
    private $apiManager;
    private $database;

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/fixtures/mock-classes.php';
        require_once dirname(__DIR__) . '/helpers/test-helper.php';

        $this->apiManager = new \Operaton\DMN\Tests\Fixtures\MockDmnApi();
        $this->database = new \Operaton\DMN\Tests\Fixtures\MockDmnDatabase();
    }

    public function testCompleteFormSubmissionFlow(): void
    {
        // Create test form
        $form = \Operaton\DMN\Tests\Helpers\TestHelper::createTestForm();

        // Create test entry
        $entry = \Operaton\DMN\Tests\Helpers\TestHelper::createTestEntry($form['id']);

        // Evaluate DMN
        $result = $this->apiManager->evaluateDmn($entry['field_values']);

        // Log evaluation to database
        $logId = $this->database->logEvaluation([
            'form_id' => $form['id'],
            'entry_id' => $entry['id'],
            'decision_result' => $result['decision'],
            'confidence_score' => $result['confidence'],
            'execution_time' => $result['execution_time'] ?? 0.15
        ]);

        // Verify complete flow
        $this->assertIsArray($result);
        $this->assertArrayHasKey('decision', $result);
        $this->assertGreaterThan(0, $logId);

        // Verify data was stored
        $evaluations = $this->database->getEvaluations(['form_id' => $form['id']]);
        $this->assertCount(1, $evaluations);
        $this->assertEquals($result['decision'], $evaluations[0]['decision_result']);
    }

    public function testErrorHandlingInIntegration(): void
    {
        $this->apiManager->setMockError('DMN service unavailable');

        $form = \Operaton\DMN\Tests\Helpers\TestHelper::createTestForm();
        $entry = \Operaton\DMN\Tests\Helpers\TestHelper::createTestEntry($form['id']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('DMN service unavailable');

        $this->apiManager->evaluateDmn($entry['field_values']);
    }

    public function testMultipleFormsIntegration(): void
    {
        $forms = [
            \Operaton\DMN\Tests\Helpers\TestHelper::createTestForm(['id' => 1001]),
            \Operaton\DMN\Tests\Helpers\TestHelper::createTestForm(['id' => 1002])
        ];

        foreach ($forms as $form)
        {
            $entry = \Operaton\DMN\Tests\Helpers\TestHelper::createTestEntry($form['id']);
            $result = $this->apiManager->evaluateDmn($entry['field_values']);

            $this->database->logEvaluation([
                'form_id' => $form['id'],
                'entry_id' => $entry['id'],
                'decision_result' => $result['decision']
            ]);
        }

        // Verify both forms have evaluations
        $form1Evals = $this->database->getEvaluations(['form_id' => 1001]);
        $form2Evals = $this->database->getEvaluations(['form_id' => 1002]);

        $this->assertCount(1, $form1Evals);
        $this->assertCount(1, $form2Evals);
    }
}
