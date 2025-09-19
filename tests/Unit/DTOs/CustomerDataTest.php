<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\DTOs;

use DjWeb\Payments\DTOs\CustomerData;
use DjWeb\Payments\DTOs\AddressData;
use DjWeb\Payments\ValueObjects\VatNumber;
use PHPUnit\Framework\TestCase;

final class CustomerDataTest extends TestCase
{
    public function testCanCreateCustomerDataWithoutVatNumber(): void
    {
        $address = new AddressData('123 Main St', 'Warsaw', '00-001', 'PL');
        $customer = new CustomerData(
            email: 'john@example.com',
            firstName: 'John',
            lastName: 'Doe',
            address: $address
        );

        $this->assertSame('john@example.com', $customer->email);
        $this->assertSame('John', $customer->firstName);
        $this->assertSame('Doe', $customer->lastName);
        $this->assertSame($address, $customer->address);
        $this->assertNull($customer->companyName);
        $this->assertNull($customer->vatNumber);
        $this->assertNull($customer->phone);
    }

    public function testCanCreateCustomerDataWithVatNumber(): void
    {
        $address = new AddressData('123 Main St', 'Warsaw', '00-001', 'PL');
        $vatNumber = new VatNumber('PL', '5260001246');
        $customer = new CustomerData(
            email: 'company@example.com',
            firstName: 'Jane',
            lastName: 'Smith',
            address: $address,
            companyName: 'Example Corp',
            vatNumber: $vatNumber,
            phone: '+48123456789'
        );

        $this->assertSame('company@example.com', $customer->email);
        $this->assertSame('Jane', $customer->firstName);
        $this->assertSame('Smith', $customer->lastName);
        $this->assertSame('Example Corp', $customer->companyName);
        $this->assertSame($vatNumber, $customer->vatNumber);
        $this->assertSame('+48123456789', $customer->phone);
    }

    public function testIsB2BReturnsTrueWithVatNumber(): void
    {
        $address = new AddressData('123 Main St', 'Warsaw', '00-001', 'PL');
        $vatNumber = new VatNumber('PL', '5260001246');
        $customer = new CustomerData(
            email: 'company@example.com',
            firstName: 'Jane',
            lastName: 'Smith',
            address: $address,
            vatNumber: $vatNumber
        );

        $this->assertTrue($customer->isB2B);
    }

    public function testIsB2BReturnsTrueWithCompanyName(): void
    {
        $address = new AddressData('123 Main St', 'Warsaw', '00-001', 'PL');
        $customer = new CustomerData(
            email: 'company@example.com',
            firstName: 'Jane',
            lastName: 'Smith',
            address: $address,
            companyName: 'Example Corp'
        );

        $this->assertTrue($customer->isB2B);
    }

    public function testIsB2BReturnsFalseForIndividualCustomer(): void
    {
        $address = new AddressData('123 Main St', 'Warsaw', '00-001', 'PL');
        $customer = new CustomerData(
            email: 'john@example.com',
            firstName: 'John',
            lastName: 'Doe',
            address: $address
        );

        $this->assertFalse($customer->isB2B);
    }

    public function testGetFullName(): void
    {
        $address = new AddressData('123 Main St', 'Warsaw', '00-001', 'PL');
        $customer = new CustomerData(
            email: 'john@example.com',
            firstName: 'John',
            lastName: 'Doe',
            address: $address
        );

        $this->assertSame('John Doe', $customer->fullName);
    }

    public function testGetDisplayNameReturnsCompanyNameWhenAvailable(): void
    {
        $address = new AddressData('123 Main St', 'Warsaw', '00-001', 'PL');
        $customer = new CustomerData(
            email: 'company@example.com',
            firstName: 'Jane',
            lastName: 'Smith',
            address: $address,
            companyName: 'Example Corp'
        );

        $this->assertSame('Example Corp', $customer->displayName);
    }

    public function testGetDisplayNameReturnsFullNameWhenNoCompany(): void
    {
        $address = new AddressData('123 Main St', 'Warsaw', '00-001', 'PL');
        $customer = new CustomerData(
            email: 'john@example.com',
            firstName: 'John',
            lastName: 'Doe',
            address: $address
        );

        $this->assertSame('John Doe', $customer->displayName);
    }

    public function testToArrayWithNullValues(): void
    {
        $address = new AddressData('123 Main St', 'Warsaw', '00-001', 'PL');
        $customer = new CustomerData(
            email: 'john@example.com',
            firstName: 'John',
            lastName: 'Doe',
            address: $address
        );

        $result = $customer->toArray();

        $this->assertNull($result['phone']);
    }
}
