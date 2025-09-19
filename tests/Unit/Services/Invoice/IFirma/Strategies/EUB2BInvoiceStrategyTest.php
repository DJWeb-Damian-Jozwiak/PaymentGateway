<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\Services\Invoice\IFirma\Strategies;

use DjWeb\Payments\DTOs\CustomerData;
use DjWeb\Payments\DTOs\InvoiceData;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\EUB2BInvoiceStrategy;
use DjWeb\Payments\DTOs\AddressData;
use DjWeb\Payments\ValueObjects\Country;
use DjWeb\Payments\ValueObjects\Money;
use DjWeb\Payments\ValueObjects\VatNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EUB2BInvoiceStrategyTest extends TestCase
{
    private EUB2BInvoiceStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new EUB2BInvoiceStrategy();
    }

    public function testGetEndpoint(): void
    {
        $this->assertSame('/fakturaeksportuslugue.json', $this->strategy->getEndpoint());
    }

    #[DataProvider('supportedDataProvider')]
    public function testSupports(InvoiceData $data, bool $expected): void
    {
        $this->assertSame($expected, $this->strategy->supports($data));
    }

    public static function supportedDataProvider(): iterable
    {
        $money = new Money(100.00, 'EUR');
        $poland = new Country('PL', 'Poland', true, 0.23);
        $germany = new Country('DE', 'Germany', true, 0.19);
        $usa = new Country('US', 'United States', false, 0.0);
        $vatNumber = new VatNumber( 'DE', '123456789');

        // EU B2B customer - supported
        yield 'EU B2B customer with VAT - supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'John',
                    'Doe',
                    'john@company.com',
                    new AddressData('123 Test St', 'Berlin', '10115', "DE"),
                    companyName: 'Test GmbH',
                    vatNumber: $vatNumber,
                ),
                amount: $money,
                originalAmount: $money,
                productName: 'Test Product',
            ),
            true,
        ];

        // EU B2B customer with company name but no VAT - supported
        yield 'EU B2B customer with company name - supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'John',
                    'Doe',
                    'john@company.com',
                    new AddressData('123 Test St', 'Berlin', '10115', "DE"),
                    companyName: 'Test GmbH',
                ),
                amount: $money,
                originalAmount: $money,
                productName: 'Test Product',
            ),
            true,
        ];

        // EU B2C customer (individual) - not supported
        yield 'EU B2C customer - not supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'John',
                    'Doe',
                    'john@example.com',
                    new AddressData('123 Test St', 'Berlin', '10115', "DE"),
                ),
                amount: $money,
                originalAmount: $money,
                productName: 'Test Product',
            ),
            false,
        ];

        // Polish B2B customer - not supported
        yield 'Polish B2B customer - not supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'Jan',
                    'Kowalski',
                    'jan@company.com',
                    new AddressData('ul. Testowa 123', 'Warsaw', '00-001', "PL"),
                    companyName: 'Test Sp. z o.o.',
                    vatNumber: new VatNumber('PL','5260001246'),
                ),
                amount: $money,
                originalAmount: $money,
                productName: 'Test Product',
            ),
            false,
        ];

        // Non-EU B2B customer - not supported
        yield 'Non-EU B2B customer - not supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'John',
                    'Smith',
                    'john@company.com',
                    new AddressData('123 Main St', 'New York', '10001', "US", stateProvince: 'NY'),
                    companyName: 'Test Inc.',
                ),
                amount: $money,
                originalAmount: $money,
                productName: 'Test Product',
            ),
            false,
        ];
    }

    public function testPrepareInvoiceDataWithVatNumber(): void
    {
        $germany = new Country('DE', 'Germany', true, 0.19);
        $money = new Money(1000.00, 'EUR');
        $vatNumber = new VatNumber('DE','123456789');

        $customer = new CustomerData(
            'Hans',
            'Mueller',
            'hans@company.com',
            new AddressData('Teststraße 123', 'Berlin', '10115', 'DE', stateProvince: 'Berlin'),
            companyName: 'Test GmbH',
            vatNumber: $vatNumber,
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Consulting Services',
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        // Check basic invoice structure
        $this->assertSame('Consulting Services', $result['NazwaUslugi']);
        $this->assertSame(1000.00, $result['Zaplacono']);
        $this->assertSame(date('Y-m-d'), $result['DataWystawienia']);
        $this->assertSame(date('Y-m-d'), $result['DataSprzedazy']);
        $this->assertSame('DZN', $result['FormatDatySprzedazy']);
        $this->assertSame(date('Y-m-d'), $result['DataObowiazkuPodatkowego']);
        $this->assertSame('PRZ', $result['SposobZaplaty']);

        // Check customer data
        $kontrahent = $result['Kontrahent'];
        $this->assertSame('Test GmbH', $kontrahent['Nazwa']);
        $this->assertSame('Teststraße 123', $kontrahent['Ulica']);
        $this->assertSame('10115', $kontrahent['KodPocztowy']);
        $this->assertSame('DE', $kontrahent['KodKraju']);
        $this->assertSame('Berlin', $kontrahent['Miejscowosc']);
        $this->assertSame('hans@company.com', $kontrahent['Email']);
        $this->assertFalse($kontrahent['OsobaFizyczna']);
        $this->assertSame('DE123456789', $kontrahent['NIP']);
        $this->assertSame('Berlin', $kontrahent['Wojewodztwo']);
    }

    public function testPrepareInvoiceDataWithoutVatNumber(): void
    {
        $france = new Country('FR', 'France', true, 0.20);
        $money = new Money(500.00, 'EUR');

        $customer = new CustomerData(
            'Pierre',
            'Dupont',
            'pierre@company.com',
            new AddressData('123 Rue de Test', 'Paris', '75001', 'FR'),
            companyName: 'Test SARL',
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Web Development',
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        $kontrahent = $result['Kontrahent'];
        $this->assertSame('Test SARL', $kontrahent['Nazwa']);
        $this->assertArrayNotHasKey('NIP', $kontrahent);
        $this->assertArrayNotHasKey('Wojewodztwo', $kontrahent);
        $this->assertFalse($kontrahent['OsobaFizyczna']);
    }

    public function testPrepareInvoiceDataFallbackToFullName(): void
    {
        $italy = new Country('IT', 'Italy', true, 0.22);
        $money = new Money(750.00, 'EUR');

        $customer = new CustomerData(
            'Marco',
            'Rossi',
            'marco@business.com',
            new AddressData('Via Test 123', 'Rome', '00100', 'IT'),
            vatNumber: new VatNumber('IT', '12345678901'),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Digital Marketing',
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        $kontrahent = $result['Kontrahent'];
        $this->assertSame('Marco Rossi', $kontrahent['Nazwa']);
        $this->assertSame('IT12345678901', $kontrahent['NIP']);
    }

    public function testPrepareInvoiceDataWithCustomDates(): void
    {
        $netherlands = new Country('NL', 'Netherlands', true, 0.21);
        $money = new Money(200.00, 'EUR');

        $customer = new CustomerData(
            'Jan',
            'van der Berg',
            'jan@company.com',
            new AddressData('Teststraat 123', 'Amsterdam', '1000 AA', 'NL'),
            companyName: 'Test B.V.',
        );

        $issueDate = new \DateTimeImmutable('2024-01-15');
        $saleDate = new \DateTimeImmutable('2024-01-14');

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Software License',
            issueDate: $issueDate,
            saleDate: $saleDate,
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        $this->assertSame('2024-01-15', $result['DataWystawienia']);
        $this->assertSame('2024-01-14', $result['DataSprzedazy']);
        $this->assertSame(date('Y-m-d'), $result['DataObowiazkuPodatkowego']);
    }

    public function testPrepareInvoiceDataWithStateProvince(): void
    {
        $spain = new Country('ES', 'Spain', true, 0.21);
        $money = new Money(300.00, 'EUR');

        $customer = new CustomerData(
            'Carlos',
            'Garcia',
            'carlos@empresa.com',
            new AddressData('Calle Test 123', 'Madrid', '28001', 'ES', stateProvince: 'Madrid'),
            companyName: 'Test S.L.',
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Training Services',
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        $kontrahent = $result['Kontrahent'];
        $this->assertSame('Madrid', $kontrahent['Wojewodztwo']);
    }
}
