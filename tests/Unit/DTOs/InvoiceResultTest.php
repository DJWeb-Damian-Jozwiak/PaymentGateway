<?php

declare(strict_types=1);

namespace DjWeb\Payments\Tests\Unit\DTOs;

use DjWeb\Payments\DTOs\InvoiceResult;
use PHPUnit\Framework\TestCase;

final class InvoiceResultTest extends TestCase
{
    public function testCreateSuccessfulInvoiceResult(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-01 10:00:00');
        $result = new InvoiceResult(
            success: true,
            invoiceId: 'INV-123',
            invoiceNumber: 'FV/2025/01/001',
            pdfUrl: 'https://example.com/invoices/INV-123.pdf',
            createdAt: $createdAt
        );

        $this->assertTrue($result->success);
        $this->assertEquals('INV-123', $result->invoiceId);
        $this->assertEquals('FV/2025/01/001', $result->invoiceNumber);
        $this->assertEquals('https://example.com/invoices/INV-123.pdf', $result->pdfUrl);
        $this->assertNull($result->errorMessage);
        $this->assertEmpty($result->metadata);
        $this->assertSame($createdAt, $result->createdAt);
        $this->assertFalse($result->hasError);
    }

    public function testCreateFailedInvoiceResult(): void
    {
        $result = new InvoiceResult(
            success: false,
            errorMessage: 'Invalid customer data'
        );

        $this->assertFalse($result->success);
        $this->assertNull($result->invoiceId);
        $this->assertNull($result->invoiceNumber);
        $this->assertNull($result->pdfUrl);
        $this->assertEquals('Invalid customer data', $result->errorMessage);
        $this->assertEmpty($result->metadata);
        $this->assertNull($result->createdAt);
        $this->assertTrue($result->hasError);
    }

    public function testCreateInvoiceResultWithMetadata(): void
    {
        $metadata = [
            'system' => 'IFirma',
            'attempts' => 2,
            'processing_time' => 1.5,
            'tags' => ['priority', 'export']
        ];

        $result = new InvoiceResult(
            success: true,
            invoiceId: 'INV-456',
            invoiceNumber: 'FV/2025/01/002',
            metadata: $metadata
        );

        $this->assertTrue($result->success);
        $this->assertEquals($metadata, $result->metadata);
        $this->assertEquals('IFirma', $result->metadata['system']);
        $this->assertEquals(2, $result->metadata['attempts']);
        $this->assertEquals(1.5, $result->metadata['processing_time']);
        $this->assertIsArray($result->metadata['tags']);
        $this->assertFalse($result->hasError);
    }

    public function testHasErrorProperty(): void
    {
        $resultWithError = new InvoiceResult(
            success: false,
            errorMessage: 'Connection timeout'
        );
        $this->assertTrue($resultWithError->hasError);

        $resultWithoutError = new InvoiceResult(
            success: true,
            invoiceId: 'INV-789'
        );
        $this->assertFalse($resultWithoutError->hasError);

        $failedResultWithoutMessage = new InvoiceResult(
            success: false
        );
        $this->assertFalse($failedResultWithoutMessage->hasError);
    }

    public function testCreateMinimalSuccessfulResult(): void
    {
        $result = new InvoiceResult(success: true);

        $this->assertTrue($result->success);
        $this->assertNull($result->invoiceId);
        $this->assertNull($result->invoiceNumber);
        $this->assertNull($result->pdfUrl);
        $this->assertNull($result->errorMessage);
        $this->assertEmpty($result->metadata);
        $this->assertNull($result->createdAt);
        $this->assertFalse($result->hasError);
    }

    public function testCreateMinimalFailedResult(): void
    {
        $result = new InvoiceResult(success: false);

        $this->assertFalse($result->success);
        $this->assertNull($result->invoiceId);
        $this->assertNull($result->invoiceNumber);
        $this->assertNull($result->pdfUrl);
        $this->assertNull($result->errorMessage);
        $this->assertEmpty($result->metadata);
        $this->assertNull($result->createdAt);
        $this->assertFalse($result->hasError);
    }

    public function testToArrayMethod(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-01 12:00:00');
        $result = new InvoiceResult(
            success: true,
            invoiceId: 'INV-999',
            invoiceNumber: 'FV/2025/01/999',
            pdfUrl: 'https://example.com/invoices/INV-999.pdf',
            errorMessage: null,
            metadata: ['extra' => 'data'],
            createdAt: $createdAt
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('invoiceId', $array);
        $this->assertArrayHasKey('invoiceNumber', $array);
        $this->assertArrayHasKey('pdfUrl', $array);
        $this->assertArrayHasKey('errorMessage', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('createdAt', $array);
        $this->assertTrue($array['success']);
        $this->assertEquals('INV-999', $array['invoiceId']);
        $this->assertEquals('FV/2025/01/999', $array['invoiceNumber']);
        $this->assertEquals('https://example.com/invoices/INV-999.pdf', $array['pdfUrl']);
        $this->assertNull($array['errorMessage']);
        $this->assertEquals(['extra' => 'data'], $array['metadata']);
    }
}