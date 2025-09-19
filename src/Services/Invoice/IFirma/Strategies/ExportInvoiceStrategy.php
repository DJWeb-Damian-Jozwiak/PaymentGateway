<?php

declare(strict_types=1);

namespace DjWeb\Payments\Services\Invoice\IFirma\Strategies;

use DjWeb\Payments\DTOs\InvoiceData;

final class ExportInvoiceStrategy implements InvoiceStrategyContract
{
    public function getEndpoint(): string
    {
        return '/fakturaeksportuslug.json';
    }

    public function supports(InvoiceData $data): bool
    {
        $country = $data->customer->address->country;
        return ! $country->isEu && $country->code !== 'PL';
    }

    /**
     * @return array<string, mixed>
     */
    public function prepareInvoiceData(InvoiceData $data): array
    {
        $currentDate = date('Y-m-d');

        return [
            'NazwaUslugi' => $data->productName,
            'UslugaSwiadczonaTrybArt28b' => false, // Not 28b for non-EU
            'DataWystawienia' => $data->issueDate?->format('Y-m-d') ?? $currentDate,
            'DataSprzedazy' => $data->saleDate?->format('Y-m-d') ?? $currentDate,
            'FormatDatySprzedazy' => 'DZN',
            'DataObowiazkuPodatkowego' => $currentDate,
            'SposobZaplaty' => 'PRZ',
            'Kontrahent' => $this->prepareCustomerData($data),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareCustomerData(InvoiceData $data): array
    {
        $customer = $data->customer;
        $country = $customer->address->country;

        $result = [
            'Nazwa' => $customer->companyName ?? $customer->fullName,
            'Ulica' => $customer->address->street,
            'KodPocztowy' => $customer->address->postalCode,
            'Kraj' => $country->name,
            'Miejscowosc' => $customer->address->city,
            'Email' => $customer->email,
        ];

        if ($customer->address->stateProvince !== null) {
            $result['Wojewodztwo'] = $customer->address->stateProvince;
        }

        return $result;
    }
}
