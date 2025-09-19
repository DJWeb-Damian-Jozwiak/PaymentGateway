<?php

declare(strict_types=1);

namespace DjWeb\Payments\Services\Invoice\IFirma\Strategies;

use DjWeb\Payments\DTOs\InvoiceData;

final class OSSInvoiceStrategy implements InvoiceStrategyContract
{
    public function getEndpoint(): string
    {
        return '/fakturaoss.json';
    }

    public function supports(InvoiceData $data): bool
    {
        $country = $data->customer->address->country;
        return $country->isEu && $country->code !== 'PL' && ! $data->customer->isB2B;
    }

    /**
     * @return array<string, mixed>
     */
    public function prepareInvoiceData(InvoiceData $data): array
    {
        $currentDate = date('Y-m-d');
        $country = $data->customer->address->country;

        return [
            'DataSprzedazy' => $data->saleDate?->format('Y-m-d') ?? $currentDate,
            'FormatDatySprzedazy' => 'DZN',
            'DataWystawienia' => $data->issueDate?->format('Y-m-d') ?? $currentDate,
            'Jezyk' => $this->getLanguageForCountry($country->code),
            'Waluta' => $data->amount->currency,
            'LiczOd' => 'BRT',
            'RodzajPodpisuOdbiorcy' => 'BWO',
            'WidocznyNumerBdo' => false,
            'SprzedazUslug' => true,
            'UstalenieMiejscaUslugi1' => 'IP',
            'UstalenieMiejscaUslugi2' => 'BillingAddress',
            'KrajDostawy' => $country->code,
            'KrajWysylki' => 'PL',
            'Pozycje' => [$this->preparePosition($data)],
            'Kontrahent' => $this->prepareCustomerData($data),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function preparePosition(InvoiceData $data): array
    {
        $country = $data->customer->address->country;
        $vatRate = $country->getVatRate();

        $position = [
            'NazwaPelna' => $data->productName,
            'NazwaPelnaObca' => $data->productName,
            'Jednostka' => 'szt.',
            'JednostkaObca' => 'pcs',
            'CenaJednostkowa' => $data->originalAmount->amount,
            'Ilosc' => 1,
            'StawkaVat' => $vatRate,
            'TypStawkiVat' => 'POD',
        ];

        if ($data->discount !== null && $data->discount->percentage > 0) {
            $position['Rabat'] = $data->discount->percentage;
        }

        return $position;
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareCustomerData(InvoiceData $data): array
    {
        $customer = $data->customer;
        $country = $customer->address->country;

        return [
            'Nazwa' => $customer->fullName,
            'Kraj' => $country->name,
            'Miejscowosc' => $customer->address->city,
            'KodPocztowy' => $customer->address->postalCode,
            'Ulica' => $customer->address->street,
            'Email' => $customer->email,
        ];
    }

    private function getLanguageForCountry(string $countryCode): string
    {
        return match ($countryCode) {
            'DE', 'AT' => 'de',
            'FR', 'BE', 'LU' => 'fr',
            'ES' => 'es',
            'IT' => 'it',
            'NL' => 'nl',
            'SE' => 'sv',
            'DK' => 'da',
            'FI' => 'fi',
            'PL' => 'pl',
            default => 'en',
        };
    }
}
