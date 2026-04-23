<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Phase2\Onboarding;

use Maaz\LaravelZatca\Contracts\CsrGenerator;
use Maaz\LaravelZatca\DTOs\GeneratedCsrResult;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Exceptions\CsrException;

class SdkCsrGenerator implements CsrGenerator
{
    /**
     * @var array<string, string>
     */
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

    /**
     * @param array{path?: string|null, cli_path?: string|null} $sdkConfig
     */
    public function __construct(
        protected array $sdkConfig = []
    ) {
    }

    public function generate(array $payload, TenantConfig $tenantConfig): GeneratedCsrResult
    {
        $cliPath = $this->resolveCliPath();
        $workspace = $this->makeWorkspace();
        $cleanupConfigPath = null;

        try {
            [$configPath, $properties, $cleanupConfigPath] = $this->resolveConfig($payload, $tenantConfig, $workspace);
            $rawOutput = (bool) ($payload['raw'] ?? false);
            $simulation = (bool) ($payload['simulation'] ?? false);
            $nonProduction = (bool) ($payload['non_production'] ?? false);

            if ($simulation && $nonProduction) {
                throw new CsrException('Only one CSR environment flag may be used at a time: simulation or non-production.');
            }

            $csrPath = $this->resolveOutputPath(
                $payload['generated_csr_path'] ?? $payload['generated_csr'] ?? null,
                $this->defaultOutputFileName($rawOutput ? 'generated-csr' : 'generated-csr', $rawOutput ? 'txt' : 'csr')
            );
            $privateKeyPath = $this->resolveOutputPath(
                $payload['private_key_path'] ?? $payload['private_key'] ?? null,
                $this->defaultOutputFileName('generated-private-key', $rawOutput ? 'txt' : 'pem')
            );

            $command = [
                ...$this->sdkCommandPrefix($cliPath),
                'csr',
                '-csrConfig',
                $configPath,
                '-generatedCsr',
                $csrPath,
                '-privateKey',
                $privateKeyPath,
            ];

            if (! $rawOutput) {
                $command[] = '-pem';
            }

            if ($simulation) {
                $command[] = '-sim';
            }

            if ($nonProduction) {
                $command[] = '-nonprod';
            }

            $result = $this->runSdkCommand($command, dirname($cliPath));

            if ($result['exit_code'] !== 0 || ! is_file($csrPath) || ! is_file($privateKeyPath)) {
                throw new CsrException(trim($result['stderr'] . PHP_EOL . $result['stdout']) ?: (string) trans('zatca::exceptions.csr_generation_failed'));
            }

            $csrContents = $this->readRequiredFile($csrPath, 'CSR');
            $privateKeyContents = $this->readRequiredFile($privateKeyPath, 'private key');

            $csrPem = $this->normalizeCsrPem($csrContents);
            $privateKeyPem = $this->normalizePrivateKeyPem($privateKeyContents);

            return new GeneratedCsrResult(
                csrPath: $csrPath,
                privateKeyPath: $privateKeyPath,
                csrBase64: $this->normalizeCsrBase64($csrPem),
                csrPem: $csrPem,
                privateKeyPem: $privateKeyPem,
                properties: $properties,
                configPath: $this->resolveReturnedConfigPath($payload, $cleanupConfigPath),
                rawOutput: $rawOutput,
                simulation: $simulation,
                nonProduction: $nonProduction
            );
        } finally {
            if ($cleanupConfigPath !== null && is_file($cleanupConfigPath)) {
                @unlink($cleanupConfigPath);
            }

            $this->cleanupWorkspace($workspace);
        }
    }

    protected function resolveCliPath(): string
    {
        $configuredPath = $this->sdkConfig['cli_path'] ?? null;

        if (is_string($configuredPath) && $configuredPath !== '' && is_file($configuredPath)) {
            return $configuredPath;
        }

        $sdkPath = $this->sdkConfig['path'] ?? null;

        if (! is_string($sdkPath) || trim($sdkPath) === '') {
            throw new CsrException((string) trans('zatca::exceptions.sdk_path_missing'));
        }

        $directory = $sdkPath . DIRECTORY_SEPARATOR . 'Lib' . DIRECTORY_SEPARATOR . 'Dot-Net8' . DIRECTORY_SEPARATOR . 'Test';
        $candidates = PHP_OS_FAMILY === 'Windows'
            ? [$directory . DIRECTORY_SEPARATOR . 'fatooraNet.exe', $directory . DIRECTORY_SEPARATOR . 'fatooraNet.dll']
            : [$directory . DIRECTORY_SEPARATOR . 'fatooraNet.dll', $directory . DIRECTORY_SEPARATOR . 'fatooraNet.exe'];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new CsrException((string) trans('zatca::exceptions.sdk_cli_not_found'));
    }

