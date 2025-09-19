<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\Services\Invoice\IFirma;

use DjWeb\Payments\DTOs\CustomerData;
use DjWeb\Payments\DTOs\InvoiceData;
use DjWeb\Payments\DTOs\InvoiceResult;
use DjWeb\Payments\Exceptions\InvoiceError;
use DjWeb\Payments\Services\Invoice\IFirma\IFirmaInvoiceService;
use DjWeb\Payments\DTOs\AddressData;
use DjWeb\Payments\ValueObjects\Country;
use DjWeb\Payments\ValueObjects\Money;
use DjWeb\Payments\ValueObjects\VatNumber;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class IFirmaInvoiceServiceTest extends TestCase
{
    private Client&MockObject $httpClient;
    private IFirmaInvoiceService $service;
    private string $testInvoiceKey = '1234567890123456';

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(Client::class);


        $this->service = new IFirmaInvoiceService(
            username: 'testuser',
            invoiceKey: $this->testInvoiceKey,
            apiUrl: 'https://www.ifirma.pl/iapi',
            httpClient: $this->httpClient,
        );
    }

    public function testConstructorWithValidKey(): void
    {
        $service = new IFirmaInvoiceService(
            username: 'testuser',
            invoiceKey: $this->testInvoiceKey,
        );

        $this->assertInstanceOf(IFirmaInvoiceService::class, $service);
    }

    public function testConstructorWithInvalidKey(): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('Invalid invoice key format');

        new IFirmaInvoiceService(
            username: 'testuser',
            invoiceKey: 'invalid_hex_key',
        );
    }

    public function testCreateInvoiceSuccess(): void
    {
        $poland = new Country('PL', 'Poland', true, 0.23);
        $money = new Money(123.45, 'PLN');

        $customer = new CustomerData(
            'Jan',
            'Kowalski',
            'jan@example.com',
            new AddressData('ul. Testowa 123', 'Warsaw', '00-001', 'PL'),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Test Product',
        );

        $responseBody = json_encode([
            'response' => [
                'Kod' => 0,
                'Identyfikator' => 'INV-12345',
                'Numer' => 'FV/2024/001',
                'Informacja' => 'Invoice created successfully',
            ],
        ]);

        $response = new Response(200, [], $responseBody);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/fakturakraj.json'),
                $this->callback(function (array $options): bool {
                    // Check headers
                    $this->assertArrayHasKey('headers', $options);
                    $this->assertArrayHasKey('Authentication', $options['headers']);
                    self::assertStringContainsString('IAPIS user=testuser', $options['headers']['Authentication']);
                    self::assertStringContainsString('hmac-sha1=', $options['headers']['Authentication']);
                    $this->assertSame('application/json; charset=UTF-8', $options['headers']['Content-Type']);

                    // Check body
                    $this->assertArrayHasKey('body', $options);
                    $body = json_decode($options['body'], true);
                    $this->assertArrayHasKey('Zaplacono', $body);
                    $this->assertSame(123.45, $body['Zaplacono']);

                    return true;
                })
            )
            ->willReturn($response);

        $result = $this->service->createInvoice($invoiceData);

        $this->assertInstanceOf(InvoiceResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('INV-12345', $result->invoiceId);
        $this->assertSame('FV/2024/001', $result->invoiceNumber);
        self::assertStringContainsString('INV-12345.pdf', $result->pdfUrl);
        $this->assertSame('/fakturakraj.json', $result->metadata['endpoint']);
    }

    public function testCreateInvoiceFailure(): void
    {
        $poland = new Country('PL', 'Poland', true, 0.23);
        $money = new Money(100.00, 'PLN');

        $customer = new CustomerData(
            'Jan',
            'Kowalski',
            'jan@example.com',
            new AddressData('ul. Testowa 123', 'Warsaw', '00-001', 'PL'),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Test Product',
        );

        $responseBody = json_encode([
            'response' => [
                'Kod' => 1,
                'Informacja' => 'Invalid customer data',
            ],
        ]);

        $response = new Response(200, [], $responseBody);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->willReturn($response);

        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('Invoice creation failed: Invalid customer data');

        $this->service->createInvoice($invoiceData);
    }

    public function testCreateInvoiceHttpException(): void
    {
        $poland = new Country('PL', 'Poland', true, 0.23);
        $money = new Money(100.00, 'PLN');

        $customer = new CustomerData(
            'Jan',
            'Kowalski',
            'jan@example.com',
            new AddressData('ul. Testowa 123', 'Warsaw', '00-001', 'PL'),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Test Product',
        );

        $request = new Request('POST', 'https://www.ifirma.pl/iapi/fakturakraj.json');
        $exception = new RequestException('Connection failed', $request);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->willThrowException($exception);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Connection failed');

        $this->service->createInvoice($invoiceData);
    }

    #[DataProvider('strategyEndpointProvider')]
    public function testCreateInvoiceUsesCorrectStrategy(InvoiceData $data, string $expectedEndpoint): void
    {
        $responseBody = json_encode([
            'response' => [
                'Kod' => 0,
                'Identyfikator' => 'INV-12345',
                'Informacja' => 'Success',
            ],
        ]);

        $response = new Response(200, [], $responseBody);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with($this->stringContains($expectedEndpoint))
            ->willReturn($response);

        $result = $this->service->createInvoice($data);
        $this->assertSame($expectedEndpoint, $result->metadata['endpoint']);
    }

    public static function strategyEndpointProvider(): iterable
    {
        $poland = new Country('PL', 'Poland', true, 0.23);
        $germany = new Country('DE', 'Germany', true, 0.19);
        $usa = new Country('US', 'United States', false, 0.0);

        // Polish customer with foreign currency
        yield 'Currency strategy' => [
            new InvoiceData(
                customer: new CustomerData(
                    'Jan',
                    'Kowalski',
                    'jan@example.com',
                    new AddressData('ul. Testowa 123', 'Warsaw', '00-001', 'PL'),
                ),
                amount: new Money(100.00, 'EUR'),
                originalAmount: new Money(100.00, 'EUR'),
                productName: 'Test Product',
            ),
            '/fakturawaluta.json',
        ];

        // Domestic strategy
        yield 'Domestic strategy' => [
            new InvoiceData(
                customer: new CustomerData(
                    'Jan',
                    'Kowalski',
                    'jan@example.com',
                    new AddressData('ul. Testowa 123', 'Warsaw', '00-001', 'PL'),
                ),
                amount: new Money(100.00, 'PLN'),
                originalAmount: new Money(100.00, 'PLN'),
                productName: 'Test Product',
            ),
            '/fakturakraj.json',
        ];

        // EU B2C strategy
        yield 'OSS strategy' => [
            new InvoiceData(
                customer: new CustomerData(
                    'Hans',
                    'Mueller',
                    'hans@example.com',
                    new AddressData('Test Str. 123', 'Berlin', '10115', 'DE'),
                ),
                amount: new Money(100.00, 'EUR'),
                originalAmount: new Money(100.00, 'EUR'),
                productName: 'Test Product',
            ),
            '/fakturaoss.json',
        ];

        // Export strategy
        yield 'Export strategy' => [
            new InvoiceData(
                customer: new CustomerData(
                    'John',
                    'Smith',
                    'john@example.com',
                    new AddressData('123 Main St', 'New York', '10001', 'US', stateProvince: 'NY'),
                ),
                amount: new Money(100.00, 'USD'),
                originalAmount: new Money(100.00, 'USD'),
                productName: 'Test Product',
            ),
            '/fakturaeksportuslug.json',
        ];
    }

    public function testGetInvoicePdf(): void
    {
        $pdfContent = 'PDF_CONTENT_MOCK';
        $response = new Response(200, [], $pdfContent);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with(
                'https://www.ifirma.pl/iapi/fakturakraj/INV-12345.pdf',
                $this->callback(function (array $options): bool {
                    $this->assertArrayHasKey('headers', $options);
                    $this->assertArrayHasKey('Authentication', $options['headers']);
                    self::assertStringContainsString('IAPIS user=testuser', $options['headers']['Authentication']);
                    self::assertStringContainsString('hmac-sha1=', $options['headers']['Authentication']);
                    return true;
                })
            )
            ->willReturn($response);

        $result = $this->service->getInvoicePdf('INV-12345');
        $this->assertSame($pdfContent, $result);
    }

    #[DataProvider('pdfUrlProvider')]
    public function testBuildPdfUrl(string $endpoint, string $expectedPdfPath): void
    {
        $responseBody = json_encode([
            'response' => [
                'Kod' => 0,
                'Identyfikator' => 'INV-12345',
                'Informacja' => 'Success',
            ],
        ]);

        $response = new Response(200, [], $responseBody);

        // Mock the strategy selection to return our test endpoint
        $this->httpClient->expects($this->once())
            ->method('post')
            ->with($this->stringContains($endpoint))
            ->willReturn($response);

        // Create invoice data that will use the expected strategy
        [$countryCode, $currency, $companyName, $vatNumber] = match ($endpoint) {
            '/fakturaoss.json' => ['DE', 'EUR', null, null],
            '/fakturaeksportuslug.json' => ['US', 'USD', null, null],
            '/fakturaeksportuslugue.json' => ['DE', 'EUR', 'Test GmbH', new VatNumber( 'DE', '123456789')],
            '/fakturakraj.json' => ['PL', 'PLN', null, null],
            '/fakturawaluta.json' => ['PL', 'EUR', null, null],
        };

        $address = $countryCode === 'US'
            ? new AddressData('Test St 123', 'Test City', '12345', $countryCode, stateProvince: 'NY')
            : new AddressData('Test St 123', 'Test City', '12345', $countryCode);

        $invoiceData = new InvoiceData(
            customer: new CustomerData(
                'Test',
                'User',
                'test@example.com',
                $address,
                companyName: $companyName,
                vatNumber: $vatNumber,
            ),
            amount: new Money(100.00, $currency),
            originalAmount: new Money(100.00, $currency),
            productName: 'Test Product',
        );

        $result = $this->service->createInvoice($invoiceData);
        self::assertStringContainsString($expectedPdfPath, $result->pdfUrl);
    }

    public static function pdfUrlProvider(): iterable
    {
        yield ['endpoint' => '/fakturaoss.json', 'expectedPdfPath' => '/fakturaoss/INV-12345.pdf'];
        yield ['endpoint' => '/fakturaeksportuslug.json', 'expectedPdfPath' => '/fakturaeksport/INV-12345.pdf'];
        yield ['endpoint' => '/fakturaeksportuslugue.json', 'expectedPdfPath' => '/fakturaeksport/INV-12345.pdf'];
        yield ['endpoint' => '/fakturakraj.json', 'expectedPdfPath' => '/fakturakraj/INV-12345.pdf'];
        yield ['endpoint' => '/fakturawaluta.json', 'expectedPdfPath' => '/fakturakraj/INV-12345.pdf'];
    }

    public function testHmacGeneration(): void
    {
        $poland = new Country('PL', 'Poland', true, 0.23);
        $money = new Money(100.00, 'PLN');

        $customer = new CustomerData(
            'Jan',
            'Kowalski',
            'jan@example.com',
            new AddressData('ul. Testowa 123', 'Warsaw', '00-001', 'PL'),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Test Product',
        );

        $responseBody = json_encode([
            'response' => [
                'Kod' => 0,
                'Identyfikator' => 'INV-12345',
                'Informacja' => 'Success',
            ],
        ]);

        $response = new Response(200, [], $responseBody);

        $capturedHmac = null;
        $this->httpClient->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) use (&$capturedHmac): bool {
                    $auth = $options['headers']['Authentication'];
                    preg_match('/hmac-sha1=([a-f0-9]+)/', $auth, $matches);
                    $capturedHmac = $matches[1] ?? null;
                    return true;
                })
            )
            ->willReturn($response);

        $this->service->createInvoice($invoiceData);

        // Verify HMAC was generated (40 characters hex)
        $this->assertNotNull($capturedHmac);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $capturedHmac);
    }

    public function testJsonEncodingError(): void
    {
        // Create a mock strategy that returns invalid data for JSON encoding
        $poland = new Country('PL', 'Poland', true, 0.23);

        // Create customer with invalid UTF-8 sequence that would cause json_encode to fail
        $customer = new CustomerData(
            "Invalid\xFF",
            'User',
            'test@example.com',
            new AddressData('Test St', 'Warsaw', '00-001', 'PL'),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: new Money(100.00, 'PLN'),
            originalAmount: new Money(100.00, 'PLN'),
            productName: 'Test Product',
        );

        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('Failed to encode invoice data as JSON');

        $this->service->createInvoice($invoiceData);
    }

    public function testResponseWithoutInvoiceNumber(): void
    {
        $poland = new Country('PL', 'Poland', true, 0.23);
        $money = new Money(100.00, 'PLN');

        $customer = new CustomerData(
            'Jan',
            'Kowalski',
            'jan@example.com',
            new AddressData('ul. Testowa 123', 'Warsaw', '00-001', 'PL'),
        );

        $invoiceData = new InvoiceData(
            customer: $customer,
            amount: $money,
            originalAmount: $money,
            productName: 'Test Product',
        );

        $responseBody = json_encode([
            'response' => [
                'Kod' => 0,
                'Identyfikator' => 'INV-12345',
                'Informacja' => 'Success without number',
            ],
        ]);

        $response = new Response(200, [], $responseBody);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->willReturn($response);

        $result = $this->service->createInvoice($invoiceData);

        $this->assertTrue($result->success);
        $this->assertSame('INV-12345', $result->invoiceId);
        $this->assertNull($result->invoiceNumber);
    }
}
