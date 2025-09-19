<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\DTOs;

use DjWeb\Payments\DTOs\AddressData;
use PHPUnit\Framework\TestCase;

final class AddressDataTest extends TestCase
{
    public function testCreateAddressDataWithoutStateForCountryThatDoesNotRequireIt(): void
    {
        $address = new AddressData(
            street: '123 Main St',
            city: 'Warsaw',
            postalCode: '00-001',
            country: 'PL'
        );

        $this->assertEquals('123 Main St', $address->street);
        $this->assertEquals('Warsaw', $address->city);
        $this->assertEquals('00-001', $address->postalCode);
        $this->assertEquals('PL', $address->country->code);
        $this->assertNull($address->stateProvince);
    }

    public function testCreateAddressDataWithStateForUSA(): void
    {
        $address = new AddressData(
            street: '123 Main St',
            city: 'New York',
            postalCode: '10001',
            country: 'US',
            stateProvince: 'NY'
        );

        $this->assertEquals('123 Main St', $address->street);
        $this->assertEquals('New York', $address->city);
        $this->assertEquals('10001', $address->postalCode);
        $this->assertEquals('US', $address->country->code);
        $this->assertEquals('NY', $address->stateProvince);
    }

    public function testThrowsExceptionWhenStateRequiredButNotProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('State/Province is required for US');

        new AddressData(
            street: '123 Main St',
            city: 'New York',
            postalCode: '10001',
            country: 'US'
        );
    }

    public function testThrowsExceptionWhenStateRequiredButEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('State/Province is required for CA');

        new AddressData(
            street: '123 Main St',
            city: 'Toronto',
            postalCode: 'M5V 2T6',
            country: 'CA',
            stateProvince: ''
        );
    }

    public function testToArrayMethod(): void
    {
        $address = new AddressData(
            street: '123 Main St',
            city: 'Warsaw',
            postalCode: '00-001',
            country: 'PL'
        );

        $array = $address->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('street', $array);
        $this->assertArrayHasKey('city', $array);
        $this->assertArrayHasKey('postalCode', $array);
        $this->assertArrayHasKey('country', $array);
        $this->assertEquals('123 Main St', $array['street']);
        $this->assertEquals('Warsaw', $array['city']);
        $this->assertEquals('00-001', $array['postalCode']);
    }
}