    /**
     * @return array{0: string, 1: array<string, string>, 2: string|null}
     */
    protected function resolveConfig(array $payload, TenantConfig $tenantConfig, string $workspace): array
    {
        $existingConfigPath = $this->resolveExistingConfigPath($payload['config_path'] ?? $payload['config'] ?? null);

        if ($existingConfigPath !== null) {
            return [$existingConfigPath, $this->parsePropertiesFile($existingConfigPath), null];
        }

        $properties = $this->buildProperties($payload, $tenantConfig);
        $configContents = $this->renderPropertiesFile($properties);
        $saveConfigPath = $this->resolveOptionalPath($payload['save_config_path'] ?? $payload['save_config'] ?? null);

        if ($saveConfigPath !== null) {
            $this->ensureParentDirectory(dirname($saveConfigPath));
            file_put_contents($saveConfigPath, $configContents);

            return [$saveConfigPath, $properties, null];
        }

        $configPath = $workspace . DIRECTORY_SEPARATOR . 'csr-config.properties';
        file_put_contents($configPath, $configContents);

        return [$configPath, $properties, $configPath];
    }

    /**
     * @return array<string, string>
     */
    protected function buildProperties(array $payload, TenantConfig $tenantConfig): array
    {
        $properties = [
            'common_name' => $this->requireProperty($payload['common_name'] ?? null, 'common_name'),
            'serial_number' => $this->requireProperty($payload['serial_number'] ?? null, 'serial_number'),
            'organization_identifier' => $this->requireProperty($payload['organization_identifier'] ?? $tenantConfig->sellerVatNumber, 'organization_identifier'),
            'organization_unit_name' => $this->requireProperty($payload['organization_unit_name'] ?? $tenantConfig->branchName, 'organization_unit_name'),
            'organization_name' => $this->requireProperty($payload['organization_name'] ?? $tenantConfig->sellerName, 'organization_name'),
            'country_name' => $this->requireProperty($payload['country_name'] ?? 'SA', 'country_name'),
            'invoice_type' => $this->requireProperty($payload['invoice_type'] ?? '1100', 'invoice_type'),
            'location_address' => $this->requireProperty($payload['location_address'] ?? null, 'location_address'),
            'industry_business_category' => $this->requireProperty($payload['industry_business_category'] ?? null, 'industry_business_category'),
        ];

        $mapped = [];

        foreach (self::PROPERTY_MAP as $inputKey => $propertyKey) {
            $mapped[$propertyKey] = $properties[$inputKey];
        }

        return $mapped;
    }

    protected function requireProperty(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new CsrException((string) trans('zatca::exceptions.csr_missing_field', ['field' => $field]));
        }

