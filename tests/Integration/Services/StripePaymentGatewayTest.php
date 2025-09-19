<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Integration\Services;

use DjWeb\Payments\Services\Payment\Stripe\StripePaymentGateway;
use DjWeb\Payments\DTOs\PaymentRequest;
use DjWeb\Payments\DTOs\CustomerData;
use DjWeb\Payments\DTOs\AddressData;
use DjWeb\Payments\ValueObjects\Money;
use DjWeb\Payments\Exceptions\PaymentError;
use PHPUnit\Framework\TestCase;
use Mockery;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\SignatureVerificationException;

final class StripePaymentGatewayTest extends TestCase
{
    private StripePaymentGateway $gateway;

    protected function setUp(): void
    {
        // Use test API key
        $this->gateway = new StripePaymentGateway(
            secretKey: 'sk_test_fake_key_for_testing',
            webhookSecret: 'whsec_test_fake_secret'
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testCanInitializeGateway(): void
    {
        $this->assertInstanceOf(StripePaymentGateway::class, $this->gateway);
    }

    public function testCreatePaymentRequestPreparesCorrectMetadata(): void
    {
        $customer = new CustomerData(
            email: 'test@example.com',
            firstName: 'John',
            lastName: 'Doe',
            address: new AddressData('123 Main St', 'Warsaw', '00-001', 'PL')
        );

        $request = new PaymentRequest(
            amount: new Money(99.99, 'PLN'),
            customer: $customer,
            description: 'Test payment'
        );

        $array = $request->toArray();

        $this->assertSame(9999, $array['amount']); // PLN in grosze
        $this->assertSame('PLN', $array['currency']);
        $this->assertSame('Test payment', $array['description']);
        $this->assertSame('test@example.com', $array['metadata']['customer_email']);
    }

    public function testWebhookSignatureValidationWithInvalidSignature(): void
    {
        $payload = '{"id": "evt_test"}';
        $invalidSignature = 'invalid_signature';

        $this->expectException(SignatureVerificationException::class);
        $this->gateway->verifyWebhookSignature($payload, $invalidSignature);
    }

    public function testCreatePaymentIntentThrowsExceptionWithInvalidApiKey(): void
    {
        $customer = new CustomerData(
            email: 'test@example.com',
            firstName: 'John',
            lastName: 'Doe',
            address: new AddressData('123 Main St', 'Warsaw', '00-001', 'PL')
        );

        $request = new PaymentRequest(
            amount: new Money(99.99, 'PLN'),
            customer: $customer,
            description: 'Test payment'
        );

        // This will fail with fake API key
        $this->expectException(AuthenticationException::class);

        $this->gateway->createPaymentIntent($request);
    }

    public function testGetPaymentStatusThrowsExceptionWithInvalidPaymentId(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->gateway->getPaymentStatus('invalid_payment_id');
    }

    public function testRefundPaymentThrowsExceptionWithInvalidPaymentId(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->gateway->refundPayment('invalid_payment_id');
    }

    /**
     * Test that would work with real Stripe API (commented out for CI)
     */
    /*
    public function testCreatePaymentIntentWithRealStripeAccount(): void
    {
        // This test would require real Stripe test credentials
        $realGateway = new StripePaymentGateway(
            secretKey: $_ENV['STRIPE_TEST_SECRET_KEY'] ?? 'sk_test_...',
            webhookSecret: $_ENV['STRIPE_TEST_WEBHOOK_SECRET'] ?? 'whsec_...'
        );

        $customer = new CustomerData(
            email: 'test@example.com',
            firstName: 'John',
            lastName: 'Doe',
            address: new AddressData('123 Main St', 'Warsaw', '00-001', 'PL')
        );

        $request = new PaymentRequest(
            amount: new Money(99.99, 'PLN'),
            customer: $customer,
            description: 'Test payment'
        );

        $intent = $realGateway->createPaymentIntent($request);

        $this->assertNotEmpty($intent->id);
        $this->assertNotEmpty($intent->clientSecret);
        $this->assertEquals(new Money(99.99, 'PLN'), $intent->amount);
    }
    */
}
