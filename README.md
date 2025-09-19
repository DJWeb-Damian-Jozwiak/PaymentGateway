# DjWeb Payments 💳

[![PHP Version](https://img.shields.io/badge/PHP-8.4+-787CB5.svg)](https://php.net)
[![Tests](https://img.shields.io/badge/tests-276%20passed-brightgreen.svg)](./tests)
[![Coverage](https://img.shields.io/badge/coverage-92%25%20classes-brightgreen.svg)](./tests)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg)](https://phpstan.org)
[![PHPInsights](https://img.shields.io/badge/PHPInsightsCode-97%25-brightgreen.svg)](https://phpinsights.com)
[![PHPInsights](https://img.shields.io/badge/PHPInsightsComplexity-100%25-brightgreen.svg)](https://phpinsights.com)
[![PHPInsights](https://img.shields.io/badge/PHPInsightsArchitecture-100%25-brightgreen.svg)](https://phpinsights.com)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Modern, secure and flexible payment system for PHP applications. Supports Stripe, invoice generation via IFirma and EU VAT data validation.

## ✨ Features

- 🔒 **Security** - VAT validation, type checking, sensitive data protection
- 💰 **Stripe Payments** - Full Stripe PaymentIntents support with webhooks
- 📄 **IFirma Invoices** - Automatic invoice generation with various VAT strategies
- 🌍 **EU Support** - VAT number validation, tax rates, regional requirements
- 🧪 **High Quality** - 92% test coverage, PHPStan level 8, PHPInsights A+
- 🚀 **Modern PHP** - Uses latest PHP 8.4 features (property hooks, readonly classes)

## 📦 Installation

```bash
composer require djweb/payments
```

## 🚀 Quick Start

### Stripe Payments

```php
use DjWeb\Payments\Services\Payment\Stripe\StripePaymentGateway;
use DjWeb\Payments\DTOs\PaymentRequest;
use DjWeb\Payments\DTOs\CustomerData;
use DjWeb\Payments\DTOs\AddressData;
use DjWeb\Payments\ValueObjects\Money;
use DjWeb\Payments\ValueObjects\VatNumber;

// Gateway configuration
$stripe = new StripePaymentGateway(
    secretKey: 'sk_test_...',
    webhookSecret: 'whsec_...'
);

// Customer data
$customer = new CustomerData(
    email: 'customer@example.com',
    firstName: 'John',
    lastName: 'Doe',
    address: new AddressData(
        street: '123 Example Street',
        city: 'Warsaw',
        postalCode: '00-001',
        country: 'PL'
    ),
    companyName: 'Example Company Ltd.',
    vatNumber: new VatNumber('PL', '5260001246')
);

// Payment request
$request = new PaymentRequest(
    amount: new Money(299.99, 'PLN'),
    customer: $customer,
    description: 'Premium Product Purchase'
);

// Create PaymentIntent
$intent = $stripe->createPaymentIntent($request);

// Process payment
$result = $stripe->processPayment($intent);

if ($result->success) {
    echo "Payment successful: {$result->transactionId}";
}
```

### IFirma Invoice Generation

```php
use DjWeb\Payments\Services\Invoice\IFirma\IFirmaInvoiceService;
use DjWeb\Payments\DTOs\InvoiceData;

$invoiceService = new IFirmaInvoiceService(
    username: 'your_username',
    invoiceKey: 'your_invoice_key',
    apiUrl: 'https://www.ifirma.pl/iapi'
);

$invoiceData = new InvoiceData(
    customer: $customer,
    amount: new Money(299.99, 'PLN'),
    originalAmount: new Money(299.99, 'PLN'),
    productName: 'Premium Product'
);

$invoiceResult = $invoiceService->createInvoice($invoiceData);

if ($invoiceResult->success) {
    echo "Invoice created: {$invoiceResult->invoiceNumber}";
    echo "PDF: {$invoiceResult->pdfUrl}";
}
```

### Working with Discounts

```php
use DjWeb\Payments\DTOs\DiscountData;

$discount = new DiscountData(
    code: 'SAVE20',
    percentage: 20.0,
    maxUsages: 100,
    currentUsages: 15,
    validUntil: new DateTimeImmutable('2024-12-31')
);

if ($discount->isValid) {
    $originalAmount = 299.99;
    $discountAmount = $discount->calculateDiscountAmount($originalAmount);
    $finalAmount = $discount->calculateFinalAmount($originalAmount);
    
    echo "Discount: {$discountAmount} PLN";
    echo "Total: {$finalAmount} PLN";
}
```

### VAT and Country Validation

```php
use DjWeb\Payments\ValueObjects\VatNumber;
use DjWeb\Payments\ValueObjects\Country;

// Create and validate VAT number
$vatNumber = new VatNumber('PL', '526-000-12-46');
echo $vatNumber; // PL5260001246

// Check EU country
$country = new Country('PL');
echo $country->name; // Poland
echo $country->getVatRate(); // 0.23 (23%)
echo $country->isEu ? 'EU' : 'Non-EU'; // EU

// Check state/province requirements
if ($country->requiresStateProvince()) {
    // Required for US, CA, AU, BR, MX, IN, MY, AR
}
```

### Stripe Webhook Handling

```php
use DjWeb\Payments\DTOs\WebhookEvent;

// Webhook signature verification
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'];

try {
    $isValid = $stripe->verifyWebhookSignature($payload, $signature);
    
    if ($isValid) {
        $eventData = json_decode($payload, true);
        
        $event = new WebhookEvent(
            id: $eventData['id'],
            type: $eventData['type'],
            data: $eventData['data'],
            source: 'stripe',
            createdAt: new DateTimeImmutable()
        );
        
        // Handle different event types
        if ($event->isPaymentEvent) {
            // payment_intent.succeeded, payment_intent.payment_failed, etc.
            handlePaymentEvent($event);
        } elseif ($event->isInvoiceEvent) {
            // invoice.payment_succeeded, invoice.payment_failed, etc.
            handleInvoiceEvent($event);
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    exit('Webhook error: ' . $e->getMessage());
}
```

## 🏗️ Architecture

### Design Patterns

- **Strategy Pattern** - Different invoicing strategies (domestic, EU B2B, export, OSS)
- **Factory Pattern** - Automatic strategy selection based on customer data
- **Value Objects** - Safe representations of money, countries, VAT numbers
- **DTOs** - Data transfer with validation and transformation

### Project Structure

```
src/
├── Contracts/          # Interfaces
│   ├── Arrayable.php
│   ├── InvoiceServiceContract.php
│   ├── PaymentGatewayContract.php
│   └── WebhookHandlerContract.php
├── DTOs/              # Data Transfer Objects
│   ├── AddressData.php
│   ├── CustomerData.php
│   ├── DiscountData.php
│   ├── InvoiceData.php
│   ├── PaymentIntent.php
│   ├── PaymentRequest.php
│   ├── PaymentResult.php
│   └── WebhookEvent.php
├── Exceptions/        # Business Exceptions
│   ├── InvoiceError.php
│   └── PaymentError.php
├── Services/
│   ├── Invoice/       # Invoice Services
│   │   └── IFirma/   # IFirma Implementation
│   ├── Payment/       # Payment Gateways
│   │   └── Stripe/   # Stripe Implementation
│   └── Validators/    # VAT Validators
└── ValueObjects/      # Value Objects
    ├── Country.php
    ├── Money.php
    └── VatNumber.php
```

## 🧪 Code Quality

The project maintains the highest code quality through:

### Test Coverage
- **92% class coverage** (23/25)
- **91% method coverage** (74/81)
- **89% line coverage** (389/438)
- **276 tests**, **1007 assertions**

### Quality Control Tools
```bash
# All tests
vendor/bin/phpunit

# With code coverage
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html coverage

# Static analysis PHPStan (level 9)
vendor/bin/phpstan analyse

# Quality analysis PHPInsights
vendor/bin/phpinsights analyse

# Code standards check
vendor/bin/phpcs
```

## 📝 Invoice Strategies

The system automatically selects the appropriate strategy based on customer data:

| Strategy | Conditions | VAT | IFirma Endpoint |
|----------|------------|-----|-----------------|
| **Domestic** | Polish customer, PLN | 23% | `/fakturakraj.json` |
| **Currency** | Polish customer, EUR/USD | 23% | `/fakturawaluta.json` |
| **EU B2B** | EU customer with valid VAT | 0% (reverse charge) | `/fakturaeksportuslugue.json` |
| **OSS** | EU consumer | Customer's country VAT | `/fakturaoss.json` |
| **Export** | Non-EU customer | 0% | `/fakturaeksportuslug.json` |


flowchart TD
    A[InvoiceData] --> B{Country = PL?}
    B -- Tak --> C{Currency = PLN?}
    C -- Tak --> S1[DomesticInvoiceStrategy<br/>/iapi/fakturakraj.json<br/>VAT: PL stawka]
    C -- Nie --> S2[CurrencyInvoiceStrategy<br/>/iapi/fakturawaluta.json<br/>VAT: PL stawka]

    B -- Nie --> D{EU country?}
    D -- Tak --> E{B2B z VAT nr?}
    E -- Tak --> S3[EUB2BInvoiceStrategy<br/>/iapi/fakturaeksportuslugue.json<br/>VAT: 0% reverse charge (art. 28b)]
    E -- Nie --> S4[OSSInvoiceStrategy<br/>/iapi/fakturaoss.json<br/>VAT: wg kraju konsumenta]

    D -- Nie --> S5[ExportInvoiceStrategy<br/>/iapi/fakturaeksportuslug.json<br/>VAT: poza terytorium PL]

    %% Legend
    classDef strat fill:#e7f5ff,stroke:#4dabf7,stroke-width:1px;
    class S1,S2,S3,S4,S5 strat;
### Custom Strategy Example

```php
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\InvoiceStrategyContract;

class CustomInvoiceStrategy implements InvoiceStrategyContract
{
    public function shouldApply(CustomerData $customer): bool
    {
        return $customer->address->country->code === 'US' 
            && $customer->companyName !== null;
    }
    
    public function getEndpoint(): string
    {
        return '/custom-us-b2b.json';
    }
    
    public function prepareInvoiceData(InvoiceData $data): array
    {
        return [
            'NabywcaNazwa' => $data->customer->companyName,
            'NabywcaAdres' => $data->customer->address->street,
            'StawkaVat' => 0, // No VAT for export
            // ... more fields
        ];
    }
}
```

## 🌍 Supported Countries and Currencies

### EU Countries with Automatic VAT Rates
All 27 EU countries with automatic VAT rates:

| Country | Code | VAT Rate | Requires State/Province |
|---------|------|----------|------------------------|
| 🇵🇱 Poland | PL | 23% | ❌ |
| 🇩🇪 Germany | DE | 19% | ❌ |
| 🇫🇷 France | FR | 20% | ❌ |
| 🇺🇸 USA | US | 0% | ✅ |
| 🇨🇦 Canada | CA | 0% | ✅ |

### Currencies
- **EUR**, **USD**, **PLN**, **GBP** - full support
- **JPY**, **KRW** - no decimal places  
- Support for 100+ currencies via Stripe

```php
// Automatic conversion to Stripe units
$money = new Money(99.99, 'PLN');
echo $money->toSmallestUnit(); // 9999 (grosze)

$yen = new Money(1000, 'JPY');  
echo $yen->toSmallestUnit(); // 1000 (no conversion)
```

## 🔒 Security

### Built-in Protections
- **Input validation** - All DTOs validate data at construction
- **Sensitive data protection** - `#[SensitiveParameter]` attribute for API keys
- **Safe Value Objects** - Immutable objects with validation
- **VAT validation** - Automatic checksum validation for Polish NIPs
- **Webhook verification** - Stripe signature checking

### Error Handling Example

```php
use DjWeb\Payments\Exceptions\PaymentError;
use DjWeb\Payments\Exceptions\InvoiceError;

try {
    $intent = $stripe->createPaymentIntent($request);
} catch (PaymentError $e) {
    // Safe logging with context
    logger()->error('Payment failed', [
        'message' => $e->getMessage(),
        'context' => $e->context, // Additional context data
        'customer_id' => $request->customer->email
    ]);
}

// Error factories for different scenarios
throw PaymentError::invalidAmount(-100);
throw PaymentError::unsupportedCurrency('XYZ');
throw PaymentError::gatewayError('Stripe', 'Card declined');

throw InvoiceError::invalidCustomerData('vatNumber');
throw InvoiceError::unsupportedCountry('XX');
throw InvoiceError::apiError('IFirma', 'Connection timeout');
```

## 🔧 Configuration

### Environment Variables

```env
# Stripe
STRIPE_SECRET_KEY=sk_test_51234567890...
STRIPE_WEBHOOK_SECRET=whsec_1234567890...

# IFirma  
IFIRMA_USERNAME=your_username
IFIRMA_INVOICE_KEY=123456789abcdef
IFIRMA_API_URL=https://www.ifirma.pl/iapi

# Optional
APP_ENV=production
LOG_LEVEL=info
```

### Dependency Injection (Laravel/Symfony)

```php
// Laravel Service Provider
use DjWeb\Payments\Contracts\PaymentGatewayContract;
use DjWeb\Payments\Contracts\InvoiceServiceContract;

$this->app->bind(PaymentGatewayContract::class, function () {
    return new StripePaymentGateway(
        secretKey: config('services.stripe.secret'),
        webhookSecret: config('services.stripe.webhook_secret')
    );
});

$this->app->bind(InvoiceServiceContract::class, function () {
    return new IFirmaInvoiceService(
        username: config('services.ifirma.username'),
        invoiceKey: config('services.ifirma.invoice_key'),
        apiUrl: config('services.ifirma.api_url', 'https://www.ifirma.pl/iapi')
    );
});
```

## 📊 Quality Metrics

| Metric | Value | Status |
|--------|-------|--------|
| **Class Coverage** | 92% (23/25) | ✅ Excellent |
| **Method Coverage** | 91% (74/81) | ✅ Excellent |
| **Line Coverage** | 89% (389/438) | ✅ Very Good |
| **PHPStan** | Level 8/9 | ✅ Perfect |
| **PHPInsights Code** | 97% | ✅ Excellent |
| **PHPInsights Complexity** | 100% | ✅ Excellent |
| **PHPInsights Architecture** | 100% | ✅ Excellent |
| **PHPInsights Style** | 98% | ✅ Excellent |
| **Tests** | 276 passed | ✅ All Green |

## 📄 License

MIT License. See [LICENSE](LICENSE) for details.

## 🆘 Support

- 📧 **Email**: kontakt@djweb.pl
- 🐛 **Issues**: [GitHub Issues](https://github.com/djweb/payments/issues)
- 📖 **Documentation**: [GitHub Wiki](https://github.com/djweb/payments/wiki)
- 💬 **Discussions**: [GitHub Discussions](https://github.com/djweb/payments/discussions)

---

<div align="center">

**Created with ❤️ by [DjWeb](https://djweb.pl) **

*Modern PHP solutions for future businesses*

</div>
