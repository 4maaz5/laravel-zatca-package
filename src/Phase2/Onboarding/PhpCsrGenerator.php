<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Phase2\Onboarding;

use Maaz\LaravelZatca\Contracts\CsrGenerator;
use Maaz\LaravelZatca\DTOs\GeneratedCsrResult;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Exceptions\CsrException;

class PhpCsrGenerator implements CsrGenerator
{
    protected const PROPERTY_MAP = [
        'common_name' => 'csr.common.name',
        'serial_number' => 'csr.serial.number',
        'organization_identifier' => 'csr.organization.identifier',
        'organization_unit_name' => 'csr.organization.unit.name',
        'organization_name' => 'csr.organization.name',
        'country_name' => 'csr.country.name',
        'invoice_type' => 'csr.invoice.type',
        'location_address' => 'csr.location.address',
        'industry_business_category' => 'csr.industry.business.category',
    ];

    public function generate(array $payload, TenantConfig $tenantConfig): GeneratedCsrResult
    {
        $simulation = (bool) ($payload['simulation'] ?? false);
        $nonProduction = (bool) ($payload['non_production'] ?? false);

        $dn = [
            'countryName' => $this->value($payload, 'country_name', 'SA'),
            'organizationalUnitName' => $this->value($payload, 'organization_unit_name', $tenantConfig->branchName),
            'organizationName' => $this->value($payload, 'organization_name', $tenantConfig->sellerName),
            'commonName' => $this->requireValue($payload, 'common_name'),
        ];

        $properties = $this->buildProperties($payload, $tenantConfig);
        $configFile = $this->writeOpensslConfig($dn, $properties);

        $keyConfig = PHP_OS_FAMILY === 'Windows' ? ['config' => $this->writeKeyConfig()] : [];

        try {
            $config = array_merge($keyConfig, [
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name' => 'prime256v1',
            ]);
            $privateKey = openssl_pkey_new($config);

            if (! $privateKey) {
                throw new CsrException('Failed to generate EC private key: ' . openssl_error_string());
            }

            $csr = openssl_csr_new($dn, $privateKey, [
                'config' => $configFile,
                'digest_alg' => 'sha256',
            ]);

            if (! $csr) {
                throw new CsrException('Failed to generate CSR: ' . openssl_error_string());
            }

            if (! openssl_csr_export($csr, $csrPem)) {
                throw new CsrException('Failed to export CSR PEM: ' . openssl_error_string());
            }

            if (! openssl_pkey_export($privateKey, $privateKeyPem, null, $keyConfig)) {
                throw new CsrException('Failed to export private key PEM: ' . openssl_error_string());
            }

            $csrPem = trim($csrPem) . "\n";
            $privateKeyPem = trim($privateKeyPem) . "\n";
            $csrBase64 = $this->extractBase64($csrPem);

            return new GeneratedCsrResult(
                csrPath: '',
                privateKeyPath: '',
                csrBase64: $csrBase64,
                csrPem: $csrPem,
                privateKeyPem: $privateKeyPem,
                properties: $properties,
                configPath: null,
                rawOutput: false,
                simulation: $simulation,
                nonProduction: $nonProduction,
            );
        } finally {
            if (isset($configFile) && is_file($configFile)) {
                @unlink($configFile);
            }

            if (isset($keyConfig['config']) && is_file($keyConfig['config'])) {
                @unlink($keyConfig['config']);
            }
        }
    }

    protected function writeOpensslConfig(array $dn, array $properties): string
    {
        $sanFields = [
            'serialNumber' => PHP_OS_FAMILY === 'Windows'
                ? str_replace(['|', ',', ';', '@', '_', '~'], '/', $properties['csr.serial.number'])
                : $properties['csr.serial.number'],
            'organizationIdentifier' => $properties['csr.organization.identifier'],
            'title' => $properties['csr.invoice.type'],
            'street' => $properties['csr.location.address'],
            'businessCategory' => $properties['csr.industry.business.category'],
        ];

        $lines = [];
        $lines[] = '[req]';
        $lines[] = 'distinguished_name = req_distinguished_name';
        $lines[] = 'req_extensions = v3_req';
        $lines[] = 'prompt = no';
        $lines[] = 'default_md = sha256';
        $lines[] = '';

        $lines[] = '[req_distinguished_name]';
        foreach ($dn as $key => $value) {
            $lines[] = "$key=" . $this->escapeValue($value);
        }
        $lines[] = '';

        $lines[] = '[v3_req]';
        $lines[] = '1.3.6.1.4.1.311.20.2=ASN1:UTF8String:PREZATCA-Code-Signing';
        $lines[] = 'subjectAltName=dirName:ZATCA_SAN';
        $lines[] = '';

        $lines[] = '[ZATCA_SAN]';
        foreach ($sanFields as $key => $value) {
            $lines[] = "$key=" . $this->escapeValue($value);
        }

        $path = tempnam(sys_get_temp_dir(), 'php-csr-') . '.cnf';
        file_put_contents($path, implode("\n", $lines) . "\n");

        return $path;
    }

    protected function writeKeyConfig(): string
    {
        $lines = [];
        $lines[] = '[openssl_init]';
        $lines[] = '';

        $path = tempnam(sys_get_temp_dir(), 'php-key-') . '.cnf';
        file_put_contents($path, implode("\n", $lines) . "\n");

        return $path;
    }

    protected function escapeValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    protected function extractBase64(string $pem): string
    {
        $stripped = preg_replace(
            '/-----BEGIN CERTIFICATE REQUEST-----|-----END CERTIFICATE REQUEST-----|\s+/',
            '',
            $pem
        );

        if (! is_string($stripped) || trim($stripped) === '') {
            throw new CsrException('Failed to extract base64 from generated CSR PEM.');
        }

        return trim($stripped);
    }

    protected function value(array $payload, string $key, mixed $default = null): string
    {
        return isset($payload[$key]) && is_string($payload[$key]) && trim($payload[$key]) !== ''
            ? trim($payload[$key])
            : (is_string($default) ? $default : '');
    }

    protected function requireValue(array $payload, string $key): string
    {
        $value = $this->value($payload, $key);

        if ($value === '') {
            throw new CsrException("Missing required CSR field: $key");
        }

        return $value;
    }

    protected function buildProperties(array $payload, TenantConfig $tenantConfig): array
    {
        $properties = [
            'common_name' => $this->requireValue($payload, 'common_name'),
            'serial_number' => $this->requireValue($payload, 'serial_number'),
            'organization_identifier' => $this->value($payload, 'organization_identifier', $tenantConfig->sellerVatNumber),
            'organization_unit_name' => $this->value($payload, 'organization_unit_name', $tenantConfig->branchName),
            'organization_name' => $this->value($payload, 'organization_name', $tenantConfig->sellerName),
            'country_name' => $this->value($payload, 'country_name', 'SA'),
            'invoice_type' => $this->value($payload, 'invoice_type', '1100'),
            'location_address' => $this->requireValue($payload, 'location_address'),
            'industry_business_category' => $this->requireValue($payload, 'industry_business_category'),
        ];

        $mapped = [];

        foreach (self::PROPERTY_MAP as $inputKey => $propertyKey) {
            $mapped[$propertyKey] = $properties[$inputKey];
        }

        return $mapped;
    }
}
