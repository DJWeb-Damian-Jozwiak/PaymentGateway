<?php

declare(strict_types=1);

namespace DjWeb\Payments\DTOs;

use DjWeb\Payments\ValueObjects\Money;

final class PaymentResult extends BaseDto
{
    public bool $hasError {
        get => $this->errorMessage !== null;
    }

    public function __construct(
        public readonly bool $success,
        public readonly string $transactionId,
        public readonly string $status,
        public readonly Money $amount,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
        public readonly ?string $errorMessage = null,
        public readonly ?\DateTimeImmutable $processedAt = null,
    ) {
    }
}
