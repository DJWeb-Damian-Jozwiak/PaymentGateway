<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\Exceptions;

use DjWeb\Payments\Exceptions\InvoiceError;
use PHPUnit\Framework\TestCase;

final class InvoiceErrorTest extends TestCase
{
    public function testCreateBasicInvoiceError(): void
    {
        $error = new InvoiceError('Test error message');
        
        $this->assertInstanceOf(\Exception::class, $error);
        $this->assertEquals('Test error message', $error->getMessage());
        $this->assertEquals(0, $error->getCode());
        $this->assertNull($error->getPrevious());
        $this->assertNull($error->context);
    }

    public function testCreateInvoiceErrorWithAllParameters(): void
    {
        $previousException = new \Exception('Previous error');
        $context = ['field' => 'email', 'value' => 'invalid@'];
        
        $error = new InvoiceError(
            message: 'Validation failed',
            code: 422,
            previous: $previousException,
            context: $context
        );
        
        $this->assertEquals('Validation failed', $error->getMessage());
        $this->assertEquals(422, $error->getCode());
        $this->assertSame($previousException, $error->getPrevious());
        $this->assertEquals($context, $error->context);
    }

    public function testInvalidCustomerDataStaticConstructor(): void
    {
        $error = InvoiceError::invalidCustomerData('email');
        
        $this->assertInstanceOf(InvoiceError::class, $error);
        $this->assertEquals('Invalid customer data: email', $error->getMessage());
        $this->assertEquals(0, $error->getCode());
        $this->assertNull($error->getPrevious());
        $this->assertNull($error->context);
    }

    public function testInvalidCustomerDataWithDifferentFields(): void
    {
        $fields = ['vatNumber', 'address', 'company_name', 'postal_code'];
        
        foreach ($fields as $field) {
            $error = InvoiceError::invalidCustomerData($field);
            $this->assertEquals("Invalid customer data: {$field}", $error->getMessage());
        }
    }

    public function testUnsupportedCountryStaticConstructor(): void
    {
        $error = InvoiceError::unsupportedCountry('XX');
        
        $this->assertInstanceOf(InvoiceError::class, $error);
        $this->assertEquals('Unsupported country for invoicing: XX', $error->getMessage());
        $this->assertEquals(0, $error->getCode());
        $this->assertNull($error->getPrevious());
        $this->assertNull($error->context);
    }

    public function testUnsupportedCountryWithDifferentCodes(): void
    {
        $countries = ['RU', 'CN', 'NK', 'IR'];
        
        foreach ($countries as $country) {
            $error = InvoiceError::unsupportedCountry($country);
            $this->assertEquals("Unsupported country for invoicing: {$country}", $error->getMessage());
        }
    }

    public function testApiErrorStaticConstructor(): void
    {
        $error = InvoiceError::apiError('IFirma', 'Connection timeout');
        
        $this->assertInstanceOf(InvoiceError::class, $error);
        $this->assertEquals('Invoice API error (IFirma): Connection timeout', $error->getMessage());
        $this->assertEquals(0, $error->getCode());
        $this->assertNull($error->getPrevious());
        $this->assertNull($error->context);
    }

    public function testApiErrorWithDifferentServicesAndErrors(): void
    {
        $cases = [
            ['service' => 'IFirma', 'error' => 'Invalid API key'],
            ['service' => 'Stripe', 'error' => 'Rate limit exceeded'],
            ['service' => 'QuickBooks', 'error' => 'Authentication failed'],
            ['service' => 'FreshBooks', 'error' => 'Server error 500'],
        ];
        
        foreach ($cases as $case) {
            $error = InvoiceError::apiError($case['service'], $case['error']);
            $expected = "Invoice API error ({$case['service']}): {$case['error']}";
            $this->assertEquals($expected, $error->getMessage());
        }
    }

    public function testContextIsReadOnly(): void
    {
        $context = ['key' => 'value'];
        $error = new InvoiceError('Test', 0, null, $context);
        
        $this->assertEquals($context, $error->context);
    }

    public function testExceptionChaining(): void
    {
        $rootCause = new \RuntimeException('Database connection failed');
        $middleError = new InvoiceError('Could not fetch customer', 500, $rootCause);
        $topError = InvoiceError::apiError('IFirma', 'Invoice creation failed');
        
        $chainedError = new InvoiceError(
            message: $topError->getMessage(),
            code: 503,
            previous: $middleError,
            context: ['attempts' => 3, 'last_error' => $middleError->getMessage()]
        );
        
        $this->assertEquals('Invoice API error (IFirma): Invoice creation failed', $chainedError->getMessage());
        $this->assertEquals(503, $chainedError->getCode());
        $this->assertSame($middleError, $chainedError->getPrevious());
        $this->assertSame($rootCause, $chainedError->getPrevious()->getPrevious());
        $this->assertEquals(3, $chainedError->context['attempts']);
    }

    public function testEmptyContext(): void
    {
        $error = new InvoiceError('Test', 0, null, []);
        $this->assertIsArray($error->context);
        $this->assertEmpty($error->context);
    }

    public function testComplexContext(): void
    {
        $context = [
            'request_id' => 'req_123',
            'timestamp' => '2025-01-01T10:00:00Z',
            'user' => ['id' => 42, 'email' => 'user@example.com'],
            'invoice_data' => [
                'amount' => 100.00,
                'currency' => 'EUR',
                'items' => [
                    ['name' => 'Product A', 'quantity' => 2],
                    ['name' => 'Product B', 'quantity' => 1],
                ]
            ],
            'retry_count' => 0,
        ];
        
        $error = new InvoiceError('Complex error', 400, null, $context);
        
        $this->assertEquals($context, $error->context);
        $this->assertEquals('req_123', $error->context['request_id']);
        $this->assertEquals(42, $error->context['user']['id']);
        $this->assertCount(2, $error->context['invoice_data']['items']);
    }
}