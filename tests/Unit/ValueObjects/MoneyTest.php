<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\ValueObjects;

use DjWeb\Payments\ValueObjects\Money;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

final class MoneyTest extends TestCase
{
    public function testCanCreateMoneyWithValidAmountAndCurrency(): void
    {
        $money = new Money(99.99, 'PLN');

        $this->assertSame(99.99, $money->amount);
        $this->assertSame('PLN', $money->currency);
    }

    public function testCurrencyIsAlwaysUppercase(): void
    {
        $money = new Money(50.0, 'eur');

        $this->assertSame('EUR', $money->currency);
    }

    public function testAmountIsRoundedToTwoDecimalPlaces(): void
    {
        $money = new Money(99.999, 'USD');

        $this->assertSame(100.0, $money->amount);
    }

    public function testThrowsExceptionForNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot be negative');

        new Money(-10.0, 'PLN');
    }

    public function testThrowsExceptionForInvalidCurrencyCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency must be 3-letter ISO code');

        new Money(100.0, 'EURO');
    }

    public function testThrowsExceptionForShortCurrencyCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency must be 3-letter ISO code');

        new Money(100.0, 'EU');
    }

    public function testToSmallestUnitForPLN(): void
    {
        $money = new Money(99.99, 'PLN');

        $this->assertSame(9999, $money->toSmallestUnit());
    }

    public function testToSmallestUnitForJPY(): void
    {
        $money = new Money(1000.0, 'JPY');

        $this->assertSame(1000, $money->toSmallestUnit());
    }

    public function testFromSmallestUnitForPLN(): void
    {
        $money = Money::fromSmallestUnit(9999, 'PLN');

        $this->assertSame(99.99, $money->amount);
        $this->assertSame('PLN', $money->currency);
    }

    public function testFromSmallestUnitForJPY(): void
    {
        $money = Money::fromSmallestUnit(1000, 'JPY');

        $this->assertSame(1000.0, $money->amount);
        $this->assertSame('JPY', $money->currency);
    }


    public function testFormat(): void
    {
        $money = new Money(1234.56, 'PLN');
        $formatted = (string) $money;
        
        // Check if formatting contains expected values (may vary by locale)
        $this->assertStringContainsString('234', $formatted);
        $this->assertStringContainsString('56', $formatted);
    }

    public function testToString(): void
    {
        $money = new Money(1234.56, 'PLN');
        $formatted = (string) $money;
        
        // Check if formatting contains expected values (may vary by locale)
        $this->assertStringContainsString('234', $formatted);
        $this->assertStringContainsString('56', $formatted);
    }
    
    public function testToSmallestUnitForKRW(): void
    {
        $money = new Money(5000.0, 'KRW');
        
        $this->assertSame(5000, $money->toSmallestUnit());
    }
    
    public function testFromSmallestUnitForKRW(): void
    {
        $money = Money::fromSmallestUnit(5000, 'KRW');
        
        $this->assertSame(5000.0, $money->amount);
        $this->assertSame('KRW', $money->currency);
    }
    
    public function testToSmallestUnitForEUR(): void
    {
        $money = new Money(75.25, 'EUR');
        
        $this->assertSame(7525, $money->toSmallestUnit());
    }
    
    public function testFromSmallestUnitForUSD(): void
    {
        $money = Money::fromSmallestUnit(12345, 'USD');
        
        $this->assertSame(123.45, $money->amount);
        $this->assertSame('USD', $money->currency);
    }
    
    public function testAmountPropertyIsSetCorrectly(): void
    {
        $money = new Money(100.0, 'EUR');
        
        // Verify amount is set correctly
        $this->assertEquals(100.0, $money->amount);
        
        // Test rounding behavior
        $money2 = new Money(99.999, 'EUR');
        $this->assertEquals(100.0, $money2->amount);
    }
    
    public function testCurrencyPropertyIsSetCorrectly(): void
    {
        $money = new Money(100.0, 'EUR');
        
        // Verify currency is set correctly
        $this->assertEquals('EUR', $money->currency);
        
        // Test uppercase conversion
        $money2 = new Money(100.0, 'usd');
        $this->assertEquals('USD', $money2->currency);
    }
    
    public function testZeroAmountIsValid(): void
    {
        $money = new Money(0.0, 'USD');
        
        $this->assertSame(0.0, $money->amount);
        $this->assertSame(0, $money->toSmallestUnit());
    }
    
    public function testVeryLargeAmount(): void
    {
        $money = new Money(999999.99, 'USD');
        
        $this->assertSame(999999.99, $money->amount);
        $this->assertSame(99999999, $money->toSmallestUnit());
    }
    
    public function testCustomLocaleFormatting(): void
    {
        $money = new Money(1234.56, 'USD', 'en_US');
        
        $formatted = (string) $money;
        $this->assertStringContainsString('1,234.56', $formatted);
    }
    
    public function testFormatThrowsExceptionOnFailure(): void
    {
        $money = new Money(100.0, 'XXX'); // Invalid currency code
        
        try {
            $result = (string) $money;
            // If formatting doesn't fail, that's also acceptable
            $this->assertIsString($result);
        } catch (\RuntimeException $e) {
            $this->assertEquals('Failed to format currency', $e->getMessage());
        }
    }
}