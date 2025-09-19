<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\Services\Invoice\IFirma\Strategies;

use DjWeb\Payments\DTOs\CustomerData;
use DjWeb\Payments\DTOs\DiscountData;
use DjWeb\Payments\DTOs\InvoiceData;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\OSSInvoiceStrategy;
use DjWeb\Payments\DTOs\AddressData;
use DjWeb\Payments\ValueObjects\Country;
use DjWeb\Payments\ValueObjects\Money;
use DjWeb\Payments\ValueObjects\VatNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OSSInvoiceStrategyTest extends TestCase
{
    private OSSInvoiceStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new OSSInvoiceStrategy();
    }

    public function testGetEndpoint(): void
    {
        $this->assertSame('/fakturaoss.json', $this->strategy->getEndpoint());
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

        // EU B2C customer (individual) - supported
        yield 'EU B2C customer - supported' => [
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
            true,
        ];

        // EU B2B customer - not supported (should use EUB2BInvoiceStrategy)
        yield 'EU B2B customer - not supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'John',
                    'Doe',
                    'john@company.com',
                    new AddressData('123 Test St', 'Berlin', '10115', "DE"),
                    companyName: 'Test Company',
                    vatNumber: new VatNumber( 'DE', '123456789'),
                ),
                amount: $money,
                originalAmount: $money,
                productName: 'Test Product',
            ),
            false,
        ];

        // Polish customer - not supported (should use other strategies)
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

        // Non-EU customer - not supported (should use ExportInvoiceStrategy)
        yield 'Non-EU customer - not supported' => [
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
            false,
        ];
    }

    public function testPrepareInvoiceDataForGermanCustomer(): void
    {
        $germany = new Country('DE', 'Germany', true, 0.19);
        $money = new Money(123.45, 'EUR');

        $customer = new CustomerData(
            'Hans',
            'Mueller',
            'hans@example.com',
            new AddressData('Teststraße 123', 'Berlin', '10115', "DE"),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Digital Service',
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        // Check basic invoice structure
        $this->assertSame(date('Y-m-d'), $result['DataSprzedazy']);
        $this->assertSame('DZN', $result['FormatDatySprzedazy']);
        $this->assertSame(date('Y-m-d'), $result['DataWystawienia']);
        $this->assertSame('de', $result['Jezyk']);
        $this->assertSame('EUR', $result['Waluta']);
        $this->assertSame('BRT', $result['LiczOd']);
        $this->assertSame('BWO', $result['RodzajPodpisuOdbiorcy']);
        $this->assertFalse($result['WidocznyNumerBdo']);
        $this->assertTrue($result['SprzedazUslug']);
        $this->assertSame('IP', $result['UstalenieMiejscaUslugi1']);
        $this->assertSame('BillingAddress', $result['UstalenieMiejscaUslugi2']);
        $this->assertSame('DE', $result['KrajDostawy']);
        $this->assertSame('PL', $result['KrajWysylki']);

        // Check customer data
        $kontrahent = $result['Kontrahent'];
        $this->assertSame('Hans Mueller', $kontrahent['Nazwa']);
        $this->assertSame('Germany', $kontrahent['Kraj']);
        $this->assertSame('Berlin', $kontrahent['Miejscowosc']);
        $this->assertSame('10115', $kontrahent['KodPocztowy']);
        $this->assertSame('Teststraße 123', $kontrahent['Ulica']);
        $this->assertSame('hans@example.com', $kontrahent['Email']);

        // Check position
        $pozycje = $result['Pozycje'];
        $this->assertCount(1, $pozycje);
        $position = $pozycje[0];
        $this->assertSame('Digital Service', $position['NazwaPelna']);
        $this->assertSame('Digital Service', $position['NazwaPelnaObca']);
        $this->assertSame('szt.', $position['Jednostka']);
        $this->assertSame('pcs', $position['JednostkaObca']);
        $this->assertSame(123.45, $position['CenaJednostkowa']);
        $this->assertSame(1, $position['Ilosc']);
        $this->assertSame(0.19, $position['StawkaVat']);
        $this->assertSame('POD', $position['TypStawkiVat']);
    }

    public function testPrepareInvoiceDataWithDiscount(): void
    {
        $france = new Country('FR', 'France', true, 0.20);
        $money = new Money(90.00, 'EUR');
        $originalMoney = new Money(100.00, 'EUR');

        $customer = new CustomerData(
            'Pierre',
            'Dupont',
            'pierre@example.com',
            new AddressData('123 Rue de Test', 'Paris', '75001', 'FR'),
        );

        $discount = new DiscountData('SAVE10', 10.0);

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $originalMoney,
            productName: 'Premium Service',
            discount: $discount,
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        $this->assertSame('fr', $result['Jezyk']);
        $this->assertSame('FR', $result['KrajDostawy']);

        $position = $result['Pozycje'][0];
        $this->assertSame(100.00, $position['CenaJednostkowa']);
        $this->assertSame(10.0, $position['Rabat']);
        $this->assertSame(0.20, $position['StawkaVat']);
    }

    public function testPrepareInvoiceDataWithoutDiscount(): void
    {
        $italy = new Country('IT', 'Italy', true, 0.22);
        $money = new Money(100.00, 'EUR');

        $customer = new CustomerData(
            'Marco',
            'Rossi',
            'marco@example.com',
            new AddressData('Via Test 123', 'Rome', '00100', 'IT'),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Basic Service',
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        $position = $result['Pozycje'][0];
        $this->assertArrayNotHasKey('Rabat', $position);
        $this->assertSame(0.22, $position['StawkaVat']);
        $this->assertSame('it', $result['Jezyk']);
    }

    #[DataProvider('languageProvider')]
    public function testGetLanguageForCountry(string $countryCode, string $expectedLanguage): void
    {
        $country = new Country($countryCode, 'Test Country', true, 0.20);
        $money = new Money(100.00, 'EUR');

        $customer = new CustomerData(
            'Test',
            'User',
            'test@example.com',
            new AddressData('Test St 123', 'Test City', '12345', $countryCode),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Test Product',
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);
        $this->assertSame($expectedLanguage, $result['Jezyk']);
    }

    public static function languageProvider(): iterable
    {
        yield ['DE', 'de'];
        yield ['AT', 'de'];
        yield ['FR', 'fr'];
        yield ['BE', 'fr'];
        yield ['LU', 'fr'];
        yield ['ES', 'es'];
        yield ['IT', 'it'];
        yield ['NL', 'nl'];
        yield ['SE', 'sv'];
        yield ['DK', 'da'];
        yield ['FI', 'fi'];
        yield ['PL', 'pl'];
        yield ['CZ', 'en'];
        yield ['HU', 'en'];
        yield ['SK', 'en'];
    }

    public function testPrepareInvoiceDataWithCustomDates(): void
    {
        $germany = new Country('DE', 'Germany', true, 0.19);
        $money = new Money(100.00, 'EUR');

        $customer = new CustomerData(
            'Hans',
            'Mueller',
            'hans@example.com',
            new AddressData('Teststraße 123', 'Berlin', '10115', "DE"),
        );

        $issueDate = new \DateTimeImmutable('2024-01-15');
        $saleDate = new \DateTimeImmutable('2024-01-14');

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Digital Service',
            issueDate: $issueDate,
            saleDate: $saleDate,
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        $this->assertSame('2024-01-15', $result['DataWystawienia']);
        $this->assertSame('2024-01-14', $result['DataSprzedazy']);
    }
}
