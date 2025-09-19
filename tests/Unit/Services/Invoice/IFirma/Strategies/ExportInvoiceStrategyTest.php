<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\Services\Invoice\IFirma\Strategies;

use DjWeb\Payments\DTOs\CustomerData;
use DjWeb\Payments\DTOs\InvoiceData;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\ExportInvoiceStrategy;
use DjWeb\Payments\DTOs\AddressData;
use DjWeb\Payments\ValueObjects\Country;
use DjWeb\Payments\ValueObjects\Money;
use DjWeb\Payments\ValueObjects\VatNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExportInvoiceStrategyTest extends TestCase
{
    private ExportInvoiceStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new ExportInvoiceStrategy();
    }

    public function testGetEndpoint(): void
    {
        $this->assertSame('/fakturaeksportuslug.json', $this->strategy->getEndpoint());
    }

    #[DataProvider('supportedDataProvider')]
    public function testSupports(InvoiceData $data, bool $expected): void
    {
        $this->assertSame($expected, $this->strategy->supports($data));
    }

    public static function supportedDataProvider(): iterable
    {
        $money = new Money(100.00, 'USD');
        $poland = new Country('PL', 'Poland', true, 0.23);
        $germany = new Country('DE', 'Germany', true, 0.19);
        $usa = new Country('US', 'United States', false, 0.0);
        $canada = new Country('CA', 'Canada', false, 0.0);
        $japan = new Country('JP', 'Japan', false, 0.0);

        // Non-EU countries - supported
        yield 'US customer - supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'John',
                    'Smith',
                    'john@example.com',
                    new AddressData('123 Main St', 'New York', '10001', "US", stateProvince: 'NY'),
                ),
                amount: $money,
                originalAmount: $money,
                productName: 'Test Product',
            ),
            true,
        ];

        yield 'Canadian customer - supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'Jane',
                    'Doe',
                    'jane@example.com',
                    new AddressData('456 Maple Ave', 'Toronto', 'M5V 3A8', 'CA', stateProvince: 'ON'),
                ),
                amount: $money,
                originalAmount: $money,
                productName: 'Test Product',
            ),
            true,
        ];

        yield 'Japanese customer - supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'Yuki',
                    'Tanaka',
                    'yuki@example.com',
                    new AddressData('123 Tokyo St', 'Tokyo', '100-0001', 'JP'),
                ),
                amount: $money,
                originalAmount: $money,
                productName: 'Test Product',
            ),
            true,
        ];

        // EU countries - not supported
        yield 'German customer - not supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'Hans',
                    'Mueller',
                    'hans@example.com',
                    new AddressData('123 Test St', 'Berlin', '10115', "DE"),
                ),
                amount: $money,
                originalAmount: $money,
                productName: 'Test Product',
            ),
            false,
        ];

        // Polish customer - not supported
        yield 'Polish customer - not supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'Jan',
                    'Kowalski',
                    'jan@example.com',
                    new AddressData('ul. Testowa 123', 'Warsaw', '00-001', "PL"),
                ),
                amount: $money,
                originalAmount: $money,
                productName: 'Test Product',
            ),
            false,
        ];
    }

    public function testPrepareInvoiceDataForUSCustomer(): void
    {
        $usa = new Country('US', 'United States', false, 0.0);
        $money = new Money(1500.00, 'USD');
        
        $customer = new CustomerData(
            'John',
            'Smith',
            'john@example.com',
            new AddressData('123 Main Street', 'New York', '10001', 'US', stateProvince: 'NY'),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Software Consulting',
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        // Check basic invoice structure
        $this->assertSame('Software Consulting', $result['NazwaUslugi']);
        $this->assertFalse($result['UslugaSwiadczonaTrybArt28b']);
        $this->assertSame(date('Y-m-d'), $result['DataWystawienia']);
        $this->assertSame(date('Y-m-d'), $result['DataSprzedazy']);
        $this->assertSame('DZN', $result['FormatDatySprzedazy']);
        $this->assertSame(date('Y-m-d'), $result['DataObowiazkuPodatkowego']);
        $this->assertSame('PRZ', $result['SposobZaplaty']);

        // Check customer data
        $kontrahent = $result['Kontrahent'];
        $this->assertSame('John Smith', $kontrahent['Nazwa']);
        $this->assertSame('123 Main Street', $kontrahent['Ulica']);
        $this->assertSame('10001', $kontrahent['KodPocztowy']);
        $this->assertSame('United States of America', $kontrahent['Kraj']);
        $this->assertSame('New York', $kontrahent['Miejscowosc']);
        $this->assertSame('john@example.com', $kontrahent['Email']);
        $this->assertSame('NY', $kontrahent['Wojewodztwo']);
    }

    public function testPrepareInvoiceDataForBusinessCustomer(): void
    {
        $canada = new Country('CA', 'Canada', false, 0.0);
        $money = new Money(2000.00, 'CAD');
        
        $customer = new CustomerData(
            'Jane',
            'Doe',
            'jane@company.com',
            new AddressData('456 Business Ave', 'Toronto', 'M5V 3A8', 'CA', stateProvince: 'ON'),
            companyName: 'Tech Solutions Inc.',
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Web Development Services',
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        $kontrahent = $result['Kontrahent'];
        $this->assertSame('Tech Solutions Inc.', $kontrahent['Nazwa']);
        $this->assertSame('jane@company.com', $kontrahent['Email']);
    }

    public function testPrepareInvoiceDataWithoutStateProvince(): void
    {
        $japan = new Country('JP', 'Japan', false, 0.0);
        $money = new Money(800.00, 'JPY');
        
        $customer = new CustomerData(
            'Yuki',
            'Tanaka',
            'yuki@example.com',
            new AddressData('123 Tokyo Street', 'Tokyo', '100-0001', 'JP'),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Digital Marketing',
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        $kontrahent = $result['Kontrahent'];
        $this->assertArrayNotHasKey('Wojewodztwo', $kontrahent);
        $this->assertSame('Japan', $kontrahent['Kraj']);
    }

    public function testPrepareInvoiceDataWithCustomDates(): void
    {
        $australia = new Country('AU', 'Australia', false, 0.0);
        $money = new Money(1200.00, 'AUD');
        
        $customer = new CustomerData(
            'Sarah',
            'Wilson',
            'sarah@example.com',
            new AddressData('789 Sydney Road', 'Sydney', '2000', 'AU', stateProvince: 'NSW'),
        );

        $issueDate = new \DateTimeImmutable('2024-02-15');
        $saleDate = new \DateTimeImmutable('2024-02-14');

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Cloud Services',
            issueDate: $issueDate,
            saleDate: $saleDate,
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        $this->assertSame('2024-02-15', $result['DataWystawienia']);
        $this->assertSame('2024-02-14', $result['DataSprzedazy']);
        $this->assertSame(date('Y-m-d'), $result['DataObowiazkuPodatkowego']);
    }

    public function testUslugaSwiadczonaTrybArt28bIsFalse(): void
    {
        $brazil = new Country('BR', 'Brazil', false, 0.0);
        $money = new Money(500.00, 'BRL');
        
        $customer = new CustomerData(
            'Carlos',
            'Silva',
            'carlos@example.com',
            new AddressData('Rua Test 123', 'São Paulo', '01000-000', 'BR', stateProvince: 'SP'),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'SEO Services',
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        // For non-EU customers, UslugaSwiadczonaTrybArt28b should always be false
        $this->assertFalse($result['UslugaSwiadczonaTrybArt28b']);
    }

    public function testPrepareInvoiceDataFallbackToFullName(): void
    {
        $mexico = new Country('MX', 'Mexico', false, 0.0);
        $money = new Money(300.00, 'MXN');
        
        $customer = new CustomerData(
            'Maria',
            'Rodriguez',
            'maria@freelancer.com',
            new AddressData('Calle Test 456', 'Mexico City', '03100', 'MX', stateProvince: 'CDMX'),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Translation Services',
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        $kontrahent = $result['Kontrahent'];
        $this->assertSame('Maria Rodriguez', $kontrahent['Nazwa']);
        $this->assertSame('Mexico', $kontrahent['Kraj']);
    }
}