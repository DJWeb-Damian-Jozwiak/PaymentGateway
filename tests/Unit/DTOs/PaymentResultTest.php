<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\DTOs;

use DjWeb\Payments\DTOs\PaymentResult;
use DjWeb\Payments\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class PaymentResultTest extends TestCase
{
    public function testCreateSuccessfulPaymentResult(): void
    {
        $amount = new Money(150.00, 'EUR');
        $processedAt = new \DateTimeImmutable('2025-01-01 15:30:00');
        
        $result = new PaymentResult(
            success: true,
            transactionId: 'txn_123456789',
            status: 'completed',
            amount: $amount,
            metadata: ['order_id' => 'ORD-001', 'customer_id' => 'CUST-123'],
            errorMessage: null,
            processedAt: $processedAt
        );

        $this->assertTrue($result->success);
        $this->assertEquals('txn_123456789', $result->transactionId);
        $this->assertEquals('completed', $result->status);
        $this->assertSame($amount, $result->amount);
        $this->assertEquals(['order_id' => 'ORD-001', 'customer_id' => 'CUST-123'], $result->metadata);
        $this->assertNull($result->errorMessage);
        $this->assertSame($processedAt, $result->processedAt);
        $this->assertFalse($result->hasError);
    }

    public function testCreateFailedPaymentResult(): void
    {
        $amount = new Money(50.00, 'USD');
        
        $result = new PaymentResult(
            success: false,
            transactionId: 'txn_failed_123',
            status: 'failed',
            amount: $amount,
            metadata: [],
            errorMessage: 'Insufficient funds'
        );

        $this->assertFalse($result->success);
        $this->assertEquals('txn_failed_123', $result->transactionId);
        $this->assertEquals('failed', $result->status);
        $this->assertSame($amount, $result->amount);
        $this->assertEmpty($result->metadata);
        $this->assertEquals('Insufficient funds', $result->errorMessage);
        $this->assertNull($result->processedAt);
        $this->assertTrue($result->hasError);
    }

    public function testHasErrorProperty(): void
    {
        $amount = new Money(100.00, 'PLN');
        
        $resultWithError = new PaymentResult(
            success: false,
            transactionId: 'txn_001',
            status: 'rejected',
            amount: $amount,
            errorMessage: 'Card declined'
        );
        $this->assertTrue($resultWithError->hasError);

        $resultWithoutError = new PaymentResult(
            success: true,
            transactionId: 'txn_002',
            status: 'approved',
            amount: $amount
        );
        $this->assertFalse($resultWithoutError->hasError);

        $failedResultWithoutMessage = new PaymentResult(
            success: false,
            transactionId: 'txn_003',
            status: 'failed',
            amount: $amount,
            errorMessage: null
        );
        $this->assertFalse($failedResultWithoutMessage->hasError);
    }

    public function testCreatePendingPaymentResult(): void
    {
        $amount = new Money(75.50, 'GBP');
        $processedAt = new \DateTimeImmutable();
        
        $result = new PaymentResult(
            success: true,
            transactionId: 'txn_pending_456',
            status: 'pending',
            amount: $amount,
            metadata: ['retry_count' => 0, 'processing_queue' => 'high'],
            processedAt: $processedAt
        );

        $this->assertTrue($result->success);
        $this->assertEquals('txn_pending_456', $result->transactionId);
        $this->assertEquals('pending', $result->status);
        $this->assertEquals(75.50, $result->amount->amount);
        $this->assertEquals('GBP', $result->amount->currency);
        $this->assertEquals(0, $result->metadata['retry_count']);
        $this->assertEquals('high', $result->metadata['processing_queue']);
        $this->assertFalse($result->hasError);
    }

    public function testCreateMinimalPaymentResult(): void
    {
        $amount = new Money(10.00, 'EUR');
        
        $result = new PaymentResult(
            success: true,
            transactionId: 'txn_minimal',
            status: 'completed',
            amount: $amount
        );

        $this->assertTrue($result->success);
        $this->assertEquals('txn_minimal', $result->transactionId);
        $this->assertEquals('completed', $result->status);
        $this->assertSame($amount, $result->amount);
        $this->assertEmpty($result->metadata);
        $this->assertNull($result->errorMessage);
        $this->assertNull($result->processedAt);
        $this->assertFalse($result->hasError);
    }

    public function testPaymentResultWithComplexMetadata(): void
    {
        $amount = new Money(999.99, 'USD');
        $metadata = [
            'gateway' => 'stripe',
            'attempts' => 3,
            'fees' => 29.99,
            'customer' => [
                'id' => 'cust_789',
                'email' => 'customer@example.com',
                'country' => 'US'
            ],
            'flags' => ['express_checkout', '3d_secure', 'recurring']
        ];
        
        $result = new PaymentResult(
            success: true,
            transactionId: 'txn_complex',
            status: 'authorized',
            amount: $amount,
            metadata: $metadata
        );

        $this->assertEquals($metadata, $result->metadata);
        $this->assertEquals('stripe', $result->metadata['gateway']);
        $this->assertEquals(3, $result->metadata['attempts']);
        $this->assertEquals(29.99, $result->metadata['fees']);
        $this->assertIsArray($result->metadata['customer']);
        $this->assertEquals('cust_789', $result->metadata['customer']['id']);
        $this->assertIsArray($result->metadata['flags']);
        $this->assertContains('3d_secure', $result->metadata['flags']);
    }

    public function testToArrayMethod(): void
    {
        $amount = new Money(250.00, 'CHF');
        $processedAt = new \DateTimeImmutable('2025-01-10 09:15:00');
        
        $result = new PaymentResult(
            success: true,
            transactionId: 'txn_array_test',
            status: 'captured',
            amount: $amount,
            metadata: ['source' => 'api', 'version' => '2.0'],
            errorMessage: null,
            processedAt: $processedAt
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('transactionId', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('amount', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('errorMessage', $array);
        $this->assertArrayHasKey('processedAt', $array);
        $this->assertTrue($array['success']);
        $this->assertEquals('txn_array_test', $array['transactionId']);
        $this->assertEquals('captured', $array['status']);
        $this->assertEquals(['source' => 'api', 'version' => '2.0'], $array['metadata']);
        $this->assertNull($array['errorMessage']);
    }

    public function testReadOnlyProperties(): void
    {
        $amount = new Money(100.00, 'EUR');
        $result = new PaymentResult(
            success: true,
            transactionId: 'txn_readonly',
            status: 'completed',
            amount: $amount
        );

        $this->assertTrue($result->success);
        $this->assertEquals('txn_readonly', $result->transactionId);
        $this->assertEquals('completed', $result->status);
        $this->assertSame($amount, $result->amount);
    }
}