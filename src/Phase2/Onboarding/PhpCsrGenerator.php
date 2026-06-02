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

        return PHP_OS_FAMILY === 'Windows'
            ? $this->generatePhp($dn, $properties, $simulation, $nonProduction)
            : $this->generateCli($dn, $properties, $simulation, $nonProduction);
    }

    protected function generatePhp(array $dn, array $properties, bool $simulation, bool $nonProduction): GeneratedCsrResult
    {
        $configFile = $this->writeOpensslConfig($dn, $properties);
        $keyConfig = ['config' => $this->writeKeyConfig()];

        try {
            $privateKey = openssl_pkey_new([
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name' => 'prime256v1',
                'config' => $keyConfig['config'],
            ]);

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

    protected function generateCli(array $dn, array $properties, bool $simulation, bool $nonProduction): GeneratedCsrResult
    {
        $keyFile = null;
        $csrFile = null;
        $configFile = $this->writeOpensslConfig($dn, $properties);

        try {
            $privateKey = openssl_pkey_new([
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name' => 'prime256v1',
            ]);

            if (! $privateKey) {
                throw new CsrException('Failed to generate EC private key: ' . openssl_error_string());
            }

            $keyFile = $this->writePrivateKeyFile($privateKey);
            $csrFile = $this->tempPath('php-csr-out-', '.pem');

            $subject = $this->buildSubjectString($dn);
            $command = [
                'openssl',
                'req',
                '-new',
                '-config', $configFile,
                '-key', $keyFile,
                '-out', $csrFile,
                '-subj', $subject,
                '-sha256',
            ];

            $result = $this->runCommand($command);

            if ($result['exit_code'] !== 0 || ! is_file($csrFile)) {
                $error = trim($result['stderr'] . PHP_EOL . $result['stdout']);
                throw new CsrException($error ?: 'CSR generation via OpenSSL CLI failed.');
            }

            $csrPem = trim((string) file_get_contents($csrFile)) . "\n";
            $privateKeyPem = $this->exportPrivateKeyPem($privateKey);
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
            if ($keyFile !== null && is_file($keyFile)) {
                @unlink($keyFile);
            }
            if ($csrFile !== null && is_file($csrFile)) {
                @unlink($csrFile);
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

        if (PHP_OS_FAMILY !== 'Windows') {
            $lines[] = '.include /etc/ssl/openssl.cnf';
        }

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

    protected function writePrivateKeyFile(\OpenSSLAsymmetricKey $privateKey): string
    {
        if (! openssl_pkey_export($privateKey, $pem)) {
            throw new CsrException('Failed to export private key PEM: ' . openssl_error_string());
        }

        $path = $this->tempPath('php-key-', '.pem');
        file_put_contents($path, $pem);

        return $path;
    }

    protected function exportPrivateKeyPem(\OpenSSLAsymmetricKey $privateKey): string
    {
        if (! openssl_pkey_export($privateKey, $pem)) {
            throw new CsrException('Failed to export private key PEM: ' . openssl_error_string());
        }

        return $pem;
    }

    protected function buildSubjectString(array $dn): string
    {
        $parts = [];

        foreach ($dn as $key => $value) {
            $shortName = match ($key) {
                'commonName' => 'CN',
                'organizationName' => 'O',
                'organizationalUnitName' => 'OU',
                'countryName' => 'C',
                'stateOrProvinceName' => 'ST',
                'localityName' => 'L',
                default => $key,
            };
            $parts[] = '/' . $shortName . '=' . $value;
        }

        return implode('', $parts);
    }

    protected function tempPath(string $prefix, string $suffix): string
    {
        return tempnam(sys_get_temp_dir(), $prefix) . $suffix;
    }

    /**
     * @param array<int, string> $command
     * @return array{stdout: string, stderr: string, exit_code: int}
     */
    protected function runCommand(array $command): array
    {
        $process = proc_open(
            implode(' ', array_map(static fn (string $arg): string => escapeshellarg($arg), $command)),
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (! is_resource($process)) {
            throw new CsrException('Unable to start openssl process.');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
            'exit_code' => proc_close($process),
        ];
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
