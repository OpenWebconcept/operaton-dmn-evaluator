<?php

/**
 * Step 2: Extended Mock DMN Service Implementation
 * Save this as: tests/fixtures/ExtendedMockDmnService.php
 */

namespace Operaton\DMN\Tests\Fixtures;

class ExtendedMockDmnService
{
    private array $configurations = [];
    private array $decisionTables = [];
    private array $executionHistory = [];
    private bool $simulateLatency = false;
    private float $errorRate = 0.0;

    public function __construct()
    {
        $this->initializeConfigurations();
        $this->initializeDecisionTables();
    }

    /**
     * Initialize test configurations
     */
    private function initializeConfigurations(): void
    {
        $this->configurations = [
            1 => [
                'id' => 1,
                'name' => 'Credit Approval Decision',
                'dmn_model' => 'credit-approval-v1',
                'result_mappings' => [
                    'decision' => 'field_approval_status',
                    'interest_rate' => 'field_interest_rate'
                ],
                'active' => true
            ],
            2 => [
                'id' => 2,
                'name' => 'Municipality Benefits Assessment',
                'dmn_model' => 'municipality-benefits-v2',
                'result_mappings' => [
                    'aanmerkingHeusdenPas' => 'field_heusden_pas',
                    'aanmerkingKindPakket' => 'field_kind_pakket'
                ],
                'active' => true
            ]
        ];
    }

    /**
     * Initialize decision table logic
     */
    private function initializeDecisionTables(): void
    {
        $this->decisionTables = [
            'credit-approval-v1' => [
                'inputs' => ['age', 'income', 'credit_score'],
                'outputs' => ['decision', 'interest_rate'],
                'rules' => [
                    [
                        'condition' => fn($d) => $d['age'] >= 25 && $d['income'] >= 50000 && $d['credit_score'] === 'excellent',
                        'result' => ['decision' => 'approved', 'interest_rate' => 3.5]
                    ],
                    [
                        'condition' => fn($d) => $d['age'] >= 21 && $d['income'] >= 30000 && in_array($d['credit_score'], ['good', 'excellent']),
                        'result' => ['decision' => 'approved', 'interest_rate' => 4.2]
                    ],
                    [
                        'condition' => fn($d) => $d['age'] >= 18 && $d['income'] >= 20000,
                        'result' => ['decision' => 'conditional', 'interest_rate' => 6.5]
                    ],
                    [
                        'condition' => fn($d) => true,
                        'result' => ['decision' => 'rejected', 'interest_rate' => null]
                    ]
                ]
            ],
            'municipality-benefits-v2' => [
                'inputs' => ['geboortedatumAanvrager', 'maandelijksBrutoInkomenAanvrager'],
                'outputs' => ['aanmerkingHeusdenPas', 'aanmerkingKindPakket'],
                'rules' => [
                    [
                        'condition' => fn($d) => $this->calculateAge($d['geboortedatumAanvrager']) >= 65 && $d['maandelijksBrutoInkomenAanvrager'] <= 1500,
                        'result' => ['aanmerkingHeusdenPas' => true, 'aanmerkingKindPakket' => false]
                    ],
                    [
                        'condition' => fn($d) => $d['maandelijksBrutoInkomenAanvrager'] <= 1200,
                        'result' => ['aanmerkingHeusdenPas' => true, 'aanmerkingKindPakket' => false]
                    ],
                    [
                        'condition' => fn($d) => true,
                        'result' => ['aanmerkingHeusdenPas' => false, 'aanmerkingKindPakket' => false]
                    ]
                ]
            ]
        ];
    }

