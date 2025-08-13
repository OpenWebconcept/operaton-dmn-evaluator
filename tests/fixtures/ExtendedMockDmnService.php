<?php

/**
 * Extended Mock DMN Service - Enhanced with OpenAPI Coverage
 * Provides comprehensive mock functionality for both original and enhanced unit testing
 * Maintains backward compatibility while adding new OpenAPI spec features
 */

declare(strict_types=1);

namespace Operaton\DMN\Tests\Fixtures;

class ExtendedMockDmnService
{
    private array $configurations = [];
    private array $decisionTables = [];
    private array $executionHistory = [];
    private array $evaluationHistory = [];
    private bool $simulateLatency = false;
    private float $errorRate = 0.0;
    private bool $isHealthy = true;
    private string $version = '1.0.0-beta-4-SNAPSHOT';
    private array $capabilities = ['DMN_1_1', 'DMN_1_3'];

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

    // ========================================
    // ORIGINAL METHODS (for backward compatibility)
    // ========================================

    /**
     * Evaluate DMN decision (original method)
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
     * Get test data sets (original method)
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
     * Reset method (original)
     */
    public function reset(): void
    {
        $this->executionHistory = [];
        $this->evaluationHistory = [];
        $this->simulateLatency = false;
        $this->errorRate = 0.0;
        $this->isHealthy = true;
    }

    // ========================================
    // NEW ENHANCED METHODS (for OpenAPI coverage)
    // ========================================

    /**
     * Evaluate dish decision based on season and guest count
     * Implements the complete decision table logic
     */
    public function evaluateDishDecision(string $season, int $guestCount): array
    {
        $result = $this->determineDish($season, $guestCount);

        // Record in history
        $this->evaluationHistory[] = [
            'id' => $this->generateId(),
            'decisionDefinitionKey' => 'dish',
            'evaluationTime' => date('c'),
            'inputVariables' => [
                'season' => $season,
                'guestCount' => $guestCount
            ],
            'outputVariables' => $result
        ];

        return $result;
    }

    /**
     * Evaluate dish decision with validation
     */
    public function evaluateDishDecisionWithValidation(?string $season, ?int $guestCount): array
    {
        if ($season === null)
        {
            throw new \InvalidArgumentException('Season is a required variable');
        }

        if ($guestCount === null)
        {
            throw new \InvalidArgumentException('GuestCount is a required variable');
        }

        if (!in_array($season, ['Spring', 'Summer', 'Fall', 'Winter']))
        {
            throw new \InvalidArgumentException('Invalid season value');
        }

        if ($guestCount < 0)
        {
            throw new \InvalidArgumentException('Guest count must be positive');
        }

        if ($guestCount > 1000)
        {
            throw new \InvalidArgumentException('Guest count too large');
        }

        return $this->evaluateDishDecision($season, $guestCount);
    }

    /**
     * Evaluate with typed variables (OpenAPI spec format)
     */
    public function evaluateWithTypedVariables(array $variables): array
    {
        $season = $variables['season']['value'] ?? null;
        $guestCount = $variables['guestCount']['value'] ?? null;

        // Handle type conversion
        if (isset($variables['guestCount']['type']) && $variables['guestCount']['type'] === 'Integer')
        {
            $guestCount = (int) $guestCount;
        }

        return $this->evaluateDishDecision($season, $guestCount);
    }

    /**
     * Evaluate with locale support
     */
    public function evaluateDishDecisionWithLocale(string $season, int $guestCount, string $locale): array
    {
        // Simple locale mapping
        $seasonMapping = [
            'de' => ['Sommer' => 'Summer', 'Winter' => 'Winter', 'Frühling' => 'Spring', 'Herbst' => 'Fall'],
            'fr' => ['Été' => 'Summer', 'Hiver' => 'Winter', 'Printemps' => 'Spring', 'Automne' => 'Fall'],
            'es' => ['Verano' => 'Summer', 'Invierno' => 'Winter', 'Primavera' => 'Spring', 'Otoño' => 'Fall']
        ];

        if (isset($seasonMapping[$locale][$season]))
        {
            $season = $seasonMapping[$locale][$season];
        }
        elseif (!in_array($season, ['Spring', 'Summer', 'Fall', 'Winter']))
        {
            return ['error' => 'Unsupported season in locale ' . $locale];
        }

        return $this->evaluateDishDecision($season, $guestCount);
    }

