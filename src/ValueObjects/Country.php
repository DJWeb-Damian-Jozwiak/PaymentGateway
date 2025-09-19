<?php

declare(strict_types=1);

namespace DjWeb\Payments\ValueObjects;

use DjWeb\Payments\Contracts\Arrayable;
use League\ISO3166\ISO3166;

final class Country implements Arrayable
{
    public private(set) string $code {
        get => strtoupper($this->code);
        set {
            if (strlen($value) !== 2) {
                throw new \InvalidArgumentException('Country code must be 2-letter ISO code');
            }
            $this->code = $value;
        }
    }
    public readonly string $name;
    public readonly bool $isEu;
    private static ?ISO3166 $iso3166 = null;
    public function __construct(string $code)
    {
        $this->code = $code;
        $this->name = $this->getCountryName($code);
        $this->isEu = $this->checkIfEu($code);
    }

    public function __toString(): string
    {
        return $this->code;
    }

    public function getVatRate(): float
    {
        if (! $this->isEu) {
            return 0.0;
        }

        return match ($this->code) {
            'BE', 'CZ', 'LV', 'LT', 'NL', 'ES' => 0.21,
            'HR', 'DK', 'SE' => 0.25,
            'CY', 'DE', 'RO' => 0.19,
            'EE', 'IT', 'SI' => 0.22,
            'FI', 'GR' => 0.24,
            'HU' => 0.27,
            'IE', 'PL', 'PT' => 0.23,
            'LU' => 0.17,
            'MT' => 0.18,
            default => 0.20,
        };
    }

    public function requiresStateProvince(): bool
    {
        return in_array($this->code, ['US', 'CA', 'AU', 'BR', 'MX', 'IN', 'MY', 'AR'], true);
    }

    public function equals(Country $other): bool
    {
        return $this->code === $other->code;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'is_eu' => $this->isEu,
        ];
    }

    private function getCountryName(string $code): string
    {
        self::$iso3166 ??= new ISO3166();

        try {
            $country = self::$iso3166->alpha2($code);
            return $country['name'];
        } catch (\Exception) {
            throw new \InvalidArgumentException("Invalid country code: {$code}");
        }
    }

    private function checkIfEu(string $code): bool
    {
        $euCountries = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        ];

        return in_array($code, $euCountries, true);
    }
}
