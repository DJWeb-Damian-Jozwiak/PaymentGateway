<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\ValueObjects;

use DjWeb\Payments\ValueObjects\Country;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

final class CountryTest extends TestCase
{
    public function testCanCreateCountryWithValidCode(): void
    {
        $country = new Country('PL');
        
        $this->assertSame('PL', $country->code);
        $this->assertSame('Poland', $country->name);
        $this->assertTrue($country->isEu);
    }
    
    public function testCountryCodeIsAlwaysUppercase(): void
    {
        $country = new Country('de');
        
        $this->assertSame('DE', $country->code);
        $this->assertSame('Germany', $country->name);
    }
    
    public function testThrowsExceptionForInvalidCountryCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Country code must be 2-letter ISO code');
        
        new Country('POL');
    }
    
    public function testThrowsExceptionForShortCountryCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Country code must be 2-letter ISO code');
        
        new Country('P');
    }
    
    public function testThrowsExceptionForUnknownCountryCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid country code: XX');
        
        new Country('XX');
    }
    
    public function testEuCountryDetection(): void
    {
        $euCountries = ['PL', 'DE', 'FR', 'IT', 'ES'];
        $nonEuCountries = ['US', 'JP', 'CA', 'AU', 'BR'];
        
        foreach ($euCountries as $code) {
            $country = new Country($code);
            $this->assertTrue($country->isEu, "Country {$code} should be EU");
        }
        
        foreach ($nonEuCountries as $code) {
            $country = new Country($code);
            $this->assertFalse($country->isEu, "Country {$code} should not be EU");
        }
    }
    
    public function testGetVatRateForEuCountries(): void
    {
        $expectedRates = [
            'PL' => 0.23,
            'DE' => 0.19,
            'FR' => 0.20,
            'IT' => 0.22,
            'ES' => 0.21,
            'DK' => 0.25,
            'HU' => 0.27,
        ];
        
        foreach ($expectedRates as $code => $expectedRate) {
            $country = new Country($code);
            $this->assertSame($expectedRate, $country->getVatRate(), "VAT rate for {$code}");
        }
    }
    
    public function testGetVatRateForNonEuCountries(): void
    {
        $nonEuCountries = ['US', 'JP', 'CA', 'AU', 'BR'];
        
        foreach ($nonEuCountries as $code) {
            $country = new Country($code);
            $this->assertSame(0.0, $country->getVatRate(), "VAT rate for {$code} should be 0");
        }
    }
    
    public function testRequiresStateProvince(): void
    {
        $countriesRequiringState = ['US', 'CA', 'AU', 'BR', 'MX', 'IN', 'MY', 'AR'];
        $countriesNotRequiringState = ['PL', 'DE', 'FR', 'GB', 'JP'];
        
        foreach ($countriesRequiringState as $code) {
            $country = new Country($code);
            $this->assertTrue($country->requiresStateProvince(), "{$code} should require state/province");
        }
        
        foreach ($countriesNotRequiringState as $code) {
            $country = new Country($code);
            $this->assertFalse($country->requiresStateProvince(), "{$code} should not require state/province");
        }
    }
    
    public function testEquals(): void
    {
        $country1 = new Country('PL');
        $country2 = new Country('PL');
        $country3 = new Country('DE');
        
        $this->assertTrue($country1->equals($country2));
        $this->assertFalse($country1->equals($country3));
    }
    
    public function testToString(): void
    {
        $country = new Country('PL');
        
        $this->assertSame('PL', (string) $country);
    }
    
    public function testStaticIso3166Instance(): void
    {
        // Test that multiple country instances share the same ISO3166 instance
        $country1 = new Country('PL');
        $country2 = new Country('DE');
        
        // Both should work without issues (static instance sharing)
        $this->assertNotEmpty($country1->name);
        $this->assertNotEmpty($country2->name);
    }
    
    public function testToArrayMethod(): void
    {
        $country = new Country('FR');
        
        $array = $country->toArray();
        
        $this->assertIsArray($array);
        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('is_eu', $array);
        $this->assertEquals('FR', $array['code']);
        $this->assertEquals('France', $array['name']);
        $this->assertTrue($array['is_eu']);
    }
    
    public function testGetVatRateForAllEuCountries(): void
    {
        // Test all EU countries have a VAT rate
        $euCountries = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        ];
        
        foreach ($euCountries as $code) {
            $country = new Country($code);
            $vatRate = $country->getVatRate();
            $this->assertGreaterThan(0, $vatRate, "VAT rate for EU country {$code} should be > 0");
            $this->assertLessThanOrEqual(0.27, $vatRate, "VAT rate for EU country {$code} should be <= 27%");
        }
    }
    
    public function testSpecificVatRates(): void
    {
        // Test specific VAT rates for countries not covered in main test
        $specificRates = [
            'BE' => 0.21,
            'CZ' => 0.21,
            'LV' => 0.21,
            'LT' => 0.21,
            'NL' => 0.21,
            'HR' => 0.25,
            'SE' => 0.25,
            'CY' => 0.19,
            'RO' => 0.19,
            'EE' => 0.22,
            'SI' => 0.22,
            'FI' => 0.24,
            'GR' => 0.24,
            'IE' => 0.23,
            'PT' => 0.23,
            'LU' => 0.17,
            'MT' => 0.18,
            'AT' => 0.20,  // default
            'BG' => 0.20,  // default
            'SK' => 0.20,  // default
        ];
        
        foreach ($specificRates as $code => $expectedRate) {
            $country = new Country($code);
            $this->assertEquals($expectedRate, $country->getVatRate(), "VAT rate for {$code}");
        }
    }
}