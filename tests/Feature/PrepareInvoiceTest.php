<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Maaz\LaravelZatca\DTOs\PreparedInvoiceResult;
use Maaz\LaravelZatca\Facades\Zatca;
use Maaz\LaravelZatca\Phase2\Hashing\ZatcaInvoiceHashGenerator;
use Maaz\LaravelZatca\Tests\TestCase;

class PrepareInvoiceTest extends TestCase
{
    private const VALID_PIH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    public function test_it_prepares_a_final_phase_2_invoice_without_submitting(): void
    {
        [$privateKey, $certificate] = $this->sdkCertificatePair();

        config()->set('zatca.default_tenant.certificates.private_key', $privateKey);
        config()->set('zatca.default_tenant.certificates.certificate', $certificate);

        $prepared = Zatca::invoice()
            ->invoiceNumber('INV-PREP-1')
            ->issuedAt('2026-04-19T12:00:00+03:00')
            ->seller([
                'name' => 'Demo Seller',
                'vat_number' => '300000000000003',
                'crn' => '1010010000',
                'street' => 'Prince Sultan',
                'building_number' => '2322',
                'additional_number' => '1234',
                'district' => 'Al-Murabba',
                'city' => 'Riyadh',
                'postal_code' => '12345',
            ])
            ->buyer([
                'name' => 'Demo Buyer',
                'vat_number' => '300000000000013',
                'street' => 'King Road',
                'building_number' => '1111',
                'additional_number' => '5678',
                'district' => 'Al-Murooj',
                'city' => 'Riyadh',
                'postal_code' => '54321',
            ])
            ->addItem([
                'name' => 'Test Product',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_percent' => 15,
            ])
            ->meta([
                'icv' => '1',
                'pih' => self::VALID_PIH,
            ])
            ->prepare();

        $this->assertInstanceOf(PreparedInvoiceResult::class, $prepared);
        $this->assertStringContainsString('<ext:UBLExtensions>', $prepared->signedXml);
        $this->assertStringContainsString('<ds:SignatureValue>', $prepared->signedXml);
        $this->assertStringContainsString($prepared->qrCode, $prepared->finalXml);
        $this->assertSame($prepared->finalXml, base64_decode($prepared->apiPayload()['invoice'], true));
        $this->assertSame(
            $prepared->invoiceHash,
            (new ZatcaInvoiceHashGenerator())->generate($prepared->finalXml)
        );

        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->loadXML($prepared->finalXml);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('invoice', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $this->assertSame(
            $prepared->qrCode,
            $xpath->evaluate('string(/invoice:Invoice/cac:AdditionalDocumentReference[cbc:ID="QR"]/cac:Attachment/cbc:EmbeddedDocumentBinaryObject)')
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function sdkCertificatePair(): array
    {
        $root = dirname(__DIR__, 2) . '/.net sdk/zatca-einvoicing-sdk-DotNet-238-R3.4.8/Data/Certificates';
        $certificatePath = $root . '/cert.pem';
        $privateKeyPath = $root . '/ec-secp256k1-priv-key.pem';

        if (! is_file($certificatePath) || ! is_file($privateKeyPath)) {
            $this->markTestSkipped('Official SDK certificate fixtures are not available.');
        }

        $privateKey = file_get_contents($privateKeyPath);
        $certificate = file_get_contents($certificatePath);

        $this->assertIsString($privateKey);
        $this->assertIsString($certificate);

        return [$privateKey, $certificate];
    }
}
