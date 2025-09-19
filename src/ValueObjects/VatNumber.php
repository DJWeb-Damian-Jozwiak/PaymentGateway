<?php

declare(strict_types=1);

namespace DjWeb\Payments\ValueObjects;

use DjWeb\Payments\Services\Validators\PolishNipValidator;
use DjWeb\Payments\Services\Validators\VatNumberValidator;

final class VatNumber implements \Stringable
{
    public private(set) string $countryPrefix {
        get => $this->countryPrefix;
        set {
            $v = mb_strtoupper(trim($value));
            if (strlen($v) !== 2) {
                throw new \InvalidArgumentException('Country code must be 2-letter ISO code');
            }
            $this->countryPrefix = $v;
        }
    }

    public private(set) string $number {
        get => $this->number;
        set {
            $v = preg_replace('/[ \-]/', '', $value); // normalizacja
            if (!$v) {
                throw new \InvalidArgumentException('VAT number cannot be empty');
            }
            $this->number = $v;
        }
    }
    /**
     * @var array<int, string>
     */
    private array $ueCountries = [
        'AT',
        'BE',
        'BG',
        'CY',
        'CZ',
        'DE',
        'DK',
        'EE',
        'ES',
        'FI',
        'FR',
        'GR',
        'HR',
        'HU',
        'IE',
        'IT',
        'LT',
        'LU',
        'LV',
        'MT',
        'NL',
        'PL',
        'PT',
        'RO',
        'SE',
        'SI',
        'SK',
    ];

    public function __construct(string $countryPrefix, string $number)
    {
        $this->countryPrefix = $countryPrefix;
        $this->number = $number;

        new VatNumberValidator()->assertVatFormat($this);
        if ($this->countryPrefix === 'PL') {
            new PolishNipValidator()->assertPolishNip($this);
        }
    }

    public function __toString(): string
    {
        return $this->countryPrefix ? $this->countryPrefix . $this->number : $this->number;
    }

    public function isEu(): bool
    {
        return in_array($this->countryPrefix, $this->ueCountries, true);
    }
}
