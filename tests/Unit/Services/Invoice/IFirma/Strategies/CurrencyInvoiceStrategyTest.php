<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\Services\Invoice\IFirma\Strategies;

use DjWeb\Payments\DTOs\CustomerData;
use DjWeb\Payments\DTOs\InvoiceData;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\CurrencyInvoiceStrategy;
use DjWeb\Payments\DTOs\AddressData;
use DjWeb\Payments\ValueObjects\Country;
use DjWeb\Payments\ValueObjects\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CurrencyInvoiceStrategyTest extends TestCase
{
    private CurrencyInvoiceStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new CurrencyInvoiceStrategy();
    }

    public function testGetEndpoint(): void
    {
        $this->assertSame('/fakturawaluta.json', $this->strategy->getEndpoint());
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
        $usdMoney = new Money(100.00, 'USD');
        $poland = new Country('PL', 'Poland', true, 0.23);
        $germany = new Country('DE', 'Germany', true, 0.19);

        yield 'PL country with EUR currency - supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'John',
                    'Doe',
                    'john@example.com',
                    new AddressData('123 Test St', 'Warsaw', '00-001', "PL"),
                ),
                amount: $eurMoney,
                originalAmount: $eurMoney,
                productName: 'Test Product',
            ),
            true,
        ];

        yield 'PL country with USD currency - supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'John',
                    'Doe',
                    'john@example.com',
                    new AddressData('123 Test St', 'Warsaw', '00-001', "PL"),
                ),
                amount: $usdMoney,
                originalAmount: $usdMoney,
                productName: 'Test Product',
            ),
            true,
        ];

        yield 'PL country with PLN currency - not supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'John',
                    'Doe',
                    'john@example.com',
                    new AddressData('123 Test St', 'Warsaw', '00-001', "PL"),
                ),
                amount: $plnMoney,
                originalAmount: $plnMoney,
                productName: 'Test Product',
            ),
            false,
        ];

        yield 'Non-PL country with EUR currency - not supported' => [
            new InvoiceData(
                customer: new CustomerData(
                    'John',
                    'Doe',
                    'john@example.com',
                    new AddressData('123 Test St', 'Berlin', '10115', "DE"),
                ),
                amount: $eurMoney,
                originalAmount: $eurMoney,
                productName: 'Test Product',
            ),
            false,
        ];
    }

    public function testPrepareInvoiceData(): void
    {
        $poland = new Country('PL', 'Poland', true, 0.23);
        $eurMoney = new Money(123.45, 'EUR');
        
        $customer = new CustomerData(
            'John',
            'Doe',
            'john@example.com',
            new AddressData('ul. Testowa 123', 'Warsaw', '00-001', "PL"),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $eurMoney,
            originalAmount: $eurMoney,
            productName: 'Test Product',
        );

        $result = $this->strategy->prepareInvoiceData($invoiceData);

        // Should contain all domestic strategy data
        $this->assertSame(123.45, $result['Zaplacono']);
        $this->assertSame('BRT', $result['LiczOd']);
        $this->assertFalse($result['SplitPayment']);
        
        // Plus currency-specific fields
        $this->assertSame('KRAJOWA', $result['TypSprzedazy']);
        $this->assertSame('EUR', $result['Waluta']);

        // Should have customer and position data from domestic strategy
        $this->assertArrayHasKey('Kontrahent', $result);
        $this->assertArrayHasKey('Pozycje', $result);
        
        $kontrahent = $result['Kontrahent'];
        $this->assertSame('John Doe', $kontrahent['Nazwa']);
        $this->assertSame('PL', $kontrahent['KodKraju']);

        $pozycje = $result['Pozycje'];
        $this->assertCount(1, $pozycje);
        $position = $pozycje[0];
        $this->assertSame(123.45, $position['CenaJednostkowa']);
        $this->assertSame('Test Product', $position['NazwaPelna']);
    }

    public function testPrepareInvoiceDataWithDifferentCurrencies(): void
    {
        $poland = new Country('PL', 'Poland', true, 0.23);
        $customer = new CustomerData(
            'John',
            'Doe',
            'john@example.com',
            new AddressData('ul. Testowa 123', 'Warsaw', '00-001', "PL"),
        );

        $currencies = ['EUR', 'USD', 'GBP', 'CHF'];

        foreach ($currencies as $currency) {
            $money = new Money(100.00, $currency);
            $invoiceData = new InvoiceData(
                customer: $customer,
                amount: $money,
                originalAmount: $money,
                productName: 'Test Product',
            );

            $result = $this->strategy->prepareInvoiceData($invoiceData);
            
            $this->assertSame($currency, $result['Waluta'], "Failed for currency: {$currency}");
            $this->assertSame('KRAJOWA', $result['TypSprzedazy'], "Failed for currency: {$currency}");
        }
    }
}