        return trim($value);
    }

    protected function resolveExistingConfigPath(mixed $path): ?string
    {
        $resolvedPath = $this->resolveOptionalPath($path);

        if ($resolvedPath === null) {
            return null;
        }

        if (! is_file($resolvedPath) || ! is_readable($resolvedPath)) {
            throw new CsrException((string) trans('zatca::exceptions.csr_config_unreadable', ['path' => $resolvedPath]));
        }

        return $resolvedPath;
    }

    protected function resolveOutputPath(mixed $path, string $defaultFileName): string
    {
        $resolvedPath = $this->resolveOptionalPath($path);

        if ($resolvedPath === null) {
            $directory = storage_path('app/private/zatca/onboarding');
            $resolvedPath = $directory . DIRECTORY_SEPARATOR . $defaultFileName;
        }

        $this->ensureParentDirectory(dirname($resolvedPath));

        return $resolvedPath;
    }

    protected function defaultOutputFileName(string $prefix, string $extension): string
    {
        return sprintf(
            '%s-%s.%s',
            $prefix,
            gmdate('Ymd_His') . '-' . bin2hex(random_bytes(4)),
            $extension
        );
    }

    protected function ensureParentDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new CsrException('Unable to create directory for generated CSR assets: ' . $directory);
        }
    }

    protected function resolveOptionalPath(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (! $this->isAbsolutePath($path)) {
            $path = base_path($path);
        }

        return $path;
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    protected function renderPropertiesFile(array $properties): string
    {
        $lines = [];

        foreach (self::PROPERTY_MAP as $propertyKey) {
            $lines[] = $propertyKey . '=' . ($properties[$propertyKey] ?? '');
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @return array<string, string>
     */
    protected function parsePropertiesFile(string $path): array
    {
        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new CsrException((string) trans('zatca::exceptions.csr_config_unreadable', ['path' => $path]));
        }

        $properties = [];

        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $properties[trim($key)] = trim($value);
        }

        return $properties;
    }

    protected function resolveReturnedConfigPath(array $payload, ?string $cleanupConfigPath): ?string
    {
        if ($cleanupConfigPath !== null) {
            return null;
        }

        return $this->resolveOptionalPath($payload['save_config_path'] ?? $payload['save_config'] ?? $payload['config_path'] ?? $payload['config'] ?? null);
    }

    protected function readRequiredFile(string $path, string $label): string
    {
        $contents = file_get_contents($path);

        if (! is_string($contents) || trim($contents) === '') {
            throw new CsrException((string) trans('zatca::exceptions.csr_output_unreadable', [
                'label' => $label,
                'path' => $path,
            ]));
        }

        return trim($contents);
    }

    protected function normalizeCsrPem(string $csr): string
    {
        $csr = trim($csr);

        if (str_contains($csr, 'BEGIN CERTIFICATE REQUEST')) {
            return $csr;
        }

        return "-----BEGIN CERTIFICATE REQUEST-----\n"
            . chunk_split(preg_replace('/\s+/', '', $csr) ?? '', 64, "\n")
            . "-----END CERTIFICATE REQUEST-----\n";
    }

    protected function normalizePrivateKeyPem(string $privateKey): string
    {
        $privateKey = trim($privateKey);

        if (str_contains($privateKey, 'BEGIN')) {
            return $privateKey;
        }

        return "-----BEGIN EC PRIVATE KEY-----\n"
            . chunk_split(preg_replace('/\s+/', '', $privateKey) ?? '', 64, "\n")
            . "-----END EC PRIVATE KEY-----\n";
    }

    protected function normalizeLineEndings(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", trim($value)) . "\n";
    }

    protected function normalizeCsrBase64(string $csrPem): string
    {
        $normalized = $this->normalizeLineEndings($csrPem);
        $stripped = preg_replace('/-----BEGIN CERTIFICATE REQUEST-----|-----END CERTIFICATE REQUEST-----|\s+/', '', $normalized);

        if (! is_string($stripped) || trim($stripped) === '') {
            throw new CsrException((string) trans('zatca::exceptions.csr_generation_failed'));
        }

        return trim($stripped);
    }

    protected function makeWorkspace(): string
    {
        $workspace = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laravel-zatca-csr-' . bin2hex(random_bytes(8));

        if (! @mkdir($workspace, 0777, true) && ! is_dir($workspace)) {
            throw new CsrException('Unable to create temporary workspace for CSR generation.');
        }

        return $workspace;
    }

    protected function cleanupWorkspace(string $workspace): void
    {
        if (! is_dir($workspace)) {
            return;
        }

        foreach (glob($workspace . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($workspace);
    }

    /**
     * @return array<int, string>
     */
    protected function sdkCommandPrefix(string $cliPath): array
    {
        return str_ends_with(strtolower($cliPath), '.dll')
            ? ['dotnet', $cliPath]
            : [$cliPath];
    }

    /**
     * @param array<int, string> $command
     *
     * @return array{stdout: string, stderr: string, exit_code: int}
     */
    protected function runSdkCommand(array $command, string $workingDirectory): array
    {
        $process = proc_open(
            implode(' ', array_map(static fn (string $argument): string => escapeshellarg($argument), $command)),
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $workingDirectory
        );

        if (! is_resource($process)) {
            throw new CsrException('Unable to start the official SDK CSR process.');
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
}
