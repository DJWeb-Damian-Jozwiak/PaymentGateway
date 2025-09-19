<?php

declare(strict_types=1);

namespace DjWeb\Payments\DTOs;

use DjWeb\Payments\Contracts\Arrayable;
use DjWeb\Payments\ValueObjects\Money;

final class PaymentRequest implements Arrayable
{
    public function __construct(
        public readonly Money $amount,
        public readonly CustomerData $customer,
        public readonly string $description,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
        public readonly ?string $returnUrl = null,
        public readonly ?string $cancelUrl = null,
        public readonly ?DiscountData $discount = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount->toSmallestUnit(),
            'currency' => $this->amount->currency,
            'description' => $this->description,
            'customer' => $this->customer->toArray(),
            'metadata' => array_merge($this->metadata, [
                'customer_email' => $this->customer->email,
                'discount_code' => $this->discount?->code,
                'discount_percentage' => $this->discount?->percentage,
            ]),
            'return_url' => $this->returnUrl,
            'cancel_url' => $this->cancelUrl,
        ];
    }
}
