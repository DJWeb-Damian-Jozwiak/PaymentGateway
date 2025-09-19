<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\DTOs;

use DjWeb\Payments\DTOs\DiscountData;
use PHPUnit\Framework\TestCase;

final class DiscountDataTest extends TestCase
{
    public function testCreateValidDiscount(): void
    {
        $discount = new DiscountData(
            code: 'SAVE10',
            percentage: 10
        );

        $this->assertEquals('SAVE10', $discount->code);
        $this->assertEquals(10, $discount->percentage);
        $this->assertNull($discount->maxUsages);
        $this->assertEquals(0, $discount->currentUsages);
        $this->assertNull($discount->validUntil);
        $this->assertTrue($discount->isValid);
    }

    public function testCreateDiscountWithMaxUsages(): void
    {
        $discount = new DiscountData(
            code: 'LIMITED5',
            percentage: 20,
            maxUsages: 5,
            currentUsages: 3
        );

        $this->assertEquals('LIMITED5', $discount->code);
        $this->assertEquals(20, $discount->percentage);
        $this->assertEquals(5, $discount->maxUsages);
        $this->assertEquals(3, $discount->currentUsages);
        $this->assertTrue($discount->isValid);
    }

    public function testDiscountInvalidWhenMaxUsagesReached(): void
    {
        $discount = new DiscountData(
            code: 'USED',
            percentage: 15,
            maxUsages: 5,
            currentUsages: 5
        );

        $this->assertFalse($discount->isValid);
    }

    public function testDiscountInvalidWhenMaxUsagesExceeded(): void
    {
        $discount = new DiscountData(
            code: 'OVERUSED',
            percentage: 15,
            maxUsages: 5,
            currentUsages: 10
        );

        $this->assertFalse($discount->isValid);
    }

    public function testDiscountValidBeforeExpiryDate(): void
    {
        $futureDate = new \DateTimeImmutable('+1 day');
        $discount = new DiscountData(
            code: 'FUTURE',
            percentage: 25,
            validUntil: $futureDate
        );

        $this->assertTrue($discount->isValid);
    }

    public function testDiscountInvalidAfterExpiryDate(): void
    {
        $pastDate = new \DateTimeImmutable('-1 day');
        $discount = new DiscountData(
            code: 'EXPIRED',
            percentage: 25,
            validUntil: $pastDate
        );

        $this->assertFalse($discount->isValid);
    }

    public function testValidPercentageValues(): void
    {
        $discount1 = new DiscountData(
            code: 'TEST50',
            percentage: 50
        );
        $this->assertEquals(50, $discount1->percentage);
        
        $discount2 = new DiscountData(
            code: 'TEST0',
            percentage: 0
        );
        $this->assertEquals(0, $discount2->percentage);
        
        $discount3 = new DiscountData(
            code: 'TEST100',
            percentage: 100
        );
        $this->assertEquals(100, $discount3->percentage);
    }

    public function testThrowsExceptionForNegativePercentage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount percentage must be between 0 and 100');

        new DiscountData(
            code: 'INVALID',
            percentage: -1
        );
    }

    public function testThrowsExceptionForPercentageOver100(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount percentage must be between 0 and 100');

        new DiscountData(
            code: 'INVALID',
            percentage: 101
        );
    }

    public function testPercentagePropertyIsSetCorrectly(): void
    {
        $discount = new DiscountData(
            code: 'TEST',
            percentage: 50
        );

        // Verify that percentage property is properly set
        $this->assertEquals(50, $discount->percentage);
        
        // Test that percentage cannot be set to invalid values at construction
        $this->expectException(\InvalidArgumentException::class);
        new DiscountData('INVALID', 150);
    }

    public function testCalculateDiscountAmountForValidDiscount(): void
    {
        $discount = new DiscountData(
            code: 'SAVE20',
            percentage: 20
        );

        $this->assertEquals(20.00, $discount->calculateDiscountAmount(100.00));
        $this->assertEquals(10.00, $discount->calculateDiscountAmount(50.00));
        $this->assertEquals(0.50, $discount->calculateDiscountAmount(2.50));
    }

    public function testCalculateDiscountAmountForInvalidDiscount(): void
    {
        $discount = new DiscountData(
            code: 'EXPIRED',
            percentage: 20,
            validUntil: new \DateTimeImmutable('-1 day')
        );

        $this->assertEquals(0, $discount->calculateDiscountAmount(100.00));
    }

    public function testCalculateFinalAmountForValidDiscount(): void
    {
        $discount = new DiscountData(
            code: 'SAVE10',
            percentage: 10
        );

        $this->assertEquals(90.00, $discount->calculateFinalAmount(100.00));
        $this->assertEquals(45.00, $discount->calculateFinalAmount(50.00));
        $this->assertEquals(9.00, $discount->calculateFinalAmount(10.00));
    }

    public function testCalculateFinalAmountForInvalidDiscount(): void
    {
        $discount = new DiscountData(
            code: 'USED',
            percentage: 25,
            maxUsages: 1,
            currentUsages: 1
        );

        $this->assertEquals(100.00, $discount->calculateFinalAmount(100.00));
    }

    public function testToArrayMethod(): void
    {
        $validUntil = new \DateTimeImmutable('2025-12-31');
        $discount = new DiscountData(
            code: 'TEST',
            percentage: 15,
            maxUsages: 10,
            currentUsages: 2,
            validUntil: $validUntil
        );

        $array = $discount->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('percentage', $array);
        $this->assertArrayHasKey('maxUsages', $array);
        $this->assertArrayHasKey('currentUsages', $array);
        $this->assertArrayHasKey('validUntil', $array);
        $this->assertEquals('TEST', $array['code']);
        $this->assertEquals(15, $array['percentage']);
        $this->assertEquals(10, $array['maxUsages']);
        $this->assertEquals(2, $array['currentUsages']);
    }
}