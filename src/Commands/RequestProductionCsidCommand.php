<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Commands;

use Illuminate\Console\Command;
use Maaz\LaravelZatca\Exceptions\ZatcaException;
use Maaz\LaravelZatca\Services\ZatcaManager;
use RuntimeException;

class RequestProductionCsidCommand extends Command
{
    protected $signature = 'zatca:production-csid
        {--tenant= : Tenant id or key}
        {--compliance-response= : Saved Compliance CSID response JSON path}
        {--request-id= : Compliance request ID}
        {--binary-security-token= : Compliance binary security token}
        {--secret= : Compliance secret}
        {--save= : Optional JSON file path to save the Production CSID response}
        {--show-response : Print the full JSON response body}';

    protected $description = 'Request a sandbox Production CSID using Compliance CSID credentials.';

    public function handle(): int
    {
        try {
            $manager = $this->resolveManager();
            $payload = $this->resolvePayload();
            $result = $manager->onboardProductionCsid($payload);
        } catch (RuntimeException|ZatcaException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $body = is_array($result['body'] ?? null) ? $result['body'] : [];

        if (! ($result['success'] ?? false)) {
            $this->components->error((string) trans('zatca::commands.production_csid_failed'));
            $this->line('HTTP status: ' . (string) ($result['status_code'] ?? 'unknown'));

            if ((bool) $this->option('show-response')) {
                $this->newLine();
                $this->line(json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: (string) ($result['body'] ?? ''));
            }

            return self::FAILURE;
        }

        $savePath = $this->saveResponseIfRequested($result);

        $this->components->info((string) trans('zatca::commands.production_csid_complete'));
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

    /**
     * @return array<string, string>
     */
    private function resolvePayload(): array
    {
        $responsePayload = $this->readComplianceResponse();
        $requestId = $this->stringOption('request-id')
            ?? $responsePayload['requestID']
            ?? $responsePayload['request_id']
            ?? null;
        $binarySecurityToken = $this->stringOption('binary-security-token')
            ?? $responsePayload['binarySecurityToken']
            ?? $responsePayload['binary_security_token']
            ?? null;
        $secret = $this->stringOption('secret') ?? $responsePayload['secret'] ?? null;

        if (! is_scalar($requestId) || (string) $requestId === '') {
            throw new RuntimeException((string) trans('zatca::exceptions.production_csid_missing_request_id'));
        }

        if (! is_string($binarySecurityToken) || trim($binarySecurityToken) === '') {
            throw new RuntimeException((string) trans('zatca::exceptions.production_csid_missing_token'));
        }

        if (! is_string($secret) || trim($secret) === '') {
            throw new RuntimeException((string) trans('zatca::exceptions.production_csid_missing_secret'));
        }

        return [
            'compliance_request_id' => (string) $requestId,
            'binary_security_token' => trim($binarySecurityToken),
            'secret' => trim($secret),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readComplianceResponse(): array
    {
        $path = $this->resolvePath($this->stringOption('compliance-response'));

        if ($path === null) {
            return [];
        }

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException((string) trans('zatca::exceptions.compliance_response_unreadable', ['path' => $path]));
        }

        $contents = file_get_contents($path);

        if (! is_string($contents) || trim($contents) === '') {
            throw new RuntimeException((string) trans('zatca::exceptions.compliance_response_unreadable', ['path' => $path]));
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new RuntimeException((string) trans('zatca::exceptions.compliance_response_invalid', ['path' => $path]));
        }

        $body = $decoded['body'] ?? $decoded;

        return is_array($body) ? $body : [];
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
            throw new RuntimeException('Unable to save the Production CSID response to: ' . $path);
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
