<?php

declare(strict_types=1);

namespace DjWeb\Payments\ValueObjects;

use NumberFormatter;

final class Money implements \Stringable
{
    public private(set) float $amount {
        get => round($this->amount, 2);
        set {
            if ($value < 0) {
                throw new \InvalidArgumentException('Amount cannot be negative');
            }
            $this->amount = $value;
        }
    }

    public private(set) string $currency {
        get => strtoupper($this->currency);
        set {
            if (strlen($value) !== 3) {
                throw new \InvalidArgumentException('Currency must be 3-letter ISO code');
            }
            $this->currency = $value;
        }
    }

    public function __construct(float $amount, string $currency, private string $locale = 'pl_PL')
    {
        $this->amount = $amount;
        $this->currency = $currency;
    }

    public function __toString(): string
    {
        $value = new NumberFormatter($this->locale, NumberFormatter::CURRENCY)
            ->formatCurrency($this->amount, $this->currency);
        if ($value === false) {
            throw new \RuntimeException('Failed to format currency');
        }
        return $value;
    }

    public function toSmallestUnit(): int
    {
        return match ($this->currency) {
            'JPY', 'KRW' => (int) $this->amount,
            default => (int) ($this->amount * 100),
        };
    }

    public static function fromSmallestUnit(int $amount, string $currency): self
    {
        $divisor = match (strtoupper($currency)) {
            'JPY', 'KRW' => 1,
            default => 100,
        };

        return new self($amount / $divisor, $currency);
    }
}
