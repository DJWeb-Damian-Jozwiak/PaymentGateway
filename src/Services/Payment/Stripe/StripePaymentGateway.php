<?php

declare(strict_types=1);

namespace DjWeb\Payments\Services\Payment\Stripe;

use DjWeb\Payments\Contracts\PaymentGatewayContract;
use DjWeb\Payments\DTOs\PaymentIntent;
use DjWeb\Payments\DTOs\PaymentRequest;
use DjWeb\Payments\DTOs\PaymentResult;
use DjWeb\Payments\Exceptions\PaymentError;
use SensitiveParameter;
use Stripe\StripeClient;

final class StripePaymentGateway implements PaymentGatewayContract
{
    private StripeClient $client;

    public function __construct(
        #[SensitiveParameter]
        private readonly string $secretKey,
        #[SensitiveParameter]
        private readonly string $webhookSecret,
    ) {
        $this->client = new StripeClient($this->secretKey);
    }

    public function createPaymentIntent(PaymentRequest $request): PaymentIntent
    {
        $metadata = $this->prepareMetadata($request);

        $stripeIntent = $this->client->paymentIntents->create([
            'amount' => $request->amount->toSmallestUnit(),
            'currency' => strtolower($request->amount->currency),
            'description' => $request->description,
            'metadata' => $metadata,
            'receipt_email' => $request->customer->email,
        ]);

        $clientSecret = $stripeIntent->client_secret;
        if ($clientSecret === null) {
            throw new PaymentError('Payment intent client secret is null');
        }

        return new PaymentIntent(
            id: $stripeIntent->id,
            clientSecret: $clientSecret,
            amount: $request->amount,
            status: $stripeIntent->status,
            metadata: $metadata,
        );
    }

    public function processPayment(PaymentIntent $intent): PaymentResult
    {
        $stripeIntent = $this->client->paymentIntents->retrieve($intent->id);

        return new PaymentResult(
            success: $stripeIntent->status === 'succeeded',
            transactionId: $stripeIntent->id,
            status: $stripeIntent->status,
            amount: $intent->amount,
            metadata: $stripeIntent->metadata->toArray(),
            errorMessage: $this->getPaymentErrorMessage($stripeIntent->last_payment_error),
        );
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        \Stripe\Webhook::constructEvent($payload, $signature, $this->webhookSecret);
        return true;
    }

    public function getPaymentStatus(string $paymentId): PaymentResult
    {
        $stripeIntent = $this->client->paymentIntents->retrieve($paymentId);

        return new PaymentResult(
            success: $stripeIntent->status === 'succeeded',
            transactionId: $stripeIntent->id,
            status: $stripeIntent->status,
            amount: \DjWeb\Payments\ValueObjects\Money::fromSmallestUnit(
                $stripeIntent->amount,
                strtoupper($stripeIntent->currency)
            ),
            metadata: $stripeIntent->metadata->toArray(),
            errorMessage: $this->getPaymentErrorMessage($stripeIntent->last_payment_error),
        );
    }

    public function refundPayment(string $paymentId, ?float $amount = null): PaymentResult
    {
        $params = ['payment_intent' => $paymentId];

        if ($amount !== null) {
            $params['amount'] = (int) ($amount * 100);
        }

        $refund = $this->client->refunds->create($params);

        return new PaymentResult(
            success: $refund->status === 'succeeded',
            transactionId: $refund->id,
            status: 'refunded',
            amount: \DjWeb\Payments\ValueObjects\Money::fromSmallestUnit(
                $refund->amount,
                strtoupper($refund->currency)
            ),
            metadata: ['refund_id' => $refund->id],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareMetadata(PaymentRequest $request): array
    {
        return array_filter([
            'email' => $request->customer->email,
            'first_name' => $request->customer->firstName,
            'last_name' => $request->customer->lastName,
            'company_name' => $request->customer->companyName,
            'nip' => (string) $request->customer->vatNumber,
            'street' => $request->customer->address->street,
            'city' => $request->customer->address->city,
            'postal_code' => $request->customer->address->postalCode,
            'country' => $request->customer->address->country->code,
            'state_province' => $request->customer->address->stateProvince,
            'discount_code' => $request->discount?->code,
            'discount_percentage' => $request->discount?->percentage,
        ]);
    }

    /**
     * Safely extract error message from Stripe payment error object
     */
    private function getPaymentErrorMessage(?object $paymentError): ?string
    {
        return $paymentError->message ?? null;
    }
}
