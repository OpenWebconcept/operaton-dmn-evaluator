<?php

/**
 * Test Helper Class
 */

namespace Operaton\DMN\Tests\Helpers;

class TestHelper
{
    /**
     * Create mock DMN response
     */
    public static function mockDmnResponse(string $decision = 'approved', float $confidence = 0.85): array
    {
        return [
            'decision' => $decision,
            'confidence' => $confidence,
            'reasoning' => 'Test decision reasoning',
            'timestamp' => date('c'),
            'execution_time' => 0.15,
            'model_version' => '1.0.0-test'
        ];
    }

    /**
     * Create test form data
     */
    public static function createTestForm(array $config = []): array
    {
        $defaultForm = [
            'id' => 999,
            'title' => 'Test DMN Form',
            'description' => 'Test form for DMN evaluation',
            'dmn_enabled' => true,
            'dmn_model' => 'test-model',
            'fields' => [
                [
                    'id' => 1,
                    'type' => 'text',
                    'label' => 'Full Name',
                    'required' => true
                ],
                [
                    'id' => 2,
                    'type' => 'number',
                    'label' => 'Age',
                    'required' => true
                ],
                [
                    'id' => 3,
                    'type' => 'select',
                    'label' => 'Credit Score',
                    'options' => ['excellent', 'good', 'fair', 'poor']
                ]
            ]
        ];

        return array_merge($defaultForm, $config);
    }

    /**
     * Create test entry data
     */
    public static function createTestEntry(int $formId, array $fieldValues = []): array
    {
        $defaultValues = [
            '1' => 'John Doe',
            '2' => '30',
            '3' => 'good'
        ];

        $values = array_merge($defaultValues, $fieldValues);

        return [
            'id' => rand(1000, 9999),
            'form_id' => $formId,
            'date_created' => date('Y-m-d H:i:s'),
            'is_starred' => false,
            'is_read' => false,
            'ip' => '127.0.0.1',
            'source_url' => 'http://localhost/test',
            'user_agent' => 'PHPUnit Test',
            'field_values' => $values
        ];
    }

    /**
     * Generate test evaluation data
     */
    public static function generateEvaluationHistory(int $count = 100): array
    {
        $decisions = ['approved', 'rejected', 'conditional', 'pending'];
        $confidenceRanges = [
            'approved' => [0.8, 0.95],
            'rejected' => [0.7, 0.9],
            'conditional' => [0.6, 0.8],
            'pending' => [0.4, 0.7]
        ];

        $evaluations = [];

        for ($i = 0; $i < $count; $i++)
        {
            $decision = $decisions[array_rand($decisions)];
            $confidenceRange = $confidenceRanges[$decision];
            $confidence = rand($confidenceRange[0] * 100, $confidenceRange[1] * 100) / 100;

            $evaluations[] = [
                'id' => $i + 1,
                'form_id' => 999,
                'entry_id' => $i + 1,
                'decision_result' => $decision,
                'confidence_score' => $confidence,
                'execution_time' => rand(50, 300) / 1000,
                'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} hours"))
            ];
        }

        return $evaluations;
    }
}
