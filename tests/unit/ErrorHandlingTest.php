<?php

/**
 * Error Handling Tests
 */

declare(strict_types=1);

namespace Operaton\DMN\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ErrorHandlingTest extends TestCase
{
    public function testNetworkTimeoutHandling(): void
    {
        $errorHandler = new class {
            public function handleNetworkError(string $errorType): array
            {
                switch ($errorType)
                {
                    case 'timeout':
                        return [
                            'success' => false,
                            'error' => 'Request timed out',
                            'retry_after' => 30,
                            'fallback_available' => true
                        ];
                    case 'connection_refused':
                        return [
                            'success' => false,
                            'error' => 'Connection refused',
                            'retry_after' => 60,
                            'fallback_available' => false
                        ];
                    default:
                        return [
                            'success' => false,
                            'error' => 'Unknown error',
                            'retry_after' => 120,
                            'fallback_available' => false
                        ];
                }
            }
        };

        $timeoutResult = $errorHandler->handleNetworkError('timeout');
        $this->assertFalse($timeoutResult['success']);
        $this->assertEquals(30, $timeoutResult['retry_after']);
        $this->assertTrue($timeoutResult['fallback_available']);

        $connectionResult = $errorHandler->handleNetworkError('connection_refused');
        $this->assertFalse($connectionResult['success']);
        $this->assertEquals(60, $connectionResult['retry_after']);
        $this->assertFalse($connectionResult['fallback_available']);
    }

    public function testInvalidDataHandling(): void
    {
        $dataValidator = new class {
            public function validateFormData(array $data): array
            {
                $errors = [];

                if (empty($data['name']))
                {
                    $errors[] = 'Name is required';
                }

                if (!isset($data['age']) || !is_numeric($data['age']) || $data['age'] < 18)
                {
                    $errors[] = 'Valid age (18+) is required';
                }

                if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
                {
                    $errors[] = 'Valid email is required';
                }

                return [
                    'valid' => empty($errors),
                    'errors' => $errors
                ];
            }
        };

        $validData = [
            'name' => 'John Doe',
            'age' => 30,
            'email' => 'john@example.com'
        ];

        $invalidData = [
            'name' => '',
            'age' => 16,
            'email' => 'invalid-email'
        ];

        $validResult = $dataValidator->validateFormData($validData);
        $this->assertTrue($validResult['valid']);
        $this->assertEmpty($validResult['errors']);

        $invalidResult = $dataValidator->validateFormData($invalidData);
        $this->assertFalse($invalidResult['valid']);
        $this->assertCount(3, $invalidResult['errors']);
    }
}
