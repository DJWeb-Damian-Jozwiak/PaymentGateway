<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\Services\Invoice\IFirma\Strategies;

use DjWeb\Payments\DTOs\CustomerData;
use DjWeb\Payments\DTOs\InvoiceData;
use DjWeb\Payments\Exceptions\InvoiceError;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\CurrencyInvoiceStrategy;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\DomesticInvoiceStrategy;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\EUB2BInvoiceStrategy;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\ExportInvoiceStrategy;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\InvoiceStrategyContract;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\InvoiceStrategyFactory;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\OSSInvoiceStrategy;
use DjWeb\Payments\DTOs\AddressData;
use DjWeb\Payments\ValueObjects\Country;
use DjWeb\Payments\ValueObjects\Money;
use DjWeb\Payments\ValueObjects\VatNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvoiceStrategyFactoryTest extends TestCase
{
    private InvoiceStrategyFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new InvoiceStrategyFactory();
    }

    #[DataProvider('strategySelectionProvider')]
    public function testGetStrategy(InvoiceData $data, string $expectedStrategyClass): void
    {
        $strategy = $this->factory->getStrategy($data);
        $this->assertInstanceOf($expectedStrategyClass, $strategy);
    }

    public static function strategySelectionProvider(): iterable
    {
        $poland = new Country('PL', 'Poland', true, 0.23);
        $germany = new Country('DE', 'Germany', true, 0.19);
        $usa = new Country('US', 'United States', false, 0.0);

        // Polish customer with foreign currency - CurrencyInvoiceStrategy
        yield 'Polish customer with EUR - CurrencyInvoiceStrategy' => [
            new InvoiceData(
                customer: new CustomerData(
                    'Jan',
                    'Kowalski',
                    'jan@example.com',
                    new AddressData('ul. Testowa 123', 'Warsaw', '00-001', "PL"),
                ),
                amount: new Money(100.00, 'EUR'),
                originalAmount: new Money(100.00, 'EUR'),
                productName: 'Test Product',
            ),
            CurrencyInvoiceStrategy::class,
        ];

        // Polish customer with PLN - DomesticInvoiceStrategy
        yield 'Polish customer with PLN - DomesticInvoiceStrategy' => [
            new InvoiceData(
                customer: new CustomerData(
                    'Jan',
                    'Kowalski',
                    'jan@example.com',
                    new AddressData('ul. Testowa 123', 'Warsaw', '00-001', "PL"),
                ),
                amount: new Money(100.00, 'PLN'),
                originalAmount: new Money(100.00, 'PLN'),
                productName: 'Test Product',
            ),
            DomesticInvoiceStrategy::class,
        ];

        // EU B2B customer - EUB2BInvoiceStrategy
        yield 'German B2B customer - EUB2BInvoiceStrategy' => [
            new InvoiceData(
                customer: new CustomerData(
                    'Hans',
                    'Mueller',
                    'hans@company.com',
                    new AddressData('Test Str. 123', 'Berlin', '10115', "DE"),
                    companyName: 'Test GmbH',
                    vatNumber: new VatNumber( 'DE','123456789'),
                ),
                amount: new Money(100.00, 'EUR'),
                originalAmount: new Money(100.00, 'EUR'),
                productName: 'Test Product',
            ),
            EUB2BInvoiceStrategy::class,
        ];

        // EU B2C customer - OSSInvoiceStrategy
        yield 'German B2C customer - OSSInvoiceStrategy' => [
            new InvoiceData(
                customer: new CustomerData(
                    'Hans',
                    'Mueller',
                    'hans@example.com',
                    new AddressData('Test Str. 123', 'Berlin', '10115', "DE"),
                ),
                amount: new Money(100.00, 'EUR'),
                originalAmount: new Money(100.00, 'EUR'),
                productName: 'Test Product',
            ),
            OSSInvoiceStrategy::class,
        ];

        // Non-EU customer - ExportInvoiceStrategy
        yield 'US customer - ExportInvoiceStrategy' => [
            new InvoiceData(
                customer: new CustomerData(
                    'John',
                    'Smith',
                    'john@example.com',
                    new AddressData('123 Main St', 'New York', '10001', "US", stateProvince: 'NY'),
                ),
                amount: new Money(100.00, 'USD'),
                originalAmount: new Money(100.00, 'USD'),
                productName: 'Test Product',
            ),
            ExportInvoiceStrategy::class,
        ];
    }

    public function testGetStrategyPriority(): void
    {
        $poland = new Country('PL', 'Poland', true, 0.23);

        // This should match CurrencyInvoiceStrategy (first in priority) not DomesticInvoiceStrategy
        $data = new InvoiceData(
            customer: new CustomerData(
                'Jan',
                'Kowalski',
                'jan@example.com',
                new AddressData('ul. Testowa 123', 'Warsaw', '00-001', "PL"),
            ),
            amount: new Money(100.00, 'USD'),
            originalAmount: new Money(100.00, 'USD'),
            productName: 'Test Product',
        );

        $strategy = $this->factory->getStrategy($data);
        $this->assertInstanceOf(CurrencyInvoiceStrategy::class, $strategy);
    }

    public function testGetStrategyForSwissCustomer(): void
    {
        // Switzerland should use ExportInvoiceStrategy
        $data = new InvoiceData(
            customer: new CustomerData(
                'Test',
                'User',
                'test@example.com',
                new AddressData('Test St 123', 'Test City', '12345', 'CH'),
            ),
            amount: new Money(100.00, 'EUR'),
            originalAmount: new Money(100.00, 'EUR'),
            productName: 'Test Product',
        );

        $strategy = $this->factory->getStrategy($data);
        $this->assertInstanceOf(ExportInvoiceStrategy::class, $strategy);
    }

    public function testAddCustomStrategy(): void
    {
        $customStrategy = new class implements InvoiceStrategyContract {
            public function getEndpoint(): string
            {
                return '/custom.json';
            }

            public function supports(InvoiceData $data): bool
            {
                return $data->customer->email === 'custom@example.com';
            }

            /** @return array<string, mixed> */
            public function prepareInvoiceData(InvoiceData $data): array
            {
                return ['custom' => true];
            }
        };

        $this->factory->addStrategy($customStrategy);

        $data = new InvoiceData(
            customer: new CustomerData(
                'Custom',
                'User',
                'custom@example.com',
                new AddressData('Custom St 123', 'Custom City', '12345', 'CH'),
            ),
            amount: new Money(100.00, 'EUR'),
            originalAmount: new Money(100.00, 'EUR'),
            productName: 'Custom Product',
        );

        $strategy = $this->factory->getStrategy($data);
        $this->assertSame($customStrategy, $strategy);
        $this->assertSame('/custom.json', $strategy->getEndpoint());
    }

    public function testCustomStrategyHasPriorityOverBuiltIn(): void
    {
        $customStrategy = new class implements InvoiceStrategyContract {
            public function getEndpoint(): string
            {
                return '/priority-custom.json';
            }

            public function supports(InvoiceData $data): bool
            {
                // This will match Polish customers, competing with built-in strategies
                return $data->customer->address->country->code === 'PL';
            }

            /** @return array<string, mixed> */
            public function prepareInvoiceData(InvoiceData $data): array
            {
                return ['priority_custom' => true];
            }
        };

        $this->factory->addStrategy($customStrategy);

        $poland = new Country('PL', 'Poland', true, 0.23);
        $data = new InvoiceData(
            customer: new CustomerData(
                'Jan',
                'Kowalski',
                'jan@example.com',
                new AddressData('ul. Testowa 123', 'Warsaw', '00-001', "PL"),
            ),
            amount: new Money(100.00, 'PLN'),
            originalAmount: new Money(100.00, 'PLN'),
            productName: 'Test Product',
        );

        $strategy = $this->factory->getStrategy($data);
        $this->assertSame($customStrategy, $strategy);
        $this->assertSame('/priority-custom.json', $strategy->getEndpoint());
    }

    public function testMultipleCustomStrategies(): void
    {
        $strategy1 = new class implements InvoiceStrategyContract {
            public function getEndpoint(): string
            {
                return '/first.json';
            }

            public function supports(InvoiceData $data): bool
            {
                return $data->productName === 'First Product';
            }

            /** @return array<string, mixed> */
            public function prepareInvoiceData(InvoiceData $data): array
            {
                return ['first' => true];
            }
        };

        $strategy2 = new class implements InvoiceStrategyContract {
            public function getEndpoint(): string
            {
                return '/second.json';
            }

            public function supports(InvoiceData $data): bool
            {
                return $data->productName === 'Second Product';
            }

            /** @return array<string, mixed> */
            public function prepareInvoiceData(InvoiceData $data): array
            {
                return ['second' => true];
            }
        };

        $this->factory->addStrategy($strategy2);
        $this->factory->addStrategy($strategy1);

        $country = 'CH';

        // Test first strategy
        $data1 = new InvoiceData(
            customer: new CustomerData('Test', 'User', 'test@example.com', new AddressData('St 123', 'City', '12345', $country)),
            amount: new Money(100.00, 'EUR'),
            originalAmount: new Money(100.00, 'EUR'),
            productName: 'First Product',
        );

        $result1 = $this->factory->getStrategy($data1);
        $this->assertSame($strategy1, $result1);

        // Test second strategy
        $data2 = new InvoiceData(
            customer: new CustomerData('Test', 'User', 'test@example.com', new AddressData('St 123', 'City', '12345', $country)),
            amount: new Money(100.00, 'EUR'),
            originalAmount: new Money(100.00, 'EUR'),
            productName: 'Second Product',
        );

        $result2 = $this->factory->getStrategy($data2);
        $this->assertSame($strategy2, $result2);
    }
}
