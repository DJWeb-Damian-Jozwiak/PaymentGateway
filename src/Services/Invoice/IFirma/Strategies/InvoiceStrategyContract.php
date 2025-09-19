<?php

declare(strict_types=1);

namespace DjWeb\Payments\Services\Invoice\IFirma\Strategies;

use DjWeb\Payments\DTOs\InvoiceData;

interface InvoiceStrategyContract
{
    /**
     * Get the iFirma API endpoint for this invoice type
     */
    public function getEndpoint(): string;

    /**
     * Prepare invoice data for the specific endpoint
     *
     * @return array<string, mixed>
     */
    public function prepareInvoiceData(InvoiceData $data): array;

    /**
     * Check if this strategy supports the given invoice data
     */
    public function supports(InvoiceData $data): bool;
}
