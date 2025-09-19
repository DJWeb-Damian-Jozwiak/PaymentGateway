<?php

declare(strict_types=1);

namespace DjWeb\Payments\Contracts;

use DjWeb\Payments\DTOs\InvoiceData;
use DjWeb\Payments\DTOs\InvoiceResult;

interface InvoiceServiceContract
{
    /**
     * Create invoice
     */
    public function createInvoice(InvoiceData $data): InvoiceResult;

    /**
     * Get invoice PDF
     */
    public function getInvoicePdf(string $invoiceId): ?string;
}
