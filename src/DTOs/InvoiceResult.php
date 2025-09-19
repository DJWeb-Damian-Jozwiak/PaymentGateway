<?php

declare(strict_types=1);

namespace DjWeb\Payments\DTOs;

final class InvoiceResult extends BaseDto
{
    public bool $hasError {
        get => $this->errorMessage !== null;
    }
    public function __construct(
        public readonly bool $success,
        public readonly ?string $invoiceId = null,
        public readonly ?string $invoiceNumber = null,
        public readonly ?string $pdfUrl = null,
        public readonly ?string $errorMessage = null,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
        public readonly ?\DateTimeImmutable $createdAt = null,
    ) {
    }
}
