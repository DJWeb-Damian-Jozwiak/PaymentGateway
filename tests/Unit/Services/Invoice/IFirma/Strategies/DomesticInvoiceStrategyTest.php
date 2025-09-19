<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\Services\Invoice\IFirma\Strategies;

use DjWeb\Payments\DTOs\CustomerData;
use DjWeb\Payments\DTOs\DiscountData;
use DjWeb\Payments\DTOs\InvoiceData;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\DomesticInvoiceStrategy;
use DjWeb\Payments\DTOs\AddressData;
use DjWeb\Payments\ValueObjects\Country;
use DjWeb\Payments\ValueObjects\Money;
use DjWeb\Payments\ValueObjects\VatNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DomesticInvoiceStrategyTest extends TestCase
{
    private DomesticInvoiceStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new DomesticInvoiceStrategy();
    }

    public function testGetEndpoint(): void
    {
        $this->assertSame('/fakturakraj.json', $this->strategy->getEndpoint());
    }

    #[DataProvider('supportedDataProvider')]
    public function testSupports(InvoiceData $data, bool $expected): void
    {
        $this->assertSame($expected, $this->strategy->supports($data));
    }

    public static function supportedDataProvider(): iterable
    {
        $plnMoney = new Money(100.00, 'PLN');
        $eurMoney = new Money(100.00, 'EUR');
        $poland = new Country('PL', 'Poland', true, 0.23);
        $germany = new Country('DE', 'Germany', true, 0.19);

        yield 'PL country with PLN currency - supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'John',
                    'Doe',
                    'john@example.com',
                    new AddressData('123 Test St', 'Warsaw', '00-001', 'PL'),
                ),
                amount: $plnMoney,
                originalAmount: $plnMoney,
                productName: 'Test Product',
            ),
            true,
        ];

        yield 'PL country with EUR currency - not supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'John',
                    'Doe',
                    'john@example.com',
                    new AddressData('123 Test St', 'Warsaw', '00-001', 'PL'),
                ),
                amount: $eurMoney,
                originalAmount: $eurMoney,
                productName: 'Test Product',
            ),
            false,
        ];

        yield 'Non-PL country - not supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'John',
                    'Doe',
                    'john@example.com',
                    new AddressData('123 Test St', 'Berlin', '10115', 'DE'),
                ),
                amount: $plnMoney,
                originalAmount: $plnMoney,
                productName: 'Test Product',
            ),
            false,
        ];
    }

    public function testPrepareInvoiceDataForIndividualCustomer(): void
    {
        $poland = new Country('PL', 'Poland', true, 0.23);
        $money = new Money(123.45, 'PLN');

        $customer = new CustomerData(
            'John',
            'Doe',
            'john@example.com',
            new AddressData('ul. Testowa 123', 'Warsaw', '00-001', 'PL'),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Test Product',
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        $this->assertSame(123.45, $result['Zaplacono']);
        $this->assertSame('BRT', $result['LiczOd']);
        $this->assertFalse($result['SplitPayment']);
        $this->assertSame(date('Y-m-d'), $result['DataWystawienia']);
        $this->assertSame(date('Y-m-d'), $result['DataSprzedazy']);
        $this->assertSame('DZN', $result['FormatDatySprzedazy']);
        $this->assertSame(date('Y-m-d'), $result['TerminPlatnosci']);
        $this->assertSame('PRZ', $result['SposobZaplaty']);
        $this->assertSame('BWO', $result['RodzajPodpisuOdbiorcy']);
        $this->assertFalse($result['WidocznyNumerGios']);

        // Check customer data
        $kontrahent = $result['Kontrahent'];
        $this->assertSame('John Doe', $kontrahent['Nazwa']);
        $this->assertSame('ul. Testowa 123', $kontrahent['Ulica']);
        $this->assertSame('Warsaw', $kontrahent['Miejscowosc']);
        $this->assertSame('00-001', $kontrahent['KodPocztowy']);
        $this->assertSame('PL', $kontrahent['KodKraju']);
        $this->assertSame('john@example.com', $kontrahent['Email']);
        $this->assertTrue($kontrahent['OsobaFizyczna']);
        $this->assertArrayNotHasKey('NIP', $kontrahent);

        // Check position
        $pozycje = $result['Pozycje'];
        $this->assertCount(1, $pozycje);
        $position = $pozycje[0];
        $this->assertSame(0.23, $position['StawkaVat']);
        $this->assertSame(1, $position['Ilosc']);
        $this->assertSame(123.45, $position['CenaJednostkowa']);
        $this->assertSame('Test Product', $position['NazwaPelna']);
        $this->assertSame('szt.', $position['Jednostka']);
        $this->assertSame('PRC', $position['TypStawkiVat']);
    }

    public function testPrepareInvoiceDataForBusinessCustomer(): void
    {
        $poland = new Country('PL', 'Poland', true, 0.23);
        $money = new Money(100.00, 'PLN');
        $vatNumber = new VatNumber('PL', '5260001246');

        $customer = new CustomerData(
            'John',
            'Doe',
            'john@company.com',
            new AddressData('ul. Biznesowa 456', 'Krakow', '30-001', 'PL'),
            companyName: 'Test Company',
            vatNumber: $vatNumber,
        );

        $discount = new DiscountData('SAVE10', 10.0);

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: new Money(111.11, 'PLN'),
            productName: 'Business Service',
            discount: $discount,
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        // Check customer data for business
        $kontrahent = $result['Kontrahent'];
        $this->assertSame('Test Company', $kontrahent['Nazwa']);
        $this->assertSame('5260001246', $kontrahent['NIP']);
        $this->assertFalse($kontrahent['OsobaFizyczna']);

        // Check position with discount
        $position = $result['Pozycje'][0];
        $this->assertSame(111.11, $position['CenaJednostkowa']);
        $this->assertSame(10.0, $position['Rabat']);
    }

    #[DataProvider('paymentMethodProvider')]
    public function testPaymentMethodMapping(string $method, string $expected): void
    {
        $poland = new Country('PL', 'Poland', true, 0.23);
        $money = new Money(100.00, 'PLN');

        $customer = new CustomerData(
            'John',
            'Doe',
            'john@example.com',
            new AddressData('ul. Testowa 123', 'Warsaw', '00-001', 'PL'),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Test Product',
            paymentMethod: $method,
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);
        $this->assertSame($expected, $result['SposobZaplaty']);
    }

    public static function paymentMethodProvider(): iterable
    {
        yield ['cash', 'GTK'];
        yield ['card', 'KAR'];
        yield ['paypal', 'PAL'];
        yield ['p24', 'P24'];
        yield ['transfer', 'PRZ'];
        yield ['unknown', 'PRZ'];
    }

    public function testPrepareInvoiceDataWithCustomDates(): void
    {
        $poland = new Country('PL', 'Poland', true, 0.23);
        $money = new Money(100.00, 'PLN');

        $customer = new CustomerData(
            'John',
            'Doe',
            'john@example.com',
            new AddressData('ul. Testowa 123', 'Warsaw', '00-001', 'PL'),
        );

        $issueDate = new \DateTimeImmutable('2024-01-15');
        $saleDate = new \DateTimeImmutable('2024-01-14');

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Test Product',
            issueDate: $issueDate,
            saleDate: $saleDate,
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        $this->assertSame('2024-01-15', $result['DataWystawienia']);
        $this->assertSame('2024-01-14', $result['DataSprzedazy']);
    }
}
