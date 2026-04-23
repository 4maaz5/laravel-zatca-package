<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Phase2\Signatures\SdkSignatureService;
use Maaz\LaravelZatca\Support\CertificateLoader;
use Maaz\LaravelZatca\Tests\TestCase;
use ReflectionMethod;

class SdkSignatureServiceTest extends TestCase
{
    public function test_it_normalizes_double_encoded_binary_security_tokens_for_sdk_certificate_files(): void
    {
        [, $certificate] = $this->sdkCertificatePair();
        $token = base64_encode((string) preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $certificate));

        $service = new SdkSignatureService([], new CertificateLoader());
        $tenantConfig = TenantConfig::fromArray([
            'tenant_id' => 'tenant-1',
            'environment' => 'sandbox',
            'seller_name' => 'BI Technology Company',
            'seller_vat_number' => '313138851500003',
            'certificates' => [
                'certificate' => $token,
                'private_key' => $this->sdkCertificatePair()[0],
            ],
        ]);

        $workspace = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sdk-signer-test-' . bin2hex(random_bytes(5));
        mkdir($workspace, 0777, true);

        $method = new ReflectionMethod($service, 'resolveCertificatePath');
        $method->setAccessible(true);

        [$certificatePath] = $method->invoke($service, $tenantConfig, $workspace);

        $writtenCertificate = file_get_contents($certificatePath);

        $this->assertIsString($writtenCertificate);
        $this->assertStringNotContainsString('BEGIN CERTIFICATE', $writtenCertificate);
        $this->assertSame(
            preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $certificate),
            $writtenCertificate
        );
        $this->assertNotFalse(openssl_x509_read((new CertificateLoader())->normalizeCertificateString($writtenCertificate)));

        @unlink($certificatePath);
        @rmdir($workspace);
    }

    public function test_it_writes_sdk_generated_private_keys_without_reexporting_them(): void
    {
        [$privateKey] = $this->sdkCertificatePair();

        $service = new SdkSignatureService([], new CertificateLoader());
        $tenantConfig = TenantConfig::fromArray([
            'tenant_id' => 'tenant-1',
            'environment' => 'sandbox',
            'seller_name' => 'BI Technology Company',
            'seller_vat_number' => '313138851500003',
            'certificates' => [
                'private_key' => $privateKey,
            ],
        ]);

        $workspace = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sdk-signer-key-test-' . bin2hex(random_bytes(5));
        mkdir($workspace, 0777, true);

        $method = new ReflectionMethod($service, 'resolvePrivateKeyPath');
        $method->setAccessible(true);

        [$privateKeyPath] = $method->invoke($service, $tenantConfig, $workspace);

        $writtenPrivateKey = file_get_contents($privateKeyPath);

        $this->assertIsString($writtenPrivateKey);
        $this->assertStringContainsString('BEGIN', $writtenPrivateKey);
        $this->assertStringContainsString('PRIVATE KEY', $writtenPrivateKey);

        @unlink($privateKeyPath);
        @rmdir($workspace);
    }

    public function test_it_accepts_base64_encoded_sdk_generated_private_keys(): void
    {
        [$privateKey] = $this->sdkCertificatePair();

        $service = new SdkSignatureService([], new CertificateLoader());
        $tenantConfig = TenantConfig::fromArray([
            'tenant_id' => 'tenant-1',
            'environment' => 'sandbox',
            'seller_name' => 'BI Technology Company',
            'seller_vat_number' => '313138851500003',
            'certificates' => [
                'private_key' => base64_encode($privateKey),
            ],
        ]);

        $workspace = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sdk-signer-key-b64-test-' . bin2hex(random_bytes(5));
        mkdir($workspace, 0777, true);

        $method = new ReflectionMethod($service, 'resolvePrivateKeyPath');
        $method->setAccessible(true);

        [$privateKeyPath] = $method->invoke($service, $tenantConfig, $workspace);

        $writtenPrivateKey = file_get_contents($privateKeyPath);

        $this->assertIsString($writtenPrivateKey);
        $this->assertStringContainsString('BEGIN', $writtenPrivateKey);
        $this->assertStringContainsString('PRIVATE KEY', $writtenPrivateKey);

        @unlink($privateKeyPath);
        @rmdir($workspace);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function sdkCertificatePair(): array
    {
        $certificatePath = dirname(__DIR__, 2) . '/.net sdk/zatca-einvoicing-sdk-DotNet-238-R3.4.8/Data/Certificates/cert.pem';
        $privateKeyPath = dirname(__DIR__, 2) . '/.net sdk/zatca-einvoicing-sdk-DotNet-238-R3.4.8/Data/Certificates/ec-secp256k1-priv-key.pem';

        return [
            (string) file_get_contents($privateKeyPath),
            (string) file_get_contents($certificatePath),
        ];
    }
}
