<?php

declare(strict_types=1);

namespace DjWeb\Payments\DTOs;

use DjWeb\Payments\ValueObjects\Money;

final class PaymentIntent extends BaseDto
{
    public bool $isSucceeded {
        get => $this->status === 'succeeded';
    }

    public bool $isPending {
        get => in_array($this->status, ['processing', 'requires_action', 'requires_payment_method'], true);
    }
    public bool $isFailed {
        get => in_array($this->status, ['canceled', 'payment_failed'], true);
    }
    public function __construct(
        public readonly string $id,
        public readonly string $clientSecret,
        public readonly Money $amount,
        public string $status,
        /** @var array<string, mixed> */
        public array $metadata = [],
        public ?\DateTimeImmutable $createdAt = null,
    ) {
    }
}
