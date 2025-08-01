<?php

/**
 * Security Tests - Fixed sanitization
 */

declare(strict_types=1);

namespace Operaton\DMN\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    public function testSqlInjectionPrevention(): void
    {
        $maliciousInputs = [
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "admin'/*",
            "1; DELETE FROM forms WHERE 1=1"
        ];

        foreach ($maliciousInputs as $input)
        {
            $sanitized = $this->sanitizeInput($input);
            $this->assertStringNotContainsString("'", $sanitized, "Single quote not removed from: $input");
            $this->assertStringNotContainsString(";", $sanitized, "Semicolon not removed from: $input");
            $this->assertStringNotContainsString("--", $sanitized, "SQL comment not removed from: $input");
            $this->assertStringNotContainsString("DROP", strtoupper($sanitized), "DROP keyword not removed from: $input");
        }
    }

    public function testXssPrevention(): void
    {
        $xssInputs = [
            '<script>alert("xss")</script>',
            '<img src=x onerror=alert(1)>',
            'javascript:alert(1)',
            '<svg onload=alert(1)>'
        ];

        foreach ($xssInputs as $input)
        {
            $sanitized = $this->sanitizeInput($input);
            $this->assertStringNotContainsString('<script', strtolower($sanitized), "Script tag not removed from: $input");
            $this->assertStringNotContainsString('javascript:', strtolower($sanitized), "Javascript protocol not removed from: $input");
            $this->assertStringNotContainsString('onload=', strtolower($sanitized), "Onload event not removed from: $input");
            $this->assertStringNotContainsString('onerror=', strtolower($sanitized), "Onerror event not removed from: $input");
        }
    }

    public function testApiKeyValidation(): void
    {
        $validApiKeys = [
            'sk_test_1234567890abcdef',
            'pk_live_abcdef1234567890'
        ];

        $invalidApiKeys = [
            '',
            'short',
            'invalid-format',
            '123',
            null
        ];

        foreach ($validApiKeys as $key)
        {
            $this->assertTrue($this->isValidApiKey($key), "Valid key failed: $key");
        }

        foreach ($invalidApiKeys as $key)
        {
            $this->assertFalse($this->isValidApiKey($key), "Invalid key passed: $key");
        }
    }

    public function testDataSanitizationEdgeCases(): void
    {
        // Test empty and null inputs
        $this->assertEquals('', $this->sanitizeInput(''));
        $this->assertEquals('', $this->sanitizeInput(null));

        // Test normal text (should remain unchanged)
        $normalText = 'Hello World 123';
        $this->assertEquals($normalText, $this->sanitizeInput($normalText));

        // Test mixed content
        $mixedInput = 'Valid text <script>alert(1)</script> more text';
        $sanitized = $this->sanitizeInput($mixedInput);
        $this->assertStringContainsString('Valid text', $sanitized);
        $this->assertStringContainsString('more text', $sanitized);
        $this->assertStringNotContainsString('<script>', $sanitized);
    }

    private function sanitizeInput(?string $input): string
    {
        if ($input === null)
        {
            return '';
        }

        // Remove HTML tags completely
        $input = strip_tags($input);

        // Remove dangerous SQL characters and keywords
        $sqlPatterns = [
            '/[\'";]/',           // Remove quotes and semicolons
            '/--.*$/',            // Remove SQL comments
            '/\/\*.*?\*\//',      // Remove SQL block comments
            '/\b(DROP|DELETE|INSERT|UPDATE|SELECT|UNION|ALTER)\b/i'  // Remove SQL keywords
        ];

        foreach ($sqlPatterns as $pattern)
        {
            $input = preg_replace($pattern, '', $input);
        }

        // Remove JavaScript protocols and event handlers
        $xssPatterns = [
            '/javascript:/i',
            '/vbscript:/i',
            '/data:/i',
            '/on\w+\s*=/i',       // Remove onload, onerror, etc.
        ];

        foreach ($xssPatterns as $pattern)
        {
            $input = preg_replace($pattern, '', $input);
        }

        // HTML encode remaining content
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        // Trim whitespace
        return trim($input);
    }

    private function isValidApiKey($key): bool
    {
        if (!is_string($key) || strlen($key) < 16)
        {
            return false;
        }

        return preg_match('/^(sk|pk)_(test|live)_[a-f0-9]{16}$/', $key) === 1;
    }
}
