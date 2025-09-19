<?php

declare(strict_types=1);

namespace DjWeb\Payments\Exceptions;

final class PaymentError extends \Exception
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

    public static function invalidAmount(float $amount): self
    {
        return new self("Invalid payment amount: {$amount}");
    }

    public static function unsupportedCurrency(string $currency): self
    {
        return new self("Unsupported currency: {$currency}");
    }

    public static function gatewayError(string $gateway, string $error): self
    {
        return new self("Payment gateway error ({$gateway}): {$error}");
    }
}
