<?php

declare(strict_types=1);

namespace DjWeb\Payments\DTOs;

use DjWeb\Payments\ValueObjects\Money;

final class InvoiceData extends BaseDto
{
    public Money $discountAmount {
        get {
            $amount = $this->discount?->calculateDiscountAmount($this->originalAmount->amount) ?? 0;
            return new Money($amount, $this->amount->currency);
        }
    }
    public function __construct(
        public readonly CustomerData $customer,
        public readonly Money $amount,
        public readonly Money $originalAmount,
        public readonly string $productName,
        public readonly ?DiscountData $discount = null,
        public readonly ?\DateTimeImmutable $issueDate = null,
        public readonly ?\DateTimeImmutable $saleDate = null,
        public readonly ?string $paymentMethod = 'transfer',
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {
    }
}
