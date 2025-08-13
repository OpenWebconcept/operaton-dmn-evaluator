<?php

/**
 * Enhanced DMN API Tests - Comprehensive Unit Testing with OpenAPI Coverage
 * Covers decision evaluation, validation, error handling, and advanced scenarios
 * Based on Operaton DMN REST API specification and WordPress integration patterns
 */

declare(strict_types=1);

namespace Operaton\DMN\Tests\Unit;

use PHPUnit\Framework\TestCase;

class DmnApiTest extends TestCase
{
    private $apiManager;
    private $mockService;

    protected function setUp(): void
    {
        // Make sure the mock classes are loaded
        require_once dirname(__DIR__) . '/fixtures/mock-classes.php';
        require_once dirname(__DIR__) . '/helpers/test-helper.php';

        // Load the ExtendedMockDmnService if it exists
        $extendedMockPath = dirname(__DIR__) . '/fixtures/ExtendedMockDmnService.php';
        if (file_exists($extendedMockPath))
        {
            require_once $extendedMockPath;
        }

        // Create instance using fully qualified class name
        $this->apiManager = new \Operaton\DMN\Tests\Fixtures\MockDmnApi();

        // Only create ExtendedMockDmnService if the class exists
        if (class_exists('\Operaton\DMN\Tests\Fixtures\ExtendedMockDmnService'))
        {
            $this->mockService = new \Operaton\DMN\Tests\Fixtures\ExtendedMockDmnService();
        }
        else
        {
            // Fallback to regular MockDmnApi for extended functionality
            $this->mockService = $this->apiManager;
        }
    }

    protected function tearDown(): void
    {
        // Clean teardown
    }

    /**
     * Helper method to check if extended mock service is available
     */
    private function isExtendedMockAvailable(): bool
    {
        return class_exists('\Operaton\DMN\Tests\Fixtures\ExtendedMockDmnService') &&
            $this->mockService instanceof \Operaton\DMN\Tests\Fixtures\ExtendedMockDmnService;
    }

    public function testApiManagerExists(): void
    {
        $this->assertInstanceOf(\Operaton\DMN\Tests\Fixtures\MockDmnApi::class, $this->apiManager);
    }

