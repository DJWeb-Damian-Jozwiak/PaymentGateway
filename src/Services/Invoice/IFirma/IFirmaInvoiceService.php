<?php

declare(strict_types=1);

namespace DjWeb\Payments\Services\Invoice\IFirma;

use DjWeb\Payments\Contracts\InvoiceServiceContract;
use DjWeb\Payments\DTOs\InvoiceData;
use DjWeb\Payments\DTOs\InvoiceResult;
use DjWeb\Payments\Exceptions\InvoiceError;
use DjWeb\Payments\Services\Invoice\IFirma\Strategies\InvoiceStrategyFactory;
use GuzzleHttp\Client;

final class IFirmaInvoiceService implements InvoiceServiceContract
{
    public private(set) Client $httpClient;
    private string $invoiceKey {
        get => $this->invoiceKey;
        set {
            $len = strlen($value);
            if ($len % 2 !== 0) {
                throw new InvoiceError('Invalid invoice key format');
            }
            /** @var string $decodedKey */
            $decodedKey = hex2bin($value);
            $this->invoiceKey = $decodedKey;
        }
    }
    private InvoiceStrategyFactory $strategyFactory;

    public function __construct(
        private readonly string $username,
        #[\SensitiveParameter]
        string $invoiceKey,
        private readonly string $apiUrl = 'https://www.ifirma.pl/iapi',
        ?Client $httpClient = null,
    ) {
        $this->invoiceKey = $invoiceKey;
        $this->httpClient = $httpClient ?? new Client();
        $this->strategyFactory = new InvoiceStrategyFactory();
    }

    public function createInvoice(InvoiceData $data): InvoiceResult
    {
        $strategy = $this->strategyFactory->getStrategy($data);
        // Select appropriate strategy based on country and customer type
        $requestContent = $this->prepareContent($data);

        $hash = $this->generateHmac($requestContent, $this->strategyFactory->getStrategy($data)->getEndpoint());
        // Make API request
        $response = $this->httpClient->post($this->apiUrl . $strategy->getEndpoint(), [
            'headers' => [
                'Authentication' => "IAPIS user={$this->username}, hmac-sha1={$hash}",
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
            'body' => $requestContent,
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        return $this->returnResponse($result['response'], $strategy->getEndpoint());
    }

    public function getInvoicePdf(string $invoiceId): string
    {
        $endpoint = "/fakturakraj/{$invoiceId}.pdf";
        $url = $this->apiUrl . $endpoint;
        $signatureBase = $url . $this->username . 'faktura';

        $hash = hash_hmac('sha1', $signatureBase, $this->invoiceKey);

        $response = $this->httpClient->get($url, [
            'headers' => [
                'Authentication' => "IAPIS user={$this->username}, hmac-sha1={$hash}",
            ],
        ]);

        return $response->getBody()->getContents();
    }

    /**
     * @param array<string, mixed> $response1
     *
     * @throws InvoiceError
     */
    public function returnResponse(array $response1, string $endpoint): InvoiceResult
    {
        if ($response1['Kod'] === 0) {
            return new InvoiceResult(
                success: true,
                invoiceId: $response1['Identyfikator'],
                invoiceNumber: $response1['Numer'] ?? null,
                pdfUrl: $this->buildPdfUrl($response1['Identyfikator'], $endpoint),
                metadata: [
                    'endpoint' => $endpoint,
                    'message' => $response1['Informacja'],
                ],
            );
        }

        throw new InvoiceError(
            'Invoice creation failed: ' . ($response1['Informacja'] ?? 'Unknown error')
        );
    }

    private function prepareContent(InvoiceData $data): string
    {
        $strategy = $this->strategyFactory->getStrategy($data);

        // Prepare invoice data using strategy
        $invoicePayload = $strategy->prepareInvoiceData($data);

        // Prepare request
        $requestContent = json_encode($invoicePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($requestContent === false) {
            throw new InvoiceError('Failed to encode invoice data as JSON');
        }
        return $requestContent;
    }

    private function generateHmac(string $content, string $endpoint): string
    {
        // iFirma uses specific URL for HMAC generation
        $baseUrl = 'https://www.ifirma.pl/iapi';
        $url = $baseUrl . $endpoint; // Always use this for HMAC
        $signatureBase = $url . $this->username . 'faktura' . $content;

        return hash_hmac('sha1', $signatureBase, $this->invoiceKey);
    }

    private function buildPdfUrl(string $invoiceId, string $endpoint): string
    {
        // Determine PDF endpoint based on invoice type
        $pdfEndpoint = match (true) {
            str_contains($endpoint, 'fakturaoss') => '/fakturaoss',
            str_contains($endpoint, 'fakturaeksport') => '/fakturaeksport',
            default => '/fakturakraj',
        };

        return $this->apiUrl . $pdfEndpoint . "/{$invoiceId}.pdf";
    }
}
