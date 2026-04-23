<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Facades\Zatca;
use Maaz\LaravelZatca\Phase2\Hashing\ZatcaInvoiceHashGenerator;
use Maaz\LaravelZatca\Phase2\Signatures\SignatureService;
use Maaz\LaravelZatca\Support\CertificateLoader;
use Maaz\LaravelZatca\Tests\TestCase;

class SignatureServiceTest extends TestCase
{
    private const VALID_PIH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    public function test_it_generates_a_ubl_xades_signature_envelope(): void
    {
        [$privateKey, $certificate] = $this->generateCertificatePair();

        $invoice = Zatca::invoice()
            ->invoiceNumber('INV-SIGN-1')
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

        $xml = Zatca::generateXml($invoice);
        $signer = new SignatureService(new CertificateLoader(), new ZatcaInvoiceHashGenerator());

        $signedXml = $signer->sign($xml, TenantConfig::fromArray([
            'tenant_id' => 'tenant-1',
            'environment' => 'sandbox',
            'seller_name' => 'Maaz Store',
            'seller_vat_number' => '300000000000003',
            'certificates' => [
                'private_key' => $privateKey,
                'certificate' => $certificate,
            ],
        ]));

        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->loadXML($signedXml);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('invoice', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $xpath->registerNamespace('sig', 'urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2');
        $xpath->registerNamespace('sac', 'urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2');
        $xpath->registerNamespace('sbc', 'urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $xpath->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');

        $this->assertSame('UBLExtensions', $document->documentElement?->firstChild?->localName);
        $this->assertSame('urn:oasis:names:specification:ubl:dsig:enveloped:xades', $xpath->evaluate('string(/invoice:Invoice/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionURI)'));
        $this->assertSame('urn:oasis:names:specification:ubl:signature:1', $xpath->evaluate('string(//sig:UBLDocumentSignatures/sac:SignatureInformation/cbc:ID)'));
        $this->assertSame('urn:oasis:names:specification:ubl:signature:Invoice', $xpath->evaluate('string(//sac:SignatureInformation/sbc:ReferencedSignatureID)'));
        $this->assertSame('http://www.w3.org/2006/12/xml-c14n11', $xpath->evaluate('string(//ds:SignedInfo/ds:CanonicalizationMethod/@Algorithm)'));
        $this->assertSame('http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256', $xpath->evaluate('string(//ds:SignedInfo/ds:SignatureMethod/@Algorithm)'));
        $this->assertSame('invoiceSignedData', $xpath->evaluate('string(//ds:Reference[@URI=""]/@Id)'));
        $this->assertSame('#xadesSignedProperties', $xpath->evaluate('string(//ds:Reference[@Type="http://www.w3.org/2000/09/xmldsig#SignatureProperties"]/@URI)'));
        $this->assertSame('not(//ancestor-or-self::ext:UBLExtensions)', $xpath->evaluate('string((//ds:Reference[@URI=""]/ds:Transforms/ds:Transform/ds:XPath)[1])'));
        $this->assertSame("not(//ancestor-or-self::cac:AdditionalDocumentReference[cbc:ID='QR'])", $xpath->evaluate('string((//ds:Reference[@URI=""]/ds:Transforms/ds:Transform/ds:XPath)[3])'));
        $this->assertSame($this->normalizeCertificate($certificate), $xpath->evaluate('string(//ds:X509Certificate)'));
        $this->assertSame(
            'CN=PRZEINVOICESCA4-CA, DC=extgazt, DC=gov, DC=local',
            $xpath->evaluate('string(//xades:IssuerSerial/ds:X509IssuerName)')
        );
        $this->assertSame(
            '379112742831380471835263969587287663520528387',
            $xpath->evaluate('string(//xades:IssuerSerial/ds:X509SerialNumber)')
        );
        $this->assertSame(
            base64_encode(hash('sha256', $this->normalizeCertificate($certificate))),
            $xpath->evaluate('string(//xades:CertDigest/ds:DigestValue)')
        );
        $this->assertNotSame('', $xpath->evaluate('string(//xades:SignedProperties[@Id="xadesSignedProperties"]/xades:SignedSignatureProperties/xades:SigningTime)'));
        $this->assertNotFalse(base64_decode($xpath->evaluate('string(//ds:SignatureValue)'), true));
        $this->assertNotFalse(base64_decode($xpath->evaluate('string(//xades:CertDigest/ds:DigestValue)'), true));

        $signedInfo = $xpath->query('//ds:SignedInfo')->item(0);
        $publicKey = openssl_pkey_get_public($this->pemCertificate($certificate));
        $signatureValue = base64_decode($xpath->evaluate('string(//ds:SignatureValue)'), true);

        $this->assertNotFalse($signatureValue);
        $this->assertNotFalse($publicKey);
        $this->assertSame(1, openssl_verify($signedInfo->C14N(false, false), $signatureValue, $publicKey, OPENSSL_ALGO_SHA256));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function generateCertificatePair(): array
    {
        $certificatePath = dirname(__DIR__, 2) . '/.net sdk/zatca-einvoicing-sdk-DotNet-238-R3.4.8/Data/Certificates/cert.pem';
        $privateKeyPath = dirname(__DIR__, 2) . '/.net sdk/zatca-einvoicing-sdk-DotNet-238-R3.4.8/Data/Certificates/ec-secp256k1-priv-key.pem';

        if (! is_file($certificatePath) || ! is_file($privateKeyPath)) {
            $this->markTestSkipped('Official SDK certificate fixtures are not available.');
        }

        $privateKey = file_get_contents($privateKeyPath);
        $certificate = file_get_contents($certificatePath);

        $this->assertIsString($privateKey);
        $this->assertIsString($certificate);

        return [$privateKey, $certificate];
    }

    private function normalizeCertificate(string $certificate): string
    {
        return str_replace(
            ["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n"],
            '',
            $certificate
        );
    }

    private function pemCertificate(string $certificate): string
    {
        if (str_contains($certificate, 'BEGIN CERTIFICATE')) {
            return $certificate;
        }

        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(preg_replace('/\s+/', '', $certificate) ?? '', 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }
}
