<?php

declare(strict_types=1);

namespace DjWeb\Payments\Services\Invoice\IFirma\Strategies;

use DjWeb\Payments\DTOs\InvoiceData;
use DjWeb\Payments\Exceptions\InvoiceError;

final class InvoiceStrategyFactory
{
    /** @var array<InvoiceStrategyContract> */
    private array $strategies;

    public function __construct()
    {
        $this->strategies = [
            new CurrencyInvoiceStrategy(),    // Check first - domestic with foreign currency
            new DomesticInvoiceStrategy(),    // Poland, PLN
            new EUB2BInvoiceStrategy(),       // EU B2B (art. 28b)
            new OSSInvoiceStrategy(),         // EU B2C (OSS)
            new ExportInvoiceStrategy(),      // Non-EU
        ];
    }

    public function getStrategy(InvoiceData $data): InvoiceStrategyContract
    {
        $strategy = array_find($this->strategies, static fn ($strategy) => $strategy->supports($data));

        return $strategy ?? throw new InvoiceError(
            message: 'No appropriate invoice strategy found for country: ' . $data->customer->address->country->code
        );
    }
    /**
     * Add custom strategy
     */
    public function addStrategy(InvoiceStrategyContract $strategy): void
    {
        array_unshift($this->strategies, $strategy);
    }
}
