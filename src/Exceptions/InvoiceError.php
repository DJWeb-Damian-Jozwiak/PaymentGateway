<?php

declare(strict_types=1);

namespace DjWeb\Payments\Exceptions;

final class InvoiceError extends \Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $context = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function invalidCustomerData(string $field): self
    {
        return new self("Invalid customer data: {$field}");
    }

    public static function unsupportedCountry(string $country): self
    {
        return new self("Unsupported country for invoicing: {$country}");
    }

    public static function apiError(string $service, string $error): self
    {
        return new self("Invoice API error ({$service}): {$error}");
    }
}
