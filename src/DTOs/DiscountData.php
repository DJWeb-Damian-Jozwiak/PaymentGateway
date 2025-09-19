<?php

declare(strict_types=1);

namespace DjWeb\Payments\DTOs;

final class DiscountData extends BaseDto
{
    public private(set) float $percentage {
        get => $this->percentage;
        set {
            if ($value < 0 || $value > 100) {
                throw new \InvalidArgumentException('Discount percentage must be between 0 and 100');
            }
            $this->percentage = $value;
        }
    }

    public bool $isValid {
        get {
            $violatesMaxUsages = $this->maxUsages !== null && $this->currentUsages >= $this->maxUsages;
            $violatesValidUntil = $this->validUntil !== null && $this->validUntil < new \DateTimeImmutable();
            return ! $violatesMaxUsages && ! $violatesValidUntil;
        }
    }

    public function __construct(
        public readonly string $code,
        float $percentage,
        public readonly ?int $maxUsages = null,
        public readonly int $currentUsages = 0,
        public readonly ?\DateTimeImmutable $validUntil = null,
    ) {
        $this->percentage = $percentage;
    }

    public function calculateDiscountAmount(float $originalAmount): float
    {
        if (! $this->isValid) {
            return 0;
        }
        return round($originalAmount * $this->percentage / 100, 2);
    }

    public function calculateFinalAmount(float $originalAmount): float
    {
        return $originalAmount - $this->calculateDiscountAmount($originalAmount);
    }
}
