<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\DTOs;

use DjWeb\Payments\DTOs\WebhookEvent;
use PHPUnit\Framework\TestCase;

final class WebhookEventTest extends TestCase
{
    public function testCreateWebhookEvent(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-01 10:00:00');
        $data = [
            'object' => (object)['id' => 'pi_123', 'amount' => 1000],
            'status' => 'succeeded'
        ];
        
        $event = new WebhookEvent(
            id: 'evt_123456',
            type: 'payment_intent.succeeded',
            data: $data,
            source: 'stripe',
            createdAt: $createdAt,
            metadata: ['webhook_version' => '2.0']
        );

        $this->assertEquals('evt_123456', $event->id);
        $this->assertEquals('payment_intent.succeeded', $event->type);
        $this->assertEquals($data, $event->data);
        $this->assertEquals('stripe', $event->source);
        $this->assertSame($createdAt, $event->createdAt);
        $this->assertEquals(['webhook_version' => '2.0'], $event->metadata);
    }

    public function testIsPaymentEventProperty(): void
    {
        $paymentEvent = new WebhookEvent(
            id: 'evt_1',
            type: 'payment_intent.succeeded',
            data: [],
            source: 'stripe'
        );
        $this->assertTrue($paymentEvent->isPaymentEvent);
        $this->assertFalse($paymentEvent->isInvoiceEvent);

        $paymentCreatedEvent = new WebhookEvent(
            id: 'evt_2',
            type: 'payment_intent.created',
            data: [],
            source: 'stripe'
        );
        $this->assertTrue($paymentCreatedEvent->isPaymentEvent);

        $paymentFailedEvent = new WebhookEvent(
            id: 'evt_3',
            type: 'payment_intent.payment_failed',
            data: [],
            source: 'stripe'
        );
        $this->assertTrue($paymentFailedEvent->isPaymentEvent);

        $nonPaymentEvent = new WebhookEvent(
            id: 'evt_4',
            type: 'customer.created',
            data: [],
            source: 'stripe'
        );
        $this->assertFalse($nonPaymentEvent->isPaymentEvent);
    }

    public function testIsInvoiceEventProperty(): void
    {
        $invoiceEvent = new WebhookEvent(
            id: 'evt_1',
            type: 'invoice.payment_succeeded',
            data: [],
            source: 'stripe'
        );
        $this->assertTrue($invoiceEvent->isInvoiceEvent);
        $this->assertFalse($invoiceEvent->isPaymentEvent);

        $invoiceCreatedEvent = new WebhookEvent(
            id: 'evt_2',
            type: 'invoice.created',
            data: [],
            source: 'stripe'
        );
        $this->assertTrue($invoiceCreatedEvent->isInvoiceEvent);

        $invoiceVoidedEvent = new WebhookEvent(
            id: 'evt_3',
            type: 'invoice.voided',
            data: [],
            source: 'stripe'
        );
        $this->assertTrue($invoiceVoidedEvent->isInvoiceEvent);

        $nonInvoiceEvent = new WebhookEvent(
            id: 'evt_4',
            type: 'subscription.created',
            data: [],
            source: 'stripe'
        );
        $this->assertFalse($nonInvoiceEvent->isInvoiceEvent);
    }

    public function testEventObjectProperty(): void
    {
        $paymentObject = (object)[
            'id' => 'pi_123',
            'amount' => 2500,
            'currency' => 'usd',
            'status' => 'succeeded'
        ];
        
        $eventWithObject = new WebhookEvent(
            id: 'evt_with_object',
            type: 'payment_intent.succeeded',
            data: ['object' => $paymentObject, 'previous_attributes' => []],
            source: 'stripe'
        );
        
        $this->assertNotNull($eventWithObject->eventObject);
        $this->assertEquals($paymentObject, $eventWithObject->eventObject);
        $this->assertEquals('pi_123', $eventWithObject->eventObject->id);
        $this->assertEquals(2500, $eventWithObject->eventObject->amount);

        $eventWithoutObject = new WebhookEvent(
            id: 'evt_without_object',
            type: 'test.event',
            data: ['status' => 'active'],
            source: 'custom'
        );
        
        $this->assertNull($eventWithoutObject->eventObject);
    }