    /**
     * Evaluate DMN decision
     */
    public function evaluateDecision(int $configId, array $formData): array
    {
        if ($this->simulateLatency)
        {
            usleep(rand(10000, 300000)); // 10-300ms
        }

        if ($this->shouldSimulateError())
        {
            throw new \Exception('Simulated DMN service error');
        }

        $config = $this->configurations[$configId] ?? null;
        if (!$config)
        {
            throw new \InvalidArgumentException("Configuration {$configId} not found");
        }

        $dmnModel = $config['dmn_model'];
        $decisionTable = $this->decisionTables[$dmnModel] ?? null;

        if (!$decisionTable)
        {
            throw new \Exception("DMN model {$dmnModel} not found");
        }

        $result = $this->executeDecisionTable($decisionTable, $formData);

        // Apply result mappings
        $mappedResult = [];
        foreach ($config['result_mappings'] as $dmnOutput => $fieldMapping)
        {
            if (isset($result[$dmnOutput]))
            {
                $mappedResult[$fieldMapping] = $result[$dmnOutput];
            }
        }

        $executionId = $this->logExecution($configId, $formData, $result);

        return [
            'success' => true,
            'execution_id' => $executionId,
            'config_id' => $configId,
            'decision_results' => $result,
            'result_mappings' => $mappedResult,
            'execution_time' => rand(50, 300) / 1000,
            'confidence' => $this->calculateConfidence($result)
        ];
    }

    /**
     * Execute decision table logic
     */
    private function executeDecisionTable(array $decisionTable, array $inputData): array
    {
        foreach ($decisionTable['rules'] as $rule)
        {
            try
            {
                if ($rule['condition']($inputData))
                {
                    return $rule['result'];
                }
            }
            catch (\Throwable $e)
            {
                continue;
            }
        }
        return ['error' => 'No matching rules found'];
    }

    /**
     * Calculate age from birth date
     */
    private function calculateAge(string $birthDate): int
    {
        try
        {
            $birth = new \DateTime($birthDate);
            $now = new \DateTime();
            return $now->diff($birth)->y;
        }
        catch (\Exception $e)
        {
            return 0;
        }
    }

    /**
     * Get test data sets
     */
    public function getTestDataSets(): array
    {
        return [
            'credit_approval_scenarios' => [
                'high_approval' => [
                    'config_id' => 1,
                    'form_data' => ['age' => 35, 'income' => 75000, 'credit_score' => 'excellent'],
                    'expected_decision' => 'approved'
                ],
                'conditional_approval' => [
                    'config_id' => 1,
                    'form_data' => ['age' => 22, 'income' => 25000, 'credit_score' => 'fair'],
                    'expected_decision' => 'conditional'
                ],
                'rejection' => [
                    'config_id' => 1,
                    'form_data' => ['age' => 17, 'income' => 15000, 'credit_score' => 'poor'],
                    'expected_decision' => 'rejected'
                ]
            ],
            'municipality_scenarios' => [
                'senior_eligible' => [
                    'config_id' => 2,
                    'form_data' => [
                        'geboortedatumAanvrager' => '1950-01-01',
                        'maandelijksBrutoInkomenAanvrager' => 1200
                    ],
                    'expected_results' => ['aanmerkingHeusdenPas' => true]
                ],
                'not_eligible' => [
                    'config_id' => 2,
                    'form_data' => [
                        'geboortedatumAanvrager' => '1990-01-01',
                        'maandelijksBrutoInkomenAanvrager' => 5000
                    ],
                    'expected_results' => ['aanmerkingHeusdenPas' => false]
                ]
            ]
        ];
    }

    /**
     * Helper methods
     */
    public function setSimulateLatency(bool $enabled): void
    {
        $this->simulateLatency = $enabled;
    }

    public function setErrorRate(float $rate): void
    {
        $this->errorRate = max(0, min(1, $rate));
    }

    private function shouldSimulateError(): bool
    {
        return $this->errorRate > 0 && (rand(1, 100) / 100) <= $this->errorRate;
    }

    private function calculateConfidence(array $result): float
    {
        if (isset($result['decision']))
        {
            return match ($result['decision'])
            {
                'approved' => rand(85, 95) / 100,
                'rejected' => rand(80, 90) / 100,
                'conditional' => rand(60, 75) / 100,
                default => rand(50, 70) / 100
            };
        }
        return rand(70, 85) / 100;
    }

    private function logExecution(int $configId, array $inputData, array $result): string
    {
        $executionId = 'exec_' . uniqid();
        $this->executionHistory[$executionId] = [
            'execution_id' => $executionId,
            'config_id' => $configId,
            'input_data' => $inputData,
            'result' => $result,
            'timestamp' => date('c')
        ];
        return $executionId;
    }

    public function getExecutionHistory(): array
    {
        return array_reverse($this->executionHistory);
    }

    public function reset(): void
    {
        $this->executionHistory = [];
        $this->simulateLatency = false;
        $this->errorRate = 0.0;
    }
}
