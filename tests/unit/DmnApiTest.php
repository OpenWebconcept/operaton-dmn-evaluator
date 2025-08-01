<?php

/**
 * Clean DMN API Tests - Fixed imports
 */

declare(strict_types=1);

namespace Operaton\DMN\Tests\Unit;

use PHPUnit\Framework\TestCase;

// Don't import classes - we'll access them after requiring the files

class DmnApiTest extends TestCase
{
    private $apiManager; // Remove type hint for now

    protected function setUp(): void
    {
        // Make sure the mock classes are loaded
        require_once dirname(__DIR__) . '/fixtures/mock-classes.php';
        require_once dirname(__DIR__) . '/helpers/test-helper.php';

        // Create instance using fully qualified class name
        $this->apiManager = new \Operaton\DMN\Tests\Fixtures\MockDmnApi();
    }

    protected function tearDown(): void
    {
        // Clean teardown without null assignment
    }

    public function testApiManagerExists(): void
    {
        $this->assertInstanceOf(\Operaton\DMN\Tests\Fixtures\MockDmnApi::class, $this->apiManager);
    }

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
}
