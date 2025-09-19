<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\ValueObjects;

use DjWeb\Payments\ValueObjects\VatNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

final class VatNumberTest extends TestCase
{
    public function testCanCreateVatNumberWithCountryPrefix(): void
    {
        $vatNumber = new VatNumber('PL', '5260001246');

        $this->assertSame('PL', $vatNumber->countryPrefix);
        $this->assertSame('5260001246', $vatNumber->number);
    }


    public function testCountryPrefixIsAlwaysUppercase(): void
    {
        $vatNumber = new VatNumber('pl', '5260001246');

        $this->assertSame('PL', $vatNumber->countryPrefix);
    }

    public function testNumberIsCleanedAndUppercased(): void
    {
        $vatNumber = new VatNumber('PL', '526-000-12-46');

        $this->assertSame('5260001246', $vatNumber->number);
    }

    public function testThrowsExceptionForEmptyNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('VAT number cannot be empty');

        new VatNumber('PL', '');
    }

    public function testThrowsExceptionForInvalidPolishNip(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Polish NIP checksum');

        new VatNumber('PL', '1234567890'); // Invalid checksum
    }

    public function testValidPolishNipPasses(): void
    {
        // Valid Polish NIP with correct checksum
        $vatNumber = new VatNumber('PL', '5260001246');

        $this->assertSame('PL', $vatNumber->countryPrefix);
        $this->assertSame('5260001246', $vatNumber->number);
    }


    public function testGetFullNumberWithPrefix(): void
    {
        $vatNumber = new VatNumber('PL', '5260001246');

        $this->assertSame('PL5260001246', (string)$vatNumber);
    }

    public function testToString(): void
    {
        $vatNumber = new VatNumber('PL', '5260001246');

        $this->assertSame('PL5260001246', (string)$vatNumber);
    }

    public static function euVatNumberProvider(): array
    {
        return [
            ['AT', 'U12345678'],
            ['BE', '0123456789'],
            ['DE', '123456789'],
            ['ES', 'A12345674'],
            ['FR', '12123456789'],
            ['GB', '123456789'],
            ['IT', '12345678901'],
        ];
    }

    #[DataProvider('euVatNumberProvider')]
    public function testEuVatNumberFormats(string $countryCode, string $vatId): void
    {
        $vatNumber = new VatNumber($countryCode, $vatId);
        $this->assertNotNull($vatNumber);
    }

    public static function InvalidEuVatNumberFormatsProvider(): array
    {
        return  [
            ['DE', '12345678'], // Too short for German VAT
            ['AT', '12345678'], // Missing 'U' prefix for Austria
            ['IT', '1234567890'], // Too short for Italy
        ];
    }

    #[DataProvider('InvalidEuVatNumberFormatsProvider')]
    public function testInvalidEuVatNumberFormats(string $country, string $number): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VatNumber($country, $number);
    }

    /**
     * Test Polish NIP checksum calculation
     */
    public function testPolishNipChecksumCalculation(): void
    {
        // Generate a valid NIP for testing
        $nipBase = '526000124'; // 9 digits
        $weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $nipBase[$i]) * $weights[$i];
        }

        $checksum = $sum % 11;
        $validNip = $nipBase . $checksum;

        $vatNumber = new VatNumber('PL', $validNip);
        $this->assertSame($validNip, $vatNumber->number);
    }
    
    public function testIsEuMethod(): void
    {
        $euVatNumber = new VatNumber('DE', '123456789');
        $this->assertTrue($euVatNumber->isEu());
        
        $euVatNumber2 = new VatNumber('FR', '12123456789');
        $this->assertTrue($euVatNumber2->isEu());
        
        $nonEuVatNumber = new VatNumber('US', '123456789');
        $this->assertFalse($nonEuVatNumber->isEu());
        
        $gbVatNumber = new VatNumber('GB', '123456789');
        $this->assertFalse($gbVatNumber->isEu()); // GB is not in EU after Brexit
    }
    
    public function testThrowsExceptionForInvalidCountryPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Country code must be 2-letter ISO code');
        
        new VatNumber('POL', '5260001246');
    }
    
    public function testThrowsExceptionForEmptyCountryPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Country code must be 2-letter ISO code');
        
        new VatNumber('', '5260001246');
    }
    
    public function testThrowsExceptionForSingleLetterCountryPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Country code must be 2-letter ISO code');
        
        new VatNumber('P', '5260001246');
    }
    
    public function testNumberCleaningRemovesSpacesAndDashes(): void
    {
        $vatNumber = new VatNumber('PL', '526 000 12-46');
        $this->assertSame('5260001246', $vatNumber->number);
        
        $vatNumber2 = new VatNumber('DE', '123-456-789');
        $this->assertSame('123456789', $vatNumber2->number);
        
        $vatNumber3 = new VatNumber('FR', '12 123 456 789');
        $this->assertSame('12123456789', $vatNumber3->number);
    }
    
    public function testCountryPrefixTrimming(): void
    {
        $vatNumber = new VatNumber(' PL ', '5260001246');
        $this->assertSame('PL', $vatNumber->countryPrefix);
        
        $vatNumber2 = new VatNumber('  de  ', '123456789');
        $this->assertSame('DE', $vatNumber2->countryPrefix);
    }
    
    public function testFullToStringFormatting(): void
    {
        $vatNumber = new VatNumber('PL', '5260001246');
        $this->assertSame('PL5260001246', (string)$vatNumber);
        
        $vatNumber2 = new VatNumber('DE', '123456789');
        $this->assertSame('DE123456789', (string)$vatNumber2);
    }
    
    public function testAllEuCountriesAreRecognized(): void
    {
        // Test with valid VAT numbers for each EU country
        $validNumbers = [
            'AT' => 'U12345678',
            'BE' => '0123456789',
            'BG' => '123456789',
            'CY' => '12345678L',
            'CZ' => '12345678',
            'DE' => '123456789',
            'DK' => '12345678',
            'EE' => '123456789',
            'ES' => 'A12345674',
            'FI' => '12345678',
            'FR' => '12123456789',
            'GR' => '123456789',
            'HR' => '12345678901',
            'HU' => '12345678',
            'IE' => '1234567X',
            'IT' => '12345678901',
            'LT' => '123456789',
            'LU' => '12345678',
            'LV' => '12345678901',
            'MT' => '12345678',
            'NL' => '123456789B01',
            'PL' => '5260001246', // Valid Polish NIP
            'PT' => '123456789',
            'RO' => '12345678',
            'SE' => '123456789012',
            'SI' => '12345678',
            'SK' => '1234567890'
        ];
        
        foreach ($validNumbers as $country => $number) {
            $vatNumber = new VatNumber($country, $number);
            $this->assertTrue($vatNumber->isEu(), "Country {$country} should be recognized as EU");
        }
    }
}
