<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Commands;

use Illuminate\Console\Command;
use Maaz\LaravelZatca\Exceptions\ZatcaException;
use Maaz\LaravelZatca\Services\ZatcaManager;
use RuntimeException;

class RequestComplianceCsidCommand extends Command
{
    protected $signature = 'zatca:compliance-csid
        {otp : Sandbox OTP header value}
        {--tenant= : Tenant id or key}
        {--csr= : Base64 CSR string}
        {--csr-file= : CSR file path, PEM or base64}
        {--save= : Optional JSON file path to save the Compliance CSID response}
        {--show-response : Print the full JSON response body}';

    protected $description = 'Request a sandbox Compliance CSID using a generated CSR.';

    public function handle(): int
    {
        try {
            $manager = $this->resolveManager();
            $csr = $this->resolveCsrPayload();
            $result = $manager->onboardComplianceCsid([
                'otp' => (string) $this->argument('otp'),
                'csr' => $csr,
            ]);
        } catch (RuntimeException|ZatcaException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $body = is_array($result['body'] ?? null) ? $result['body'] : [];

        if (! ($result['success'] ?? false)) {
            $this->components->error((string) trans('zatca::commands.compliance_csid_failed'));
            $this->line('HTTP status: ' . (string) ($result['status_code'] ?? 'unknown'));

            if ((bool) $this->option('show-response')) {
                $this->newLine();
                $this->line(json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: (string) ($result['body'] ?? ''));
            }

            return self::FAILURE;
        }

        $savePath = $this->saveResponseIfRequested($result);

        $this->components->info((string) trans('zatca::commands.compliance_csid_complete'));
        $this->line('HTTP status: ' . (string) ($result['status_code'] ?? 'unknown'));
        $this->line('Request ID: ' . (string) ($body['requestID'] ?? '[missing]'));
        $this->line('Disposition: ' . (string) ($body['dispositionMessage'] ?? '[missing]'));
        $this->line('Binary security token: ' . (string) ($body['binarySecurityToken'] ?? '[missing]'));
        $this->line('Secret: ' . (string) ($body['secret'] ?? '[missing]'));

        if ($savePath !== null) {
            $this->line('Saved response: ' . $savePath);
        }

        if ((bool) $this->option('show-response')) {
            $this->newLine();
            $this->line(json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: (string) ($result['body'] ?? ''));
        }

        return self::SUCCESS;
    }

    private function resolveManager(): ZatcaManager
    {
        $manager = $this->laravel->make(ZatcaManager::class);
        $tenant = $this->stringOption('tenant');

        return $tenant === null ? $manager : $manager->forTenant($tenant);
    }

    private function resolveCsrPayload(): string
    {
        $inline = $this->stringOption('csr');
        $file = $this->stringOption('csr-file');

        if ($inline === null && $file === null) {
            throw new RuntimeException((string) trans('zatca::exceptions.csr_missing_input'));
        }

        if ($inline !== null && $file !== null) {
            throw new RuntimeException((string) trans('zatca::exceptions.csr_ambiguous_input'));
        }

        if ($inline !== null) {
            return $this->normalizeCsr(trim($inline));
        }

        $path = $this->resolvePath($file);

        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException((string) trans('zatca::exceptions.csr_config_unreadable', ['path' => $file ?? '[missing]']));
        }

        $contents = file_get_contents($path);

        if (! is_string($contents) || trim($contents) === '') {
            throw new RuntimeException((string) trans('zatca::exceptions.csr_output_unreadable', [
                'label' => 'CSR',
                'path' => $path,
            ]));
        }

        return $this->normalizeCsr($contents);
    }

    private function normalizeCsr(string $csr): string
    {
        $trimmed = trim($csr);

        if (str_contains($trimmed, 'BEGIN CERTIFICATE REQUEST')) {
            $pem = str_replace(["\r\n", "\r"], "\n", $trimmed) . "\n";

            return base64_encode($pem);
        }

        return preg_replace('/\s+/', '', $trimmed) ?? '';
    }

    private function saveResponseIfRequested(array $result): ?string
    {
        $path = $this->resolvePath($this->stringOption('save'));

        if ($path === null) {
            return null;
        }

        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the response output directory: ' . $directory);
        }

        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($json) || file_put_contents($path, $json) === false) {
            throw new RuntimeException('Unable to save the Compliance CSID response to: ' . $path);
        }

        return $path;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function resolvePath(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (! $this->isAbsolutePath($path)) {
            $path = base_path($path);
        }

        return $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }
}
