<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\DTOs;

use DjWeb\Payments\DTOs\AddressData;
use DjWeb\Payments\DTOs\CustomerData;
use DjWeb\Payments\DTOs\DiscountData;
use DjWeb\Payments\DTOs\InvoiceData;
use DjWeb\Payments\ValueObjects\Money;
use DjWeb\Payments\ValueObjects\VatNumber;
use PHPUnit\Framework\TestCase;

final class InvoiceDataTest extends TestCase
{
    private function createCustomer(): CustomerData
    {
        return new CustomerData(
            email: 'test@example.com',
            address: new AddressData(
                street: '123 Main St',
                city: 'Warsaw',
                postalCode: '00-001',
                country: 'PL'
            ),
            firstName: 'John',
            lastName: 'Doe',
            companyName: 'Test Company',
            vatNumber: new VatNumber('PL', '5260001246')
        );
    }

    public function testCreateInvoiceDataWithoutDiscount(): void
    {
        $customer = $this->createCustomer();
        $amount = new Money(100.00, 'PLN');
        $originalAmount = new Money(100.00, 'PLN');

        $invoice = new InvoiceData(
            customer: $customer,
            amount: $amount,
            originalAmount: $originalAmount,
            productName: 'Test Product'
        );

        $this->assertSame($customer, $invoice->customer);
        $this->assertSame($amount, $invoice->amount);
        $this->assertSame($originalAmount, $invoice->originalAmount);
        $this->assertEquals('Test Product', $invoice->productName);
        $this->assertNull($invoice->discount);
        $this->assertNull($invoice->issueDate);
        $this->assertNull($invoice->saleDate);
        $this->assertEquals('transfer', $invoice->paymentMethod);
        $this->assertEmpty($invoice->metadata);
        $this->assertEquals(0, $invoice->discountAmount->amount);
        $this->assertEquals('PLN', $invoice->discountAmount->currency);
    }

    public function testCreateInvoiceDataWithDiscount(): void
    {
        $customer = $this->createCustomer();
        $amount = new Money(90.00, 'EUR');
        $originalAmount = new Money(100.00, 'EUR');
        $discount = new DiscountData('SAVE10', 10.00);

        $invoice = new InvoiceData(
            customer: $customer,
            amount: $amount,
            originalAmount: $originalAmount,
            productName: 'Test Product',
            discount: $discount
        );

        $this->assertSame($discount, $invoice->discount);
        $this->assertEquals(10.00, $invoice->discountAmount->amount);
        $this->assertEquals('EUR', $invoice->discountAmount->currency);
    }

    public function testCreateInvoiceDataWithDates(): void
    {
        $customer = $this->createCustomer();
        $amount = new Money(100.00, 'USD');
        $originalAmount = new Money(100.00, 'USD');
        $issueDate = new \DateTimeImmutable('2025-01-01');
        $saleDate = new \DateTimeImmutable('2025-01-02');

        $invoice = new InvoiceData(
            customer: $customer,
            amount: $amount,
            originalAmount: $originalAmount,
            productName: 'Test Product',
            issueDate: $issueDate,
            saleDate: $saleDate
        );

        $this->assertSame($issueDate, $invoice->issueDate);
        $this->assertSame($saleDate, $invoice->saleDate);
    }

    public function testCreateInvoiceDataWithPaymentMethod(): void
    {
        $customer = $this->createCustomer();
        $amount = new Money(100.00, 'PLN');
        $originalAmount = new Money(100.00, 'PLN');

        $invoice = new InvoiceData(
            customer: $customer,
            amount: $amount,
            originalAmount: $originalAmount,
            productName: 'Test Product',
            paymentMethod: 'card'
        );

        $this->assertEquals('card', $invoice->paymentMethod);
    }

    public function testCreateInvoiceDataWithMetadata(): void
    {
        $customer = $this->createCustomer();
        $amount = new Money(100.00, 'PLN');
        $originalAmount = new Money(100.00, 'PLN');
        $metadata = [
            'order_id' => '12345',
            'custom_field' => 'custom_value',
            'tags' => ['important', 'priority']
        ];

        $invoice = new InvoiceData(
            customer: $customer,
            amount: $amount,
            originalAmount: $originalAmount,
            productName: 'Test Product',
            metadata: $metadata
        );

        $this->assertEquals($metadata, $invoice->metadata);
        $this->assertEquals('12345', $invoice->metadata['order_id']);
        $this->assertEquals('custom_value', $invoice->metadata['custom_field']);
        $this->assertIsArray($invoice->metadata['tags']);
    }

    public function testDiscountAmountWithHighPercentageDiscount(): void
    {
        $customer = $this->createCustomer();
        $amount = new Money(25.00, 'PLN');
        $originalAmount = new Money(100.00, 'PLN');
        $discount = new DiscountData('SAVE75', 75.00);

        $invoice = new InvoiceData(
            customer: $customer,
            amount: $amount,
            originalAmount: $originalAmount,
            productName: 'Test Product',
            discount: $discount
        );

        $this->assertEquals(75.00, $invoice->discountAmount->amount);
        $this->assertEquals('PLN', $invoice->discountAmount->currency);
    }

    public function testToArrayMethod(): void
    {
        $customer = $this->createCustomer();
        $amount = new Money(90.00, 'EUR');
        $originalAmount = new Money(100.00, 'EUR');
        $discount = new DiscountData('SAVE10', 10.00);
        $issueDate = new \DateTimeImmutable('2025-01-01');
        $saleDate = new \DateTimeImmutable('2025-01-02');

        $invoice = new InvoiceData(
            customer: $customer,
            amount: $amount,
            originalAmount: $originalAmount,
            productName: 'Test Product',
            discount: $discount,
            issueDate: $issueDate,
            saleDate: $saleDate,
            paymentMethod: 'card',
            metadata: ['order_id' => '123']
        );

        $array = $invoice->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('customer', $array);
        $this->assertArrayHasKey('amount', $array);
        $this->assertArrayHasKey('originalAmount', $array);
        $this->assertArrayHasKey('productName', $array);
        $this->assertArrayHasKey('discount', $array);
        $this->assertArrayHasKey('issueDate', $array);
        $this->assertArrayHasKey('saleDate', $array);
        $this->assertArrayHasKey('paymentMethod', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertEquals('Test Product', $array['productName']);
        $this->assertEquals('card', $array['paymentMethod']);
    }
}