    /**
     * Validate variable type
     */
    public function validateVariableType(string $key, $value, string $type): bool
    {
        switch ($type)
        {
            case 'String':
                return is_string($value);
            case 'Integer':
                return is_int($value) || (is_string($value) && is_numeric($value));
            case 'Boolean':
                return is_bool($value) || in_array($value, ['true', 'false', '1', '0'], true);
            case 'Double':
                return is_float($value) || is_numeric($value);
            case 'Date':
                return is_string($value) && strtotime($value) !== false;
            default:
                return false;
        }
    }

    /**
     * Get decision definition metadata
     */
    public function getDecisionDefinitionMetadata(string $key): array
    {
        return [
            'id' => $key . ':1:' . $this->generateId(),
            'key' => $key,
            'name' => ucfirst($key),
            'version' => 1,
            'deploymentId' => $this->generateId(),
            'resource' => $key . '.dmn'
        ];
    }

    /**
     * Check engine availability
     */
    public function checkEngineAvailability(): bool
    {
        return $this->isHealthy;
    }

    /**
     * Get engine version
     */
    public function getEngineVersion(): string
    {
        return $this->version;
    }

    /**
     * Get engine capabilities
     */
    public function getEngineCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Get evaluation history (new method)
     */
    public function getEvaluationHistory(): array
    {
        return array_reverse($this->evaluationHistory); // Most recent first
    }

    // ========================================
    // PRIVATE HELPER METHODS
    // ========================================

    /**
     * Determine dish based on decision table logic
     */
    private function determineDish(string $season, int $guestCount): array
    {
        $dish = '';
        $confidence = 1.0;

        // Decision Table Logic (as per your DMN spec)
        if ($season === 'Fall' && $guestCount <= 8)
        {
            $dish = 'Spareribs'; // Rule 1
        }
        elseif ($season === 'Winter' && $guestCount <= 8)
        {
            $dish = 'Roastbeef'; // Rule 2
        }
        elseif ($season === 'Spring' && $guestCount <= 4)
        {
            $dish = 'Dry Aged Gourmet Steak'; // Rule 3
        }
        elseif ($season === 'Spring' && $guestCount >= 5 && $guestCount <= 8)
        {
            $dish = 'Steak'; // Rule 4
        }
        elseif (in_array($season, ['Fall', 'Winter', 'Spring']) && $guestCount > 8)
        {
            $dish = 'Stew'; // Rule 5
        }
        elseif ($season === 'Summer')
        {
            $dish = 'Light Salad and nice Steak'; // Rule 6
        }
        else
        {
            $dish = 'Default Meal';
            $confidence = 0.5;
        }

        return [
            'desiredDish' => $dish,
            'confidence' => $confidence,
            'evaluationTime' => date('c'),
            'ruleMatches' => [$this->determineRuleNumber($season, $guestCount)]
        ];
    }

    /**
     * Determine which rule was matched
     */
    private function determineRuleNumber(string $season, int $guestCount): int
    {
        if ($season === 'Fall' && $guestCount <= 8) return 1;
        if ($season === 'Winter' && $guestCount <= 8) return 2;
        if ($season === 'Spring' && $guestCount <= 4) return 3;
        if ($season === 'Spring' && $guestCount >= 5 && $guestCount <= 8) return 4;
        if (in_array($season, ['Fall', 'Winter', 'Spring']) && $guestCount > 8) return 5;
        if ($season === 'Summer') return 6;
        return 0; // No rule matched
    }

    /**
     * Execute decision table logic (original method)
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
     * Calculate age from birth date (original method)
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
     * Generate a unique ID
     */
    private function generateId(): string
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6))
        );
    }

    /**
     * Original helper methods for backward compatibility
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

    /**
     * Set mock error state
     */
    public function setMockError(string $error): void
    {
        $this->isHealthy = false;
    }

    /**
     * Reset to healthy state
     */
    public function resetToHealthy(): void
    {
        $this->isHealthy = true;
    }
}