    public function testCreateMinimalWebhookEvent(): void
    {
        $event = new WebhookEvent(
            id: 'evt_minimal',
            type: 'test.event',
            data: [],
            source: 'test'
        );

        $this->assertEquals('evt_minimal', $event->id);
        $this->assertEquals('test.event', $event->type);
        $this->assertEmpty($event->data);
        $this->assertEquals('test', $event->source);
        $this->assertNull($event->createdAt);
        $this->assertEmpty($event->metadata);
    }

    public function testComplexDataStructure(): void
    {
        $complexData = [
            'object' => (object)[
                'id' => 'cus_123',
                'email' => 'customer@example.com',
                'subscriptions' => [
                    ['id' => 'sub_1', 'status' => 'active'],
                    ['id' => 'sub_2', 'status' => 'canceled']
                ]
            ],
            'previous_attributes' => [
                'email' => 'old@example.com'
            ],
            'nested' => [
                'level1' => [
                    'level2' => [
                        'value' => 'deep'
                    ]
                ]
            ]
        ];

        $event = new WebhookEvent(
            id: 'evt_complex',
            type: 'customer.updated',
            data: $complexData,
            source: 'stripe'
        );

        $this->assertEquals($complexData, $event->data);
        $this->assertNotNull($event->eventObject);
        $this->assertEquals('cus_123', $event->eventObject->id);
        $this->assertIsArray($event->data['nested']);
        $this->assertEquals('deep', $event->data['nested']['level1']['level2']['value']);
    }

    public function testToArrayMethod(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-15 14:30:00');
        $event = new WebhookEvent(
            id: 'evt_array_test',
            type: 'payment_intent.succeeded',
            data: ['amount' => 1000, 'currency' => 'eur'],
            source: 'stripe',
            createdAt: $createdAt,
            metadata: ['environment' => 'production']
        );

        $array = $event->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('source', $array);
        $this->assertArrayHasKey('createdAt', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertEquals('evt_array_test', $array['id']);
        $this->assertEquals('payment_intent.succeeded', $array['type']);
        $this->assertEquals(['amount' => 1000, 'currency' => 'eur'], $array['data']);
        $this->assertEquals('stripe', $array['source']);
        $this->assertEquals('2025-01-15 14:30:00', $array['createdAt']);
        $this->assertEquals(['environment' => 'production'], $array['metadata']);
    }

    public function testToArrayMethodWithNullCreatedAt(): void
    {
        $event = new WebhookEvent(
            id: 'evt_null_date',
            type: 'test.event',
            data: [],
            source: 'test'
        );

        $array = $event->toArray();
        
        $this->assertArrayHasKey('createdAt', $array);
        $this->assertNull($array['createdAt']);
    }

    public function testDifferentEventTypes(): void
    {
        $eventTypes = [
            'payment_intent.succeeded' => ['isPayment' => true, 'isInvoice' => false],
            'payment_intent.canceled' => ['isPayment' => true, 'isInvoice' => false],
            'invoice.created' => ['isPayment' => false, 'isInvoice' => true],
            'invoice.finalized' => ['isPayment' => false, 'isInvoice' => true],
            'customer.subscription.created' => ['isPayment' => false, 'isInvoice' => false],
            'charge.succeeded' => ['isPayment' => false, 'isInvoice' => false],
        ];

        foreach ($eventTypes as $type => $expectations) {
            $event = new WebhookEvent(
                id: 'evt_' . str_replace('.', '_', $type),
                type: $type,
                data: [],
                source: 'stripe'
            );

            $this->assertEquals(
                $expectations['isPayment'], 
                $event->isPaymentEvent,
                "Failed for type: $type - expected isPaymentEvent to be " . ($expectations['isPayment'] ? 'true' : 'false')
            );
            $this->assertEquals(
                $expectations['isInvoice'], 
                $event->isInvoiceEvent,
                "Failed for type: $type - expected isInvoiceEvent to be " . ($expectations['isInvoice'] ? 'true' : 'false')
            );
        }
    }
}