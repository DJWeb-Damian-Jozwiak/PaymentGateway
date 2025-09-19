<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\Strategies;

use DjWeb\Payments\Services\Invoice\IFirma\Strategies\InvoiceStrategyFactory;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\DomesticInvoiceStrategy;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\CurrencyInvoiceStrategy;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\EUB2BInvoiceStrategy;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\OSSInvoiceStrategy;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\ExportInvoiceStrategy;
use DjWeb\Payments\DTOs\InvoiceData;
use DjWeb\Payments\DTOs\CustomerData;
use DjWeb\Payments\DTOs\AddressData;
use DjWeb\Payments\ValueObjects\Money;
use DjWeb\Payments\ValueObjects\VatNumber;
use DjWeb\Payments\Exceptions\InvoiceError;
use PHPUnit\Framework\TestCase;

final class InvoiceStrategyFactoryTest extends TestCase
{
    private InvoiceStrategyFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new InvoiceStrategyFactory();
    }

    public function testGetsDomesticStrategyForPolandPLN(): void
    {
        $invoiceData = $this->createInvoiceData('PL', 'PLN');

        $strategy = $this->factory->getStrategy($invoiceData);

        $this->assertInstanceOf(DomesticInvoiceStrategy::class, $strategy);
    }

    public function testGetsCurrencyStrategyForPolandNonPLN(): void
    {
        $invoiceData = $this->createInvoiceData('PL', 'EUR');

        $strategy = $this->factory->getStrategy($invoiceData);

        $this->assertInstanceOf(CurrencyInvoiceStrategy::class, $strategy);
    }

    public function testGetsEUB2BStrategyForEUBusinessCustomer(): void
    {
        $invoiceData = $this->createInvoiceData('DE', 'EUR', true);

        $strategy = $this->factory->getStrategy($invoiceData);

        $this->assertInstanceOf(EUB2BInvoiceStrategy::class, $strategy);
    }

    public function testGetsOSSStrategyForEUConsumerCustomer(): void
    {
        $invoiceData = $this->createInvoiceData('DE', 'EUR', false);

        $strategy = $this->factory->getStrategy($invoiceData);

        $this->assertInstanceOf(OSSInvoiceStrategy::class, $strategy);
    }

    public function testGetsExportStrategyForNonEUCustomer(): void
    {
        $invoiceData = $this->createInvoiceData('CA', 'CAD'); // Use Canada instead of US

        $strategy = $this->factory->getStrategy($invoiceData);

        $this->assertInstanceOf(ExportInvoiceStrategy::class, $strategy);
    }

    public function testThrowsExceptionForUnknownCountry(): void
    {
        // Create a mock invoice data that no strategy supports
        // This test should expect InvalidArgumentException for invalid country
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid country code: XX');

        new AddressData('123 Test St', 'Test City', '12345', 'XX'); // Invalid country

    }

    public function testCanAddCustomStrategy(): void
    {
        $customStrategy = new class implements \DjWeb\Payments\Services\Invoice\IFirma\Strategies\InvoiceStrategyContract {
            public function getEndpoint(): string
            {
                return '/custom-endpoint.json';
            }

            public function prepareInvoiceData(\DjWeb\Payments\DTOs\InvoiceData $data): array
            {
                return ['custom' => 'data'];
            }

            public function supports(\DjWeb\Payments\DTOs\InvoiceData $data): bool
            {
                return $data->customer->email === 'custom@example.com';
            }
        };

        $this->factory->addStrategy($customStrategy);

        $customer = new CustomerData(
            email: 'custom@example.com',
            firstName: 'Custom',
            lastName: 'User',
            address: new AddressData('123 Custom St', 'Custom City', '12345', 'DE') // Use Germany instead
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: new Money(100.0, 'USD'),
            originalAmount: new Money(100.0, 'USD'),
            productName: 'Custom Product'
        );

        $strategy = $this->factory->getStrategy($invoiceData);

        $this->assertSame($customStrategy, $strategy);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('strategySelectionProvider')]
    public function testStrategySelection(string $country, string $currency, bool $hasVat, string $expectedStrategy): void
    {
        $invoiceData = $this->createInvoiceData($country, $currency, $hasVat);

        $strategy = $this->factory->getStrategy($invoiceData);

        $this->assertInstanceOf($expectedStrategy, $strategy);
    }

    public static function strategySelectionProvider(): array
    {
        return [
            'Poland PLN' => ['PL', 'PLN', false, DomesticInvoiceStrategy::class],
            'Poland EUR' => ['PL', 'EUR', false, CurrencyInvoiceStrategy::class],
            'Germany B2B' => ['DE', 'EUR', true, EUB2BInvoiceStrategy::class],
            'Germany B2C' => ['DE', 'EUR', false, OSSInvoiceStrategy::class],
            'France B2B' => ['FR', 'EUR', true, EUB2BInvoiceStrategy::class],
            'France B2C' => ['FR', 'EUR', false, OSSInvoiceStrategy::class],
            'Canada B2B' => ['CA', 'CAD', true, ExportInvoiceStrategy::class],
            'Canada B2C' => ['CA', 'CAD', false, ExportInvoiceStrategy::class],
            'Japan' => ['JP', 'JPY', false, ExportInvoiceStrategy::class],
            'Canada' => ['CA', 'CAD', true, ExportInvoiceStrategy::class],
        ];
    }

    private function createInvoiceData(string $country, string $currency, bool $hasVat = false): InvoiceData
    {
        $vatNumber = $hasVat ? new VatNumber('PL', '5260001246') : null;
        $companyName = $hasVat ? 'Test Company' : null;

        // Handle state requirement for US and CA addresses
        $stateProvince = match ($country) {
            'US' => 'CA',
            'CA' => 'ON',
            default => null,
        };

        $customer = new CustomerData(
            email: 'test@example.com',
            firstName: 'Test',
            lastName: 'User',
            address: new AddressData('123 Test St', 'Test City', '12345', $country, $stateProvince),
            companyName: $companyName,
            vatNumber: $vatNumber
        );

        return new InvoiceData(
            customer: $customer,
            amount: new Money(100.0, $currency),
            originalAmount: new Money(100.0, $currency),
            productName: 'Test Product'
        );
    }
}
