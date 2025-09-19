<?php

declare(strict_types=1);

namespace DjWeb\Payments\DTOs;

use DjWeb\Payments\ValueObjects\VatNumber;

final class CustomerData extends BaseDto
{
    public bool $isB2B {
        get => $this->vatNumber !== null || $this->companyName !== null;
    }

    public string $fullName {
        get => "{$this->firstName} {$this->lastName}";
    }

    public string $displayName {
        get => $this->companyName ?? $this->fullName;
    }
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly AddressData $address,
        public readonly ?string $companyName = null,
        public readonly ?VatNumber $vatNumber = null,
        public readonly ?string $phone = null,
    ) {
    }
}
