<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Log\LogManager;
use Maaz\LaravelZatca\Contracts\ApiClient;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Facades\Zatca;
use Maaz\LaravelZatca\Phase1\Encoders\TlvEncoder;
use Maaz\LaravelZatca\Phase2\Builders\UblInvoiceBuilder;
use Maaz\LaravelZatca\Phase2\Hashing\ZatcaInvoiceHashGenerator;
use Maaz\LaravelZatca\Phase2\Qr\Phase2QrCodeService;
use Maaz\LaravelZatca\Phase2\Signatures\SignatureService;
use Maaz\LaravelZatca\Tenancy\Stores\NullTenantInvoiceStateStore;
use Maaz\LaravelZatca\Exceptions\ApiException;
use Maaz\LaravelZatca\Services\SubmissionPipeline;
use Maaz\LaravelZatca\Support\CertificateLoader;
use Maaz\LaravelZatca\Support\ZatcaLogger;
use Maaz\LaravelZatca\Tests\TestCase;

class SubmissionPipelineTest extends TestCase
{
    private const VALID_PIH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    public function test_it_signs_injects_phase_2_qr_and_submits_the_final_xml(): void
    {
        [$privateKey, $certificate] = $this->sdkCertificatePair();
        $tenantConfig = TenantConfig::fromArray([
            'tenant_id' => 'tenant-1',
            'environment' => 'sandbox',
            'seller_name' => 'Maaz Store',
            'seller_vat_number' => '300000000000003',
            'certificates' => [
                'private_key' => $privateKey,
                'certificate' => $certificate,
            ],
        ]);
        $invoice = Zatca::invoice()
            ->invoiceNumber('INV-PIPE-1')
            ->issuedAt('2026-04-13T10:30:00+03:00')
            ->seller([
                'name' => 'Maaz Store',
                'vat_number' => '300000000000003',
                'crn' => '1010010000',
                'street' => 'King Rd',
                'building_number' => '2322',
                'additional_number' => '1234',
                'district' => 'Al-Murabba',
                'city' => 'Riyadh',
                'postal_code' => '12345',
            ])
            ->buyer([
                'name' => 'Buyer Co',
                'vat_number' => '300000000000013',
                'street' => 'Buyer St',
                'building_number' => '1111',
                'additional_number' => '5678',
                'district' => 'Al-Murooj',
                'city' => 'Jeddah',
                'postal_code' => '54321',
            ])
            ->addItem([
                'name' => 'Product A',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_percent' => 15,
            ])
            ->meta([
                'icv' => '1',
                'pih' => self::VALID_PIH,
            ])
            ->generate();

        $hashGenerator = new ZatcaInvoiceHashGenerator();
        $apiClient = new class implements ApiClient {
            public array $payload = [];

            public ?TenantConfig $tenantConfig = null;

            public ?string $mode = null;

            public function submit(array $payload, TenantConfig $tenantConfig, string $mode): array
            {
                $this->payload = $payload;
                $this->tenantConfig = $tenantConfig;
                $this->mode = $mode;

                return [
                    'success' => true,
                    'status_code' => 200,
                    'body' => [
                        'reportingStatus' => 'REPORTED',
                    ],
                ];
            }
        };
        $pipeline = new SubmissionPipeline(
            new UblInvoiceBuilder(),
            new SignatureService(new CertificateLoader(), $hashGenerator),
            new Phase2QrCodeService(new TlvEncoder(), new CertificateLoader(), $hashGenerator),
            $apiClient,
            $hashGenerator,
            new ZatcaLogger($this->app->make(LogManager::class)),
            new CertificateLoader(),
            new NullTenantInvoiceStateStore()
        );

        $result = $pipeline->submit($invoice, $tenantConfig, 'reporting');
        $submittedXml = base64_decode($apiClient->payload['invoice'], true);

        $this->assertTrue($result->accepted());
        $this->assertSame('reporting', $apiClient->mode);
        $this->assertSame($tenantConfig, $apiClient->tenantConfig);
        $this->assertSame($invoice->uuid, $apiClient->payload['uuid']);
        $this->assertSame($result->invoiceHash, $apiClient->payload['invoiceHash']);
        $this->assertSame($result->signedXml, $submittedXml);
        $this->assertSame($result->invoiceHash, $hashGenerator->generate($result->signedXml));

        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->loadXML($result->signedXml);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('invoice', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $this->assertSame('UBLExtensions', $document->documentElement?->firstChild?->localName);
        $this->assertNotSame('', $xpath->evaluate('string(//ds:SignatureValue)'));
        $this->assertSame($result->invoiceHash, $xpath->evaluate('string(//ds:Reference[@URI=""]/ds:DigestValue)'));
        $signedProperties = $xpath->query('//*[@Id="xadesSignedProperties"]')->item(0);
        $this->assertNotNull($signedProperties);
        $this->assertSame(
            base64_encode(hash('sha256', $signedProperties->C14N(false, false), true)),
            $xpath->evaluate('string(//ds:Reference[@URI="#xadesSignedProperties"]/ds:DigestValue)')
        );
        $this->assertSame($result->qrCode, $xpath->evaluate('string(/invoice:Invoice/cac:AdditionalDocumentReference[cbc:ID="QR"]/cac:Attachment/cbc:EmbeddedDocumentBinaryObject)'));
        $this->assertSame('text/plain', $xpath->evaluate('string(/invoice:Invoice/cac:AdditionalDocumentReference[cbc:ID="QR"]/cac:Attachment/cbc:EmbeddedDocumentBinaryObject/@mimeCode)'));
    }

    public function test_it_rejects_submission_when_seller_vat_does_not_match_authentication_certificate_vat(): void
    {
        [, $certificate] = $this->sdkCertificatePair();
        $token = base64_encode((string) preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $certificate));

        $tenantConfig = TenantConfig::fromArray([
            'tenant_id' => 'tenant-1',
            'environment' => 'sandbox',
            'seller_name' => 'BI Technology Company',
            'seller_vat_number' => '313138851500003',
            'api' => [
                'binary_security_token' => $token,
            ],
            'certificates' => [
                'private_key' => $this->sdkCertificatePair()[0],
                'certificate' => $this->sdkCertificatePair()[1],
            ],
        ]);

        $invoice = Zatca::invoice()
            ->invoiceNumber('INV-MISMATCH-1')
            ->issuedAt('2026-04-13T10:30:00+03:00')
            ->seller([
                'name' => 'BI Technology Company',
                'vat_number' => '313138851500003',
                'crn' => '7050816433',
                'street' => 'Saidya',
                'building_number' => '7036',
                'additional_number' => '7036',
                'district' => 'AL Duraihemiyah',
                'city' => 'Riyadh',
                'postal_code' => '12796',
            ])
            ->addItem([
                'name' => 'Mismatch Item',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_percent' => 15,
            ])
            ->meta([
                'icv' => '1',
                'pih' => self::VALID_PIH,
            ])
            ->generate();

        $pipeline = new SubmissionPipeline(
            new UblInvoiceBuilder(),
            new SignatureService(new CertificateLoader(), new ZatcaInvoiceHashGenerator()),
            new Phase2QrCodeService(new TlvEncoder(), new CertificateLoader(), new ZatcaInvoiceHashGenerator()),
            new class implements ApiClient {
                public function submit(array $payload, TenantConfig $tenantConfig, string $mode): array
                {
                    return ['success' => true];
                }
            },
            new ZatcaInvoiceHashGenerator(),
            new ZatcaLogger($this->app->make(LogManager::class)),
            new CertificateLoader(),
            new NullTenantInvoiceStateStore()
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('authentication certificate');

        $pipeline->submit($invoice, $tenantConfig, 'reporting');
    }

    public function test_it_rejects_submission_when_only_compliance_csid_is_available(): void
    {
        $tenantConfig = TenantConfig::fromArray([
            'tenant_id' => 'tenant-1',
            'environment' => 'simulation',
            'seller_name' => 'Maaz Store',
            'seller_vat_number' => '300000000000003',
            'meta' => [
                'has_compliance_csid' => true,
                'has_production_csid' => false,
            ],
        ]);

        $invoice = Zatca::invoice()
            ->invoiceNumber('INV-COMPLIANCE-ONLY-1')
            ->seller(['name' => 'Maaz Store', 'vat_number' => '300000000000003'])
            ->addItem(['name' => 'Item', 'quantity' => 1, 'unit_price' => 100, 'tax_percent' => 15])
            ->generate();

        $pipeline = new SubmissionPipeline(
            new UblInvoiceBuilder(),
            new SignatureService(new CertificateLoader(), new ZatcaInvoiceHashGenerator()),
            new Phase2QrCodeService(new TlvEncoder(), new CertificateLoader(), new ZatcaInvoiceHashGenerator()),
            new class implements ApiClient {
                public function submit(array $payload, TenantConfig $tenantConfig, string $mode): array
                {
                    return ['success' => true];
                }
            },
            new ZatcaInvoiceHashGenerator(),
            new ZatcaLogger($this->app->make(LogManager::class)),
            new CertificateLoader(),
            new NullTenantInvoiceStateStore()
        );

        $this->expectException(ApiException::class);

        $pipeline->submit($invoice, $tenantConfig, 'reporting');
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
