<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\Facades\Zatca;
use Maaz\LaravelZatca\Phase2\Hashing\ZatcaInvoiceHashGenerator;
use Maaz\LaravelZatca\Tests\TestCase;

class ZatcaInvoiceHashGeneratorTest extends TestCase
{
    private const VALID_PIH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    public function test_it_hashes_after_removing_signature_and_qr_blocks(): void
    {
        $generator = new ZatcaInvoiceHashGenerator();
        $invoiceWithFirstQr = InvoiceData::fromArray($this->invoicePayload('first-qr-value'));
        $invoiceWithSecondQr = InvoiceData::fromArray($this->invoicePayload('second-qr-value'));

        $firstXml = Zatca::generateXml($invoiceWithFirstQr);
        $secondXml = Zatca::generateXml($invoiceWithSecondQr);

        $this->assertSame(
            $generator->generate($firstXml),
            $generator->generate($secondXml)
        );

        $canonical = $generator->canonicalizeForHashing($firstXml);

        $this->assertStringNotContainsString('first-qr-value', $canonical);
        $this->assertStringNotContainsString('SignatureMethod', $canonical);
        $this->assertStringContainsString('PIH', $canonical);
        $this->assertNotFalse(base64_decode($generator->generate($firstXml), true));
    }

    public function test_it_matches_the_official_sdk_sample_digest(): void
    {
        $samplePath = dirname(__DIR__, 2)
            . '/.net sdk/zatca-einvoicing-sdk-DotNet-238-R3.4.8/Data/Samples/Simplified/Invoice/Simplified_Invoice.xml';

        if (! is_file($samplePath)) {
            $this->markTestSkipped('Official SDK sample invoice is not available.');
        }

        $sampleXml = file_get_contents($samplePath);

        $this->assertIsString($sampleXml);

        $document = new DOMDocument();
        $document->preserveWhiteSpace = true;
        $document->loadXML($sampleXml);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $expectedDigest = $xpath->evaluate('string(//ds:Reference[@Id="invoiceSignedData"]/ds:DigestValue)');

        $this->assertSame(
            'z5F9qsS6oWyDhehD8u8S0DaxV+2CUiUz9Y+UsR61JgQ=',
            $expectedDigest
        );
        $this->assertSame($expectedDigest, (new ZatcaInvoiceHashGenerator())->generate($sampleXml));
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(string $qr): array
    {
        return [
            'invoice_number' => 'INV-HASH-1',
            'uuid' => '8d487816-70b8-4ade-a618-9d620b73814a',
            'issued_at' => '2026-04-13T10:30:00+03:00',
            'seller' => [
                'name' => 'Maaz Store',
                'vat_number' => '300000000000003',
                'crn' => '1010010000',
                'street' => 'King Rd',
                'building_number' => '2322',
                'additional_number' => '1234',
                'district' => 'Al-Murabba',
                'city' => 'Riyadh',
                'postal_code' => '12345',
            ],
            'buyer' => [
                'name' => 'Buyer Co',
                'vat_number' => '300000000000013',
                'street' => 'Buyer St',
                'building_number' => '1111',
                'additional_number' => '5678',
                'district' => 'Al-Murooj',
                'city' => 'Jeddah',
                'postal_code' => '54321',
            ],
            'items' => [
                [
                    'name' => 'Product A',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'tax_percent' => 15,
                ],
            ],
            'meta' => [
                'icv' => '1',
                'pih' => self::VALID_PIH,
                'qr' => $qr,
            ],
        ];
    }
}
