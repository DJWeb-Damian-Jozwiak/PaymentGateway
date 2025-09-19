<?php

declare(strict_types=1);

namespace DjWeb\Payments\Contracts;

use DjWeb\Payments\DTOs\PaymentIntent;
use DjWeb\Payments\DTOs\PaymentRequest;
use DjWeb\Payments\DTOs\PaymentResult;

interface PaymentGatewayContract
{
    /**
     * Create payment intent
     */
    public function createPaymentIntent(PaymentRequest $request): PaymentIntent;

    /**
     * Process payment
     */
    public function processPayment(PaymentIntent $intent): PaymentResult;

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool;

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $paymentId): PaymentResult;

    /**
     * Refund payment
     */
    public function refundPayment(string $paymentId, ?float $amount = null): PaymentResult;
}
