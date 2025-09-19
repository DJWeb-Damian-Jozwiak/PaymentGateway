<?php

declare(strict_types=1);

namespace DjWeb\Payments\Services\Invoice\IFirma\Strategies;

use DjWeb\Payments\DTOs\InvoiceData;

final class EUB2BInvoiceStrategy implements InvoiceStrategyContract
{
    public function getEndpoint(): string
    {
        return '/fakturaeksportuslugue.json';
    }

    public function supports(InvoiceData $data): bool
    {
        $country = $data->customer->address->country;
        return $country->isEu && $country->code !== 'PL' && $data->customer->isB2B;
    }

    /**
     * @return array<string, mixed>
     */
    public function prepareInvoiceData(InvoiceData $data): array
    {
        $currentDate = date('Y-m-d');

        return [
            'NazwaUslugi' => $data->productName,
            'Zaplacono' => $data->amount->amount,
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

        $result = [
            'Nazwa' => $customer->companyName ?? $customer->fullName,
            'Ulica' => $customer->address->street,
            'KodPocztowy' => $customer->address->postalCode,
            'KodKraju' => $customer->address->country->code,
            'Miejscowosc' => $customer->address->city,
            'Email' => $customer->email,
            'OsobaFizyczna' => false,
        ];

        if ($customer->vatNumber !== null) {
            $result['NIP'] = (string) $customer->vatNumber;
        }

        if ($customer->address->stateProvince !== null) {
            $result['Wojewodztwo'] = $customer->address->stateProvince;
        }

        return $result;
    }
}
