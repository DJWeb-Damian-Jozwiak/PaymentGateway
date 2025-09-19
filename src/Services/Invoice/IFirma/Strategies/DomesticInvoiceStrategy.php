<?php

declare(strict_types=1);

namespace DjWeb\Payments\Services\Invoice\IFirma\Strategies;

use DjWeb\Payments\DTOs\InvoiceData;

final class DomesticInvoiceStrategy implements InvoiceStrategyContract
{
    public function getEndpoint(): string
    {
        return '/fakturakraj.json';
    }

    public function supports(InvoiceData $data): bool
    {
        return $data->customer->address->country->code === 'PL'
            && $data->amount->currency === 'PLN';
    }

    /**
     * @return array<string, mixed>
     */
    public function prepareInvoiceData(InvoiceData $data): array
    {
        $customer = $this->prepareCustomerData($data);
        $position = $this->preparePosition($data);

        return [
            'Zaplacono' => $data->amount->amount,
            'LiczOd' => 'BRT',
            'SplitPayment' => false,
            'DataWystawienia' => $data->issueDate?->format('Y-m-d') ?? date('Y-m-d'),
            'DataSprzedazy' => $data->saleDate?->format('Y-m-d') ?? date('Y-m-d'),
            'FormatDatySprzedazy' => 'DZN',
            'TerminPlatnosci' => date('Y-m-d'),
            'SposobZaplaty' => $this->mapPaymentMethod($data->paymentMethod ?? 'transfer'),
            'RodzajPodpisuOdbiorcy' => 'BWO',
            'WidocznyNumerGios' => false,
            'Pozycje' => [$position],
            'Kontrahent' => $customer,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareCustomerData(InvoiceData $data): array
    {
        $customer = $data->customer;

        $result = [
            'Nazwa' => $customer->companyName ?? $customer->fullName,
            'Ulica' => $customer->address->street,
            'NIP' => $customer->vatNumber->number ?? null,
            'KodPocztowy' => $customer->address->postalCode,
            'KodKraju' => $customer->address->country->code,
            'Miejscowosc' => $customer->address->city,
            'Email' => $customer->email,
            'OsobaFizyczna' => ! $customer->isB2B,
        ];

        return array_filter($result, static fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function preparePosition(InvoiceData $data): array
    {
        $position = [
            'StawkaVat' => 0.23,
            'Ilosc' => 1,
            'CenaJednostkowa' => $data->originalAmount->amount,
            'NazwaPelna' => $data->productName,
            'Jednostka' => 'szt.',
            'TypStawkiVat' => 'PRC',
            'Rabat' => $data->discount->percentage ?? null,
        ];

        return array_filter($position, static fn ($value) => $value !== null);
    }

    private function mapPaymentMethod(string $method): string
    {
        return match ($method) {
            'cash' => 'GTK',
            'card' => 'KAR',
            'paypal' => 'PAL',
            'p24' => 'P24',
            default => 'PRZ',
        };
    }
}