    /**
     * Test basic DMN evaluation with valid data
     */
    public function testEvaluateDmnWithValidData(): void
    {
        $testData = [
            'age' => 30,
            'income' => 75000,
            'credit_score' => 'excellent'
        ];

        $result = $this->apiManager->evaluateDmn($testData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('decision', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertEquals('approved', $result['decision']);
        $this->assertGreaterThan(0.5, $result['confidence']);
    }

    /**
     * Test all dish decision table scenarios based on OpenAPI spec patterns
     */
    public function testDishDecisionTableScenarios(): void
    {
        // Skip if ExtendedMockDmnService is not available
        if (!method_exists($this->mockService, 'evaluateDishDecision'))
        {
            $this->markTestSkipped('ExtendedMockDmnService not available - this test requires the extended mock service');
            return;
        }

        $dishScenarios = [
            // Rule 1: Fall season, ≤8 guests → Spareribs
            ['season' => 'Fall', 'guestCount' => 6, 'expected' => 'spareribs'],
            ['season' => 'Fall', 'guestCount' => 8, 'expected' => 'spareribs'],

            // Rule 2: Winter season, ≤8 guests → Roastbeef
            ['season' => 'Winter', 'guestCount' => 4, 'expected' => 'roastbeef'],
            ['season' => 'Winter', 'guestCount' => 8, 'expected' => 'roastbeef'],

            // Rule 3: Spring season, ≤4 guests → Dry aged gourmet steak
            ['season' => 'Spring', 'guestCount' => 3, 'expected' => 'gourmet'],
            ['season' => 'Spring', 'guestCount' => 4, 'expected' => 'gourmet'],

            // Rule 4: Spring season, 5-8 guests → Steak
            ['season' => 'Spring', 'guestCount' => 5, 'expected' => 'steak'],
            ['season' => 'Spring', 'guestCount' => 7, 'expected' => 'steak'],

            // Rule 5: Any season except Summer, >8 guests → Stew
            ['season' => 'Winter', 'guestCount' => 12, 'expected' => 'stew'],
            ['season' => 'Fall', 'guestCount' => 10, 'expected' => 'stew'],
            ['season' => 'Spring', 'guestCount' => 15, 'expected' => 'stew'],

            // Rule 6: Summer season, any guest count → Light salad and nice steak
            ['season' => 'Summer', 'guestCount' => 3, 'expected' => 'salad'],
            ['season' => 'Summer', 'guestCount' => 8, 'expected' => 'salad'],
            ['season' => 'Summer', 'guestCount' => 15, 'expected' => 'salad'],
        ];

        foreach ($dishScenarios as $scenario)
        {
            $result = $this->mockService->evaluateDishDecision($scenario['season'], $scenario['guestCount']);

            $this->assertIsArray($result, "Result should be an array for {$scenario['season']} + {$scenario['guestCount']}");
            $this->assertArrayHasKey('desiredDish', $result, "Should contain desiredDish for {$scenario['season']} + {$scenario['guestCount']}");

            $dish = strtolower($result['desiredDish']);
            $expected = strtolower($scenario['expected']);

            $this->assertStringContainsString(
                $expected,
                $dish,
                "Expected '{$expected}' in result '{$dish}' for {$scenario['season']} + {$scenario['guestCount']} guests"
            );
        }
    }

    /**
     * Test DMN variable type validation based on OpenAPI spec
     */
    public function testDmnVariableTypeValidation(): void
    {
        if (!method_exists($this->mockService, 'validateVariableType'))
        {
            $this->markTestSkipped('ExtendedMockDmnService not available - this test requires the extended mock service');
            return;
        }

        $variableTests = [
            // String variables
            ['season' => 'Summer', 'type' => 'String', 'valid' => true],
            ['season' => 123, 'type' => 'String', 'valid' => false],

            // Integer variables
            ['guestCount' => 8, 'type' => 'Integer', 'valid' => true],
            ['guestCount' => '8', 'type' => 'Integer', 'valid' => true], // Should convert
            ['guestCount' => 'eight', 'type' => 'Integer', 'valid' => false],

            // Boolean variables
            ['premium' => true, 'type' => 'Boolean', 'valid' => true],
            ['premium' => 'true', 'type' => 'Boolean', 'valid' => true], // Should convert
            ['premium' => 'maybe', 'type' => 'Boolean', 'valid' => false],
        ];

        foreach ($variableTests as $test)
        {
            $key = array_key_first($test);
            $value = $test[$key];
            $expectedValid = $test['valid'];

            $result = $this->mockService->validateVariableType($key, $value, $test['type']);

            if ($expectedValid)
            {
                $this->assertTrue($result, "Variable {$key}={$value} should be valid for type {$test['type']}");
            }
            else
            {
                $this->assertFalse($result, "Variable {$key}={$value} should be invalid for type {$test['type']}");
            }
        }
    }

    /**
     * Test DMN evaluation error scenarios based on OpenAPI error responses
     */
    public function testDmnEvaluationErrorScenarios(): void
    {
        if (!method_exists($this->mockService, 'evaluateDishDecisionWithValidation'))
        {
            $this->markTestSkipped('ExtendedMockDmnService not available - this test requires the extended mock service');
            return;
        }

        $errorScenarios = [
            [
                'name' => 'Missing required variables',
                'data' => ['season' => 'Summer'], // Missing guestCount
                'expectedException' => 'InvalidArgumentException',
                'expectedMessage' => 'required variable'
            ],
            [
                'name' => 'Invalid season value',
                'data' => ['season' => 'InvalidSeason', 'guestCount' => 8],
                'expectedException' => 'InvalidArgumentException',
                'expectedMessage' => 'Invalid season'
            ],
            [
                'name' => 'Negative guest count',
                'data' => ['season' => 'Summer', 'guestCount' => -5],
                'expectedException' => 'InvalidArgumentException',
                'expectedMessage' => 'Guest count must be positive'
            ],
            [
                'name' => 'Extremely large guest count',
                'data' => ['season' => 'Summer', 'guestCount' => 999999],
                'expectedException' => 'InvalidArgumentException',
                'expectedMessage' => 'Guest count too large'
            ]
        ];

        foreach ($errorScenarios as $scenario)
        {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessageMatches('/' . preg_quote($scenario['expectedMessage'], '/') . '/i');

            $this->mockService->evaluateDishDecisionWithValidation(
                $scenario['data']['season'] ?? null,
                $scenario['data']['guestCount'] ?? null
            );
        }
    }

    /**
     * Test DMN decision definition metadata (simulating OpenAPI spec)
     */
    public function testDecisionDefinitionMetadata(): void
    {
        if (!$this->isExtendedMockAvailable() || !method_exists($this->mockService, 'getDecisionDefinitionMetadata'))
        {
            $this->markTestSkipped('ExtendedMockDmnService not available');
            return;
        }

        $metadata = $this->mockService->getDecisionDefinitionMetadata('dish');

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('id', $metadata);
        $this->assertArrayHasKey('key', $metadata);
        $this->assertArrayHasKey('version', $metadata);
        $this->assertArrayHasKey('deploymentId', $metadata);

        $this->assertEquals('dish', $metadata['key']);
        $this->assertIsString($metadata['id']);
        $this->assertIsInt($metadata['version']);
        $this->assertGreaterThan(0, $metadata['version']);
    }

    /**
     * Test DMN evaluation with all supported data types (OpenAPI spec coverage)
     */
    public function testDmnEvaluationWithVariousDataTypes(): void
    {
        if (!$this->isExtendedMockAvailable() || !method_exists($this->mockService, 'evaluateWithTypedVariables'))
        {
            $this->markTestSkipped('ExtendedMockDmnService not available');
            return;
        }

        $dataTypeTests = [
            [
                'name' => 'String and Integer types',
                'variables' => [
                    'season' => ['value' => 'Winter', 'type' => 'String'],
                    'guestCount' => ['value' => 4, 'type' => 'Integer']
                ],
                'expectedResult' => 'roastbeef'
            ],
            [
                'name' => 'Boolean and Double types',
                'variables' => [
                    'premium' => ['value' => true, 'type' => 'Boolean'],
                    'budget' => ['value' => 150.50, 'type' => 'Double'],
                    'season' => ['value' => 'Summer', 'type' => 'String'],
                    'guestCount' => ['value' => 6, 'type' => 'Integer']
                ],
                'expectedResult' => 'salad'
            ],
            [
                'name' => 'Date type handling',
                'variables' => [
                    'eventDate' => ['value' => '2024-12-25T00:00:00Z', 'type' => 'Date'],
                    'season' => ['value' => 'Winter', 'type' => 'String'],
                    'guestCount' => ['value' => 12, 'type' => 'Integer']
                ],
                'expectedResult' => 'stew'
            ]
        ];

        foreach ($dataTypeTests as $test)
        {
            $result = $this->mockService->evaluateWithTypedVariables($test['variables']);

            $this->assertIsArray($result, "Result should be array for: " . $test['name']);
            $this->assertArrayHasKey('desiredDish', $result, "Should contain desiredDish for: " . $test['name']);

            $dish = strtolower($result['desiredDish']);
            $expected = strtolower($test['expectedResult']);

            $this->assertStringContainsString(
                $expected,
                $dish,
                "Expected '{$expected}' in result for: " . $test['name']
            );
        }
    }

    /**
     * Test DMN evaluation performance and caching
     */
    public function testDmnEvaluationPerformance(): void
    {
        if (!$this->isExtendedMockAvailable() || !method_exists($this->mockService, 'evaluateDishDecision'))
        {
            // Fallback to basic API manager test
            $testData = ['season' => 'Summer', 'guestCount' => 8];

            $startTime = microtime(true);
            $result1 = $this->apiManager->evaluateDmn($testData);
            $firstTime = microtime(true) - $startTime;

            $startTime = microtime(true);
            $result2 = $this->apiManager->evaluateDmn($testData);
            $secondTime = microtime(true) - $startTime;

            $this->assertIsArray($result1);
            $this->assertIsArray($result2);
            $this->assertLessThan(1.0, $firstTime);
            $this->assertLessThan(1.0, $secondTime);
            return;
        }

        $testData = ['season' => 'Summer', 'guestCount' => 8];

        // First evaluation (no cache)
        $startTime = microtime(true);
        $result1 = $this->mockService->evaluateDishDecision($testData['season'], $testData['guestCount']);
        $firstEvaluationTime = microtime(true) - $startTime;

        // Second evaluation (with cache)
        $startTime = microtime(true);
        $result2 = $this->mockService->evaluateDishDecision($testData['season'], $testData['guestCount']);
        $secondEvaluationTime = microtime(true) - $startTime;

        // Results should be identical
        $this->assertEquals($result1, $result2, 'Cached result should match original');

        // Performance should be reasonable (under 1 second for mock)
        $this->assertLessThan(1.0, $firstEvaluationTime, 'First evaluation should be reasonably fast');
        $this->assertLessThan(1.0, $secondEvaluationTime, 'Cached evaluation should be reasonably fast');

        // Cached evaluation might be faster (but not required for mock)
        $this->assertGreaterThan(0, $firstEvaluationTime, 'Should take some measurable time');
    }

    /**
     * Test DMN engine connection and health checks
     */
    public function testDmnEngineHealthChecks(): void
    {
        if (!$this->isExtendedMockAvailable())
        {
            // Fallback to basic connection test
            $this->assertTrue($this->apiManager->testConnection());
            return;
        }

        // Test engine availability
        $isAvailable = $this->mockService->checkEngineAvailability();
        $this->assertTrue($isAvailable, 'Mock engine should be available');

        // Test engine version
        $version = $this->mockService->getEngineVersion();
        $this->assertIsString($version, 'Engine version should be string');
        $this->assertMatchesRegularExpression('/^\d+\.\d+/', $version, 'Version should be in semantic format');

        // Test engine capabilities
        $capabilities = $this->mockService->getEngineCapabilities();
        $this->assertIsArray($capabilities, 'Capabilities should be array');
        $this->assertContains('DMN_1_1', $capabilities, 'Should support DMN 1.1');
        $this->assertContains('DMN_1_3', $capabilities, 'Should support DMN 1.3');
    }

    /**
     * Test DMN evaluation history and audit trail (OpenAPI spec)
     */
    public function testDmnEvaluationHistory(): void
    {
        if (!$this->isExtendedMockAvailable() || !method_exists($this->mockService, 'getEvaluationHistory'))
        {
            $this->markTestSkipped('ExtendedMockDmnService not available');
            return;
        }

        // Perform several evaluations
        $evaluations = [
            ['season' => 'Summer', 'guestCount' => 8],
            ['season' => 'Winter', 'guestCount' => 4],
            ['season' => 'Fall', 'guestCount' => 6]
        ];

        foreach ($evaluations as $data)
        {
            $this->mockService->evaluateDishDecision($data['season'], $data['guestCount']);
        }

        // Get evaluation history
        $history = $this->mockService->getEvaluationHistory();

        $this->assertIsArray($history, 'History should be an array');
        $this->assertGreaterThanOrEqual(3, count($history), 'Should have at least 3 evaluations');

        // Check history entry structure
        $lastEntry = $history[0]; // Most recent
        $this->assertArrayHasKey('id', $lastEntry);
        $this->assertArrayHasKey('decisionDefinitionKey', $lastEntry);
        $this->assertArrayHasKey('evaluationTime', $lastEntry);
        $this->assertArrayHasKey('inputVariables', $lastEntry);
        $this->assertArrayHasKey('outputVariables', $lastEntry);

        $this->assertEquals('dish', $lastEntry['decisionDefinitionKey']);
    }

    /**
     * Test original API tests for backward compatibility
     */
    public function testEvaluateDmnWithEmptyDataThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Data cannot be empty');

        $this->apiManager->evaluateDmn([]);
    }

    public function testEvaluateDmnWithLowIncomeReturnsRejection(): void
    {
        $testData = [
            'age' => 25,
            'income' => 15000,
            'credit_score' => 'poor'
        ];

        $result = $this->apiManager->evaluateDmn($testData);

        $this->assertEquals('rejected', $result['decision']);
        $this->assertArrayHasKey('reasoning', $result);
    }

    public function testEvaluateDmnWithConditionalApproval(): void
    {
        $testData = [
            'age' => 22,
            'income' => 35000,
            'credit_score' => 'fair'
        ];

        $result = $this->apiManager->evaluateDmn($testData);

        $this->assertEquals('conditional', $result['decision']);
        $this->assertGreaterThan(0.5, $result['confidence']);
        $this->assertLessThan(0.9, $result['confidence']);
    }

    public function testValidateApiKeyWithValidKey(): void
    {
        $result = $this->apiManager->validateApiKey('test-valid-key');
        $this->assertTrue($result);
    }

    public function testValidateApiKeyWithInvalidKey(): void
    {
        $result = $this->apiManager->validateApiKey('invalid-key');
        $this->assertFalse($result);
    }

    public function testTestConnectionWhenHealthy(): void
    {
        $this->assertTrue($this->apiManager->testConnection());
    }

    public function testTestConnectionWithError(): void
    {
        $this->apiManager->setMockError('Connection failed');
        $this->assertFalse($this->apiManager->testConnection());
    }

    public function testCustomMockResponse(): void
    {
        $customResponse = \Operaton\DMN\Tests\Helpers\TestHelper::mockDmnResponse('custom_decision', 0.95);
        $this->apiManager->setMockResponse($customResponse);

        $result = $this->apiManager->evaluateDmn(['test' => 'data']);

        $this->assertEquals('custom_decision', $result['decision']);
        $this->assertEquals(0.95, $result['confidence']);
    }

    /**
     * Test DMN evaluation with concurrent requests (performance testing)
     */
    public function testConcurrentDmnEvaluations(): void
    {
        if (!$this->isExtendedMockAvailable() || !method_exists($this->mockService, 'evaluateDishDecision'))
        {
            // Fallback to basic API test
            $scenarios = [
                ['age' => 30, 'income' => 75000, 'credit_score' => 'excellent'],
                ['age' => 25, 'income' => 50000, 'credit_score' => 'good'],
                ['age' => 35, 'income' => 80000, 'credit_score' => 'excellent'],
                ['age' => 28, 'income' => 60000, 'credit_score' => 'fair']
            ];

            $results = [];
            $startTime = microtime(true);

            foreach ($scenarios as $scenario)
            {
                $results[] = $this->apiManager->evaluateDmn($scenario);
            }

            $totalTime = microtime(true) - $startTime;

            $this->assertCount(4, $results);
            $this->assertLessThan(2.0, $totalTime);
            return;
        }

        $scenarios = [
            ['season' => 'Summer', 'guestCount' => 8],
            ['season' => 'Winter', 'guestCount' => 4],
            ['season' => 'Fall', 'guestCount' => 6],
            ['season' => 'Spring', 'guestCount' => 3]
        ];

        $startTime = microtime(true);
        $results = [];

        // Simulate concurrent evaluations
        foreach ($scenarios as $scenario)
        {
            $results[] = $this->mockService->evaluateDishDecision(
                $scenario['season'],
                $scenario['guestCount']
            );
        }

        $totalTime = microtime(true) - $startTime;

        // All evaluations should succeed
        $this->assertCount(4, $results, 'Should have 4 evaluation results');

        foreach ($results as $result)
        {
            $this->assertIsArray($result, 'Each result should be an array');
            $this->assertArrayHasKey('desiredDish', $result, 'Each result should have desiredDish');
        }

        // Performance should be reasonable for 4 evaluations
        $this->assertLessThan(2.0, $totalTime, 'Concurrent evaluations should complete in reasonable time');
    }

    /**
     * Test DMN evaluation with edge cases and boundary conditions
     */
    public function testDmnEvaluationEdgeCases(): void
    {
        if (!$this->isExtendedMockAvailable() || !method_exists($this->mockService, 'evaluateDishDecision'))
        {
            $this->markTestSkipped('ExtendedMockDmnService not available');
            return;
        }

        $edgeCases = [
            // Boundary conditions for guest count
            ['season' => 'Spring', 'guestCount' => 4, 'description' => 'Spring exactly 4 guests (boundary)'],
            ['season' => 'Spring', 'guestCount' => 5, 'description' => 'Spring exactly 5 guests (boundary)'],
            ['season' => 'Fall', 'guestCount' => 8, 'description' => 'Fall exactly 8 guests (boundary)'],
            ['season' => 'Winter', 'guestCount' => 9, 'description' => 'Winter exactly 9 guests (boundary)'],

            // Minimum and maximum reasonable values
            ['season' => 'Summer', 'guestCount' => 1, 'description' => 'Minimum guest count'],
            ['season' => 'Winter', 'guestCount' => 100, 'description' => 'Large guest count'],
        ];

        foreach ($edgeCases as $case)
        {
            $result = $this->mockService->evaluateDishDecision($case['season'], $case['guestCount']);

            $this->assertIsArray($result, "Should handle edge case: " . $case['description']);
            $this->assertArrayHasKey('desiredDish', $result, "Should return dish for: " . $case['description']);
            $this->assertNotEmpty($result['desiredDish'], "Dish should not be empty for: " . $case['description']);
        }
    }

    /**
     * Test DMN evaluation with internationalization (i18n) support
     */
    public function testDmnEvaluationInternationalization(): void
    {
        if (!$this->isExtendedMockAvailable() || !method_exists($this->mockService, 'evaluateDishDecisionWithLocale'))
        {
            $this->markTestSkipped('ExtendedMockDmnService not available');
            return;
        }

        $i18nTests = [
            // Different language seasons
            ['season' => 'Sommer', 'guestCount' => 8, 'locale' => 'de', 'description' => 'German season name'],
            ['season' => 'Été', 'guestCount' => 8, 'locale' => 'fr', 'description' => 'French season name'],
            ['season' => 'Verano', 'guestCount' => 8, 'locale' => 'es', 'description' => 'Spanish season name'],
        ];

        foreach ($i18nTests as $test)
        {
            // Test with locale support if available
            $result = $this->mockService->evaluateDishDecisionWithLocale(
                $test['season'],
                $test['guestCount'],
                $test['locale']
            );

            $this->assertIsArray($result, "Should handle i18n case: " . $test['description']);

            // Should either return a result or provide appropriate fallback
            $this->assertTrue(
                isset($result['desiredDish']) || isset($result['error']),
                "Should return dish or error for: " . $test['description']
            );
        }
    }

    /**
     * Test DMN evaluation result validation and schema compliance
     */
    public function testDmnResultValidation(): void
    {
        if (!$this->isExtendedMockAvailable() || !method_exists($this->mockService, 'evaluateDishDecision'))
        {
            // Fallback to basic result validation
            $result = $this->apiManager->evaluateDmn(['age' => 30, 'income' => 75000, 'credit_score' => 'excellent']);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('decision', $result);
            return;
        }

        $result = $this->mockService->evaluateDishDecision('Summer', 8);

        // Validate result structure
        $this->assertIsArray($result, 'Result should be an array');

        // Required fields
        $this->assertArrayHasKey('desiredDish', $result, 'Should contain desiredDish');
        $this->assertIsString($result['desiredDish'], 'desiredDish should be string');
        $this->assertNotEmpty($result['desiredDish'], 'desiredDish should not be empty');

        // Optional but expected fields
        if (isset($result['confidence']))
        {
            $this->assertIsFloat($result['confidence'], 'confidence should be float');
            $this->assertGreaterThanOrEqual(0.0, $result['confidence'], 'confidence should be >= 0');
            $this->assertLessThanOrEqual(1.0, $result['confidence'], 'confidence should be <= 1');
        }

        if (isset($result['evaluationTime']))
        {
            $this->assertIsString($result['evaluationTime'], 'evaluationTime should be string');
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}/', $result['evaluationTime'], 'evaluationTime should be ISO format');
        }

        if (isset($result['ruleMatches']))
        {
            $this->assertIsArray($result['ruleMatches'], 'ruleMatches should be array');
        }
    }
}
