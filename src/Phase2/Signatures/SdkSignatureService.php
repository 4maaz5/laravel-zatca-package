<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Phase2\Signatures;

use Maaz\LaravelZatca\Contracts\InvoiceSigner;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Exceptions\CertificateException;
use Maaz\LaravelZatca\Exceptions\SignatureException;
use Maaz\LaravelZatca\Support\CertificateLoader;

class SdkSignatureService implements InvoiceSigner
{
    /**
     * @param array{path?: string|null, cli_path?: string|null} $sdkConfig
     */
    public function __construct(
        protected array $sdkConfig = [],
        protected ?CertificateLoader $certificateLoader = null
    ) {
        $this->certificateLoader ??= new CertificateLoader();
    }

    public function sign(string $xml, TenantConfig $tenantConfig): string
    {
        $cliPath = $this->resolveCliPath();
        $workspace = $this->makeWorkspace();

        try {
            $invoicePath = $workspace . DIRECTORY_SEPARATOR . 'invoice.xml';
            $signedInvoicePath = $workspace . DIRECTORY_SEPARATOR . 'signed-invoice.xml';

            file_put_contents($invoicePath, $xml);

            [$certificatePath, $deleteCertificate] = $this->resolveCertificatePath($tenantConfig, $workspace);
            [$privateKeyPath, $deletePrivateKey] = $this->resolvePrivateKeyPath($tenantConfig, $workspace);

            $result = $this->runSdkCommand([
                ...$this->sdkCommandPrefix($cliPath),
                'sign',
                '-invoice',
                $invoicePath,
                '-signedInvoice',
                $signedInvoicePath,
                '-certificate',
                $certificatePath,
                '-privateKey',
                $privateKeyPath,
            ], dirname($cliPath));

            if ($result['exit_code'] !== 0 || ! is_file($signedInvoicePath)) {
                throw new SignatureException(trim($result['stderr'] . PHP_EOL . $result['stdout']) ?: (string) trans('zatca::exceptions.signature_failed'));
            }

            $signedXml = file_get_contents($signedInvoicePath);

            if (! is_string($signedXml) || trim($signedXml) === '') {
                throw new SignatureException((string) trans('zatca::exceptions.signature_render_failed'));
            }

            if ($deleteCertificate && is_file($certificatePath)) {
                @unlink($certificatePath);
            }

            if ($deletePrivateKey && is_file($privateKeyPath)) {
                @unlink($privateKeyPath);
            }

            return $signedXml;
        } finally {
            $this->cleanupWorkspace($workspace);
        }
    }

    private function resolveCliPath(): string
    {
        $configuredPath = $this->sdkConfig['cli_path'] ?? null;

        if (is_string($configuredPath) && $configuredPath !== '' && is_file($configuredPath)) {
            return $configuredPath;
        }

        $sdkPath = $this->sdkConfig['path'] ?? null;

        if (! is_string($sdkPath) || $sdkPath === '') {
            throw new SignatureException('Official SDK signer is enabled, but no SDK path is configured.');
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

        throw new SignatureException('Official SDK signer is enabled, but the SDK CLI was not found.');
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function resolveCertificatePath(TenantConfig $tenantConfig, string $workspace): array
    {
        $path = $tenantConfig->certificates['certificate_path'] ?? null;

        if (is_string($path) && $path !== '' && is_file($path)) {
            return [$path, false];
        }

        try {
            $normalized = $this->certificateLoader?->loadCertificate($tenantConfig);
        } catch (CertificateException $exception) {
            throw new SignatureException($exception->getMessage(), previous: $exception);
        }

        if (! is_string($normalized) || trim($normalized) === '') {
            throw new SignatureException((string) trans('zatca::exceptions.certificate_invalid_certificate'));
        }

        $certificatePath = $workspace . DIRECTORY_SEPARATOR . 'certificate.pem';
        file_put_contents($certificatePath, $this->normalizeCertificateForSdk($normalized));

        return [$certificatePath, true];
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function resolvePrivateKeyPath(TenantConfig $tenantConfig, string $workspace): array
    {
        $privateKey = $tenantConfig->certificates['private_key'] ?? null;

        if (is_string($path = $tenantConfig->certificates['private_key_path'] ?? null) && $path !== '' && is_file($path)) {
            return [$path, false];
        }

        if (! is_string($privateKey) || trim($privateKey) === '') {
            throw new SignatureException((string) trans('zatca::exceptions.certificate_missing_private_key'));
        }

        $pem = $this->normalizePrivateKeyForSdk($privateKey);

        $privateKeyPath = $workspace . DIRECTORY_SEPARATOR . 'private-key.pem';
        file_put_contents($privateKeyPath, $pem);

        return [$privateKeyPath, true];
    }

    private function normalizeCertificateForSdk(string $certificate): string
    {
        return trim((string) preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $certificate));
    }

    private function normalizePrivateKeyForSdk(string $privateKey): string
    {
        $privateKey = trim($privateKey);

        if (str_contains($privateKey, 'BEGIN')) {
            return str_replace(["\r\n", "\r"], "\n", $privateKey);
        }

        $compact = preg_replace('/\s+/', '', $privateKey) ?? '';
        $decoded = base64_decode($compact, true);

        if (is_string($decoded) && trim($decoded) !== '') {
            $decoded = trim($decoded);

            if (str_contains($decoded, 'BEGIN')) {
                return str_replace(["\r\n", "\r"], "\n", $decoded);
            }

            $decodedCompact = preg_replace('/\s+/', '', $decoded) ?? '';

            return "-----BEGIN EC PRIVATE KEY-----\n"
                . chunk_split($decodedCompact, 64, "\n")
                . "-----END EC PRIVATE KEY-----\n";
        }

        return "-----BEGIN EC PRIVATE KEY-----\n"
            . chunk_split($compact, 64, "\n")
            . "-----END EC PRIVATE KEY-----\n";
    }

    private function makeWorkspace(): string
    {
        $workspace = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laravel-zatca-sign-' . bin2hex(random_bytes(8));

        if (! @mkdir($workspace, 0777, true) && ! is_dir($workspace)) {
            throw new SignatureException('Unable to create temporary workspace for the official SDK signer.');
        }

        return $workspace;
    }

    private function cleanupWorkspace(string $workspace): void
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
    private function sdkCommandPrefix(string $cliPath): array
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
    private function runSdkCommand(array $command, string $workingDirectory): array
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
            throw new SignatureException('Unable to start the official SDK signer process.');
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
