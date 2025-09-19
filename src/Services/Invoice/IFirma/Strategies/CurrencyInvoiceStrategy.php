<?php

declare(strict_types=1);

namespace DjWeb\Payments\Services\Invoice\IFirma\Strategies;

use DjWeb\Payments\DTOs\InvoiceData;

final class CurrencyInvoiceStrategy implements InvoiceStrategyContract
{
    public function getEndpoint(): string
    {
        return '/fakturawaluta.json';
    }

    public function supports(InvoiceData $data): bool
    {
        return $data->customer->address->country->code === 'PL'
            && $data->amount->currency !== 'PLN';
    }

    /**
     * @return array<string, mixed>
     */
    public function prepareInvoiceData(InvoiceData $data): array
    {
        // Use domestic strategy as base and add currency-specific fields
        $domesticStrategy = new DomesticInvoiceStrategy();
        $invoiceData = $domesticStrategy->prepareInvoiceData($data);

        // Add currency-specific fields
        $invoiceData['TypSprzedazy'] = 'KRAJOWA';
        $invoiceData['Waluta'] = $data->amount->currency;

        return $invoiceData;
    }
}
