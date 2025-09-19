<?php

declare(strict_types=1);

namespace DjWeb\Payments\DTOs;

final class WebhookEvent extends BaseDto
{
    public bool $isPaymentEvent {
        get => str_starts_with($this->type, 'payment_intent.');
    }

    public bool $isInvoiceEvent {
        get => str_starts_with($this->type, 'invoice.');
    }

    public ?object $eventObject {
        get => $this->data['object'] ?? null;
    }

    public function __construct(
        public readonly string $id,
        public readonly string $type,
        /** @var array<string, mixed> */
        public readonly array $data,
        public readonly string $source,
        public ?\DateTimeImmutable $createdAt = null,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
        ];
    }
}
