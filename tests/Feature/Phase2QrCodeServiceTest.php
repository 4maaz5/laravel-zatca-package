<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Facades\Zatca;
use Maaz\LaravelZatca\Phase1\Encoders\TlvEncoder;
use Maaz\LaravelZatca\Phase2\Hashing\ZatcaInvoiceHashGenerator;
use Maaz\LaravelZatca\Phase2\Qr\Phase2QrCodeService;
use Maaz\LaravelZatca\Phase2\Signatures\SignatureService;
use Maaz\LaravelZatca\Support\CertificateLoader;
use Maaz\LaravelZatca\Tests\TestCase;

class Phase2QrCodeServiceTest extends TestCase
{
    private const VALID_PIH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    public function test_it_generates_phase_2_qr_with_cryptographic_tags(): void
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
            ->invoiceNumber('INV-QR2-1')
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
        $signedXml = (new SignatureService(new CertificateLoader(), $hashGenerator))->sign(
            Zatca::generateXml($invoice),
            $tenantConfig
        );
        $invoiceHash = $hashGenerator->generate($signedXml);

        $qr = (new Phase2QrCodeService(new TlvEncoder(), new CertificateLoader(), $hashGenerator))->generate(
            $invoice,
            $tenantConfig,
            $signedXml,
            $invoiceHash
        );

        $fields = $this->decodeTlv($qr);

        $this->assertSame('Maaz Store', $fields[1]);
        $this->assertSame('300000000000003', $fields[2]);
        $this->assertSame('2026-04-13T10:30:00+03:00', $fields[3]);
        $this->assertSame('115.00', $fields[4]);
        $this->assertSame('15.00', $fields[5]);
        $this->assertSame($invoiceHash, $fields[6]);
        $this->assertNotFalse(base64_decode($fields[7], true));
        $this->assertSame("\x30", $fields[8][0]);
        $this->assertSame("\x30", $fields[9][0]);
        $this->assertGreaterThan(50, strlen($fields[8]));
        $this->assertGreaterThan(50, strlen($fields[9]));
    }

    /**
     * @return array<int, string>
     */
    private function decodeTlv(string $qr): array
    {
        $payload = base64_decode($qr, true);

        $this->assertNotFalse($payload);

        $fields = [];
        $offset = 0;
        $length = strlen($payload);

        while ($offset < $length) {
            $tag = ord($payload[$offset]);
            $valueLength = ord($payload[$offset + 1]);
            $fields[$tag] = substr($payload, $offset + 2, $valueLength);
            $offset += 2 + $valueLength;
        }

        return $fields;
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
