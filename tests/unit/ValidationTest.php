<?php

/**
 * Validation Tests
 */

declare(strict_types=1);

namespace Operaton\DMN\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    public function testEmailValidation(): void
    {
        $validEmails = [
            'test@example.com',
            'user.name@domain.co.uk',
            'admin+tag@site.org'
        ];

        $invalidEmails = [
            'invalid-email',
            '@domain.com',
            'user@',
            ''
        ];

        foreach ($validEmails as $email)
        {
            $this->assertTrue(filter_var($email, FILTER_VALIDATE_EMAIL) !== false, "Failed for: $email");
        }

        foreach ($invalidEmails as $email)
        {
            $this->assertFalse(filter_var($email, FILTER_VALIDATE_EMAIL) !== false, "Failed for: $email");
        }
    }

    public function testAgeValidation(): void
    {
        $validAges = [18, 25, 65, 100];
        $invalidAges = [0, -5, 17, 150, 'abc'];

        foreach ($validAges as $age)
        {
            $this->assertTrue($this->isValidAge($age), "Valid age failed: $age");
        }

        foreach ($invalidAges as $age)
        {
            $this->assertFalse($this->isValidAge($age), "Invalid age passed: $age");
        }
    }

    public function testIncomeValidation(): void
    {
        $this->assertTrue($this->isValidIncome(50000));
        $this->assertTrue($this->isValidIncome(0));
        $this->assertFalse($this->isValidIncome(-1000));
        $this->assertFalse($this->isValidIncome('invalid'));
    }

    private function isValidAge($age): bool
    {
        return is_numeric($age) && $age >= 18 && $age <= 120;
    }

    private function isValidIncome($income): bool
    {
        return is_numeric($income) && $income >= 0;
    }
}
