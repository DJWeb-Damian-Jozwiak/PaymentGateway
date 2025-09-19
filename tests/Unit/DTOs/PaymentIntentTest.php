<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\DTOs;

use DjWeb\Payments\DTOs\PaymentIntent;
use DjWeb\Payments\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class PaymentIntentTest extends TestCase
{
    public function testCreatePaymentIntent(): void
    {
        $amount = new Money(100.00, 'EUR');
        $createdAt = new \DateTimeImmutable('2025-01-01 10:00:00');
        
        $intent = new PaymentIntent(
            id: 'pi_123456',
            clientSecret: 'pi_123456_secret_xyz',
            amount: $amount,
            status: 'succeeded',
            metadata: ['order_id' => '12345'],
            createdAt: $createdAt
        );

        $this->assertEquals('pi_123456', $intent->id);
        $this->assertEquals('pi_123456_secret_xyz', $intent->clientSecret);
        $this->assertSame($amount, $intent->amount);
        $this->assertEquals('succeeded', $intent->status);
        $this->assertEquals(['order_id' => '12345'], $intent->metadata);
        $this->assertSame($createdAt, $intent->createdAt);
    }

    public function testIsSucceededProperty(): void
    {
        $amount = new Money(100.00, 'USD');
        
        $succeededIntent = new PaymentIntent(
            id: 'pi_1',
            clientSecret: 'secret_1',
            amount: $amount,
            status: 'succeeded'
        );
        $this->assertTrue($succeededIntent->isSucceeded);
        $this->assertFalse($succeededIntent->isPending);
        $this->assertFalse($succeededIntent->isFailed);

        $processingIntent = new PaymentIntent(
            id: 'pi_2',
            clientSecret: 'secret_2',
            amount: $amount,
            status: 'processing'
        );
        $this->assertFalse($processingIntent->isSucceeded);
    }

    public function testIsPendingProperty(): void
    {
        $amount = new Money(100.00, 'PLN');
        
        $processingIntent = new PaymentIntent(
            id: 'pi_1',
            clientSecret: 'secret_1',
            amount: $amount,
            status: 'processing'
        );
        $this->assertTrue($processingIntent->isPending);
        $this->assertFalse($processingIntent->isSucceeded);
        $this->assertFalse($processingIntent->isFailed);

        $requiresActionIntent = new PaymentIntent(
            id: 'pi_2',
            clientSecret: 'secret_2',
            amount: $amount,
            status: 'requires_action'
        );
        $this->assertTrue($requiresActionIntent->isPending);

        $requiresPaymentIntent = new PaymentIntent(
            id: 'pi_3',
            clientSecret: 'secret_3',
            amount: $amount,
            status: 'requires_payment_method'
        );
        $this->assertTrue($requiresPaymentIntent->isPending);
    }

    public function testIsFailedProperty(): void
    {
        $amount = new Money(50.00, 'GBP');
        
        $canceledIntent = new PaymentIntent(
            id: 'pi_1',
            clientSecret: 'secret_1',
            amount: $amount,
            status: 'canceled'
        );
        $this->assertTrue($canceledIntent->isFailed);
        $this->assertFalse($canceledIntent->isSucceeded);
        $this->assertFalse($canceledIntent->isPending);

        $failedIntent = new PaymentIntent(
            id: 'pi_2',
            clientSecret: 'secret_2',
            amount: $amount,
            status: 'payment_failed'
        );
        $this->assertTrue($failedIntent->isFailed);
    }

    public function testStatusCanBeModified(): void
    {
        $amount = new Money(100.00, 'EUR');
        $intent = new PaymentIntent(
            id: 'pi_123',
            clientSecret: 'secret_123',
            amount: $amount,
            status: 'processing'
        );

        $this->assertEquals('processing', $intent->status);
        $this->assertTrue($intent->isPending);
        $this->assertFalse($intent->isSucceeded);

        $intent->status = 'succeeded';
        
        $this->assertEquals('succeeded', $intent->status);
        $this->assertTrue($intent->isSucceeded);
        $this->assertFalse($intent->isPending);
    }

    public function testMetadataCanBeModified(): void
    {
        $amount = new Money(75.00, 'USD');
        $intent = new PaymentIntent(
            id: 'pi_456',
            clientSecret: 'secret_456',
            amount: $amount,
            status: 'succeeded',
            metadata: ['initial' => 'value']
        );

        $this->assertEquals(['initial' => 'value'], $intent->metadata);

        $intent->metadata['new_key'] = 'new_value';
        $intent->metadata['initial'] = 'modified';

        $this->assertEquals('new_value', $intent->metadata['new_key']);
        $this->assertEquals('modified', $intent->metadata['initial']);
    }

    public function testCreateMinimalPaymentIntent(): void
    {
        $amount = new Money(25.50, 'EUR');
        $intent = new PaymentIntent(
            id: 'pi_minimal',
            clientSecret: 'secret_minimal',
            amount: $amount,
            status: 'requires_payment_method'
        );

        $this->assertEquals('pi_minimal', $intent->id);
        $this->assertEquals('secret_minimal', $intent->clientSecret);
        $this->assertSame($amount, $intent->amount);
        $this->assertEquals('requires_payment_method', $intent->status);
        $this->assertEmpty($intent->metadata);
        $this->assertNull($intent->createdAt);
    }

    public function testUnknownStatusBehavior(): void
    {
        $amount = new Money(100.00, 'USD');
        $intent = new PaymentIntent(
            id: 'pi_unknown',
            clientSecret: 'secret_unknown',
            amount: $amount,
            status: 'custom_unknown_status'
        );

        $this->assertFalse($intent->isSucceeded);
        $this->assertFalse($intent->isPending);
        $this->assertFalse($intent->isFailed);
    }

    public function testToArrayMethod(): void
    {
        $amount = new Money(99.99, 'CHF');
        $createdAt = new \DateTimeImmutable('2025-01-15 14:30:00');
        $intent = new PaymentIntent(
            id: 'pi_array_test',
            clientSecret: 'secret_array_test',
            amount: $amount,
            status: 'succeeded',
            metadata: ['customer' => 'cust_123', 'invoice' => 'inv_456'],
            createdAt: $createdAt
        );

        $array = $intent->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('clientSecret', $array);
        $this->assertArrayHasKey('amount', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('createdAt', $array);
        $this->assertEquals('pi_array_test', $array['id']);
        $this->assertEquals('secret_array_test', $array['clientSecret']);
        $this->assertEquals('succeeded', $array['status']);
        $this->assertEquals(['customer' => 'cust_123', 'invoice' => 'inv_456'], $array['metadata']);
    }
}