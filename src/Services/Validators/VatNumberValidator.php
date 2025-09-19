<?php

declare(strict_types=1);

namespace DjWeb\Payments\Services\Validators;

use DjWeb\Payments\ValueObjects\VatNumber;

final class VatNumberValidator
{
    private const array PATTERNS = [
        'AT' => '/^U\d{8}$/',
        'BE' => '/^0\d{9}$/',
        'BG' => '/^\d{9,10}$/',
        'CY' => '/^\d{8}[A-Z]$/',
        'CZ' => '/^\d{8,10}$/',
        'DE' => '/^\d{9}$/',
        'DK' => '/^\d{8}$/',
        'EE' => '/^\d{9}$/',
        'ES' => '/^[A-Z]\d{7}[A-Z0-9]$/',
        'FI' => '/^\d{8}$/',
        'FR' => '/^[A-Z0-9]{2}\d{9}$/',
        'GB' => '/^(\d{9}|\d{12}|(GD|HA)\d{3})$/', // UK poza UE, ale często używane
        'GR' => '/^\d{9}$/',
        'HR' => '/^\d{11}$/',
        'HU' => '/^\d{8}$/',
        'IE' => '/^(\d{7}[A-Z]{1,2}|\d[A-Z]\d{5}[A-Z])$/',
        'IT' => '/^\d{11}$/',
        'LT' => '/^(\d{9}|\d{12})$/',
        'LU' => '/^\d{8}$/',
        'LV' => '/^\d{11}$/',
        'MT' => '/^\d{8}$/',
        'NL' => '/^\d{9}B\d{2}$/',
        'PL' => '/^\d{10}$/',
        'PT' => '/^\d{9}$/',
        'RO' => '/^\d{2,10}$/',
        'SE' => '/^\d{12}$/',
        'SI' => '/^\d{8}$/',
        'SK' => '/^\d{10}$/',
    ];

    public function assertVatFormat(VatNumber $number): void
    {
        $pattern = self::PATTERNS[$number->countryPrefix] ?? null;
        if ($pattern && ! preg_match($pattern, $number->number)) {
            throw new \InvalidArgumentException("Invalid VAT number format for {$number->countryPrefix}");
        }
    }
}
