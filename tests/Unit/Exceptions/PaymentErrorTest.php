<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\Exceptions;

use DjWeb\Payments\Exceptions\PaymentError;
use PHPUnit\Framework\TestCase;

final class PaymentErrorTest extends TestCase
{
    public function testCreateBasicPaymentError(): void
    {
        $error = new PaymentError('Payment failed');
        
        $this->assertInstanceOf(\Exception::class, $error);
        $this->assertEquals('Payment failed', $error->getMessage());
        $this->assertEquals(0, $error->getCode());
        $this->assertNull($error->getPrevious());
        $this->assertNull($error->context);
    }

    public function testCreatePaymentErrorWithAllParameters(): void
    {
        $previousException = new \RuntimeException('Network error');
        $context = ['transaction_id' => 'txn_123', 'amount' => 100.50];
        
        $error = new PaymentError(
            message: 'Payment processing failed',
            code: 502,
            previous: $previousException,
            context: $context
        );
        
        $this->assertEquals('Payment processing failed', $error->getMessage());
        $this->assertEquals(502, $error->getCode());
        $this->assertSame($previousException, $error->getPrevious());
        $this->assertEquals($context, $error->context);
    }

    public function testInvalidAmountStaticConstructor(): void
    {
        $error = PaymentError::invalidAmount(-10.50);
        
        $this->assertInstanceOf(PaymentError::class, $error);
        $this->assertEquals('Invalid payment amount: -10.5', $error->getMessage());
        $this->assertEquals(0, $error->getCode());
        $this->assertNull($error->getPrevious());
        $this->assertNull($error->context);
    }

    public function testInvalidAmountWithDifferentValues(): void
    {
        $amounts = [0, -1, -99.99, -0.01, 0.001];
        
        foreach ($amounts as $amount) {
            $error = PaymentError::invalidAmount($amount);
            $this->assertEquals("Invalid payment amount: {$amount}", $error->getMessage());
        }
    }

    public function testUnsupportedCurrencyStaticConstructor(): void
    {
        $error = PaymentError::unsupportedCurrency('XYZ');
        
        $this->assertInstanceOf(PaymentError::class, $error);
        $this->assertEquals('Unsupported currency: XYZ', $error->getMessage());
        $this->assertEquals(0, $error->getCode());
        $this->assertNull($error->getPrevious());
        $this->assertNull($error->context);
    }

    public function testUnsupportedCurrencyWithDifferentCodes(): void
    {
        $currencies = ['ABC', 'XXX', 'TEST', '123', 'INVALID'];
        
        foreach ($currencies as $currency) {
            $error = PaymentError::unsupportedCurrency($currency);
            $this->assertEquals("Unsupported currency: {$currency}", $error->getMessage());
        }
    }

    public function testGatewayErrorStaticConstructor(): void
    {
        $error = PaymentError::gatewayError('Stripe', 'Card declined');
        
        $this->assertInstanceOf(PaymentError::class, $error);
        $this->assertEquals('Payment gateway error (Stripe): Card declined', $error->getMessage());
        $this->assertEquals(0, $error->getCode());
        $this->assertNull($error->getPrevious());
        $this->assertNull($error->context);
    }

    public function testGatewayErrorWithDifferentGatewaysAndErrors(): void
    {
        $cases = [
            ['gateway' => 'Stripe', 'error' => 'Insufficient funds'],
            ['gateway' => 'PayPal', 'error' => 'Account suspended'],
            ['gateway' => 'Square', 'error' => 'Invalid card number'],
            ['gateway' => 'Braintree', 'error' => 'Processor declined'],
            ['gateway' => 'Adyen', 'error' => 'Authentication required'],
        ];
        
        foreach ($cases as $case) {
            $error = PaymentError::gatewayError($case['gateway'], $case['error']);
            $expected = "Payment gateway error ({$case['gateway']}): {$case['error']}";
            $this->assertEquals($expected, $error->getMessage());
        }
    }

    public function testContextIsReadOnly(): void
    {
        $context = ['gateway' => 'Stripe', 'mode' => 'test'];
        $error = new PaymentError('Test error', 0, null, $context);
        
        $this->assertEquals($context, $error->context);
    }

    public function testExceptionChaining(): void
    {
        $networkError = new \Exception('Connection timeout');
        $apiError = new PaymentError('API request failed', 503, $networkError);
        $gatewayError = PaymentError::gatewayError('PayPal', 'Service unavailable');
        
        $finalError = new PaymentError(
            message: $gatewayError->getMessage(),
            code: 503,
            previous: $apiError,
            context: ['retry_attempts' => 3, 'last_attempt' => '2025-01-01T10:00:00Z']
        );
        
        $this->assertEquals('Payment gateway error (PayPal): Service unavailable', $finalError->getMessage());
        $this->assertEquals(503, $finalError->getCode());
        $this->assertSame($apiError, $finalError->getPrevious());
        $this->assertSame($networkError, $finalError->getPrevious()->getPrevious());
        $this->assertEquals(3, $finalError->context['retry_attempts']);
    }

    public function testEmptyContext(): void
    {
        $error = new PaymentError('Empty context error', 0, null, []);
        $this->assertIsArray($error->context);
        $this->assertEmpty($error->context);
    }

    public function testComplexContext(): void
    {
        $context = [
            'payment_id' => 'pay_abc123',
            'customer' => [
                'id' => 'cust_789',
                'email' => 'customer@example.com',
                'country' => 'US',
            ],
            'payment_details' => [
                'amount' => 250.00,
                'currency' => 'USD',
                'method' => 'card',
                'card' => [
                    'brand' => 'visa',
                    'last4' => '4242',
                    'exp_month' => 12,
                    'exp_year' => 2025,
                ],
            ],
            'metadata' => [
                'order_id' => 'order_456',
                'invoice_id' => 'inv_789',
            ],
            'timestamps' => [
                'created' => '2025-01-01T09:00:00Z',
                'attempted' => '2025-01-01T09:00:05Z',
                'failed' => '2025-01-01T09:00:10Z',
            ],
        ];
        
        $error = new PaymentError('Complex payment error', 400, null, $context);
        
        $this->assertEquals($context, $error->context);
        $this->assertEquals('pay_abc123', $error->context['payment_id']);
        $this->assertEquals('customer@example.com', $error->context['customer']['email']);
        $this->assertEquals(250.00, $error->context['payment_details']['amount']);
        $this->assertEquals('4242', $error->context['payment_details']['card']['last4']);
        $this->assertIsArray($error->context['timestamps']);
    }

    public function testFloatFormattingInInvalidAmount(): void
    {
        $error1 = PaymentError::invalidAmount(10.0);
        $this->assertEquals('Invalid payment amount: 10', $error1->getMessage());
        
        $error2 = PaymentError::invalidAmount(10.50);
        $this->assertEquals('Invalid payment amount: 10.5', $error2->getMessage());
        
        $error3 = PaymentError::invalidAmount(10.123);
        $this->assertEquals('Invalid payment amount: 10.123', $error3->getMessage());
    }
}