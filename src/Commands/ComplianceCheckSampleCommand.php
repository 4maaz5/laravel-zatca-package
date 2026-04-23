<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\Exceptions\ZatcaException;
use Maaz\LaravelZatca\Services\ZatcaManager;
use RuntimeException;

class ComplianceCheckSampleCommand extends Command
{
    protected $signature = 'zatca:compliance-check-sample
        {--tenant= : Tenant id or key}
        {--compliance-response= : Saved Compliance CSID response JSON path}
        {--private-key= : Private key path generated with the CSR}
        {--save= : Optional JSON file path to save the Compliance Invoice API response}
        {--save-xml= : Optional XML file path to save the final signed invoice sent to the API}
        {--seller-name= : Seller legal name}
        {--seller-vat= : Seller VAT number}
        {--seller-crn= : Seller CRN}
        {--street= : Seller street name}
        {--building-number= : Seller building number}
        {--additional-number= : Seller additional number}
        {--district= : Seller district}
        {--city=Riyadh : Seller city}
        {--postal-code= : Seller postal code}
        {--buyer-vat=300000000000013 : Buyer VAT number for the sample invoice}
        {--show-response : Print the full JSON response body}';

    protected $description = 'Submit a signed sample invoice to the ZATCA Compliance Invoice API.';

    public function handle(): int
    {
        try {
            $compliance = $this->readComplianceResponse();
            $privateKeyPath = $this->resolveRequiredFile($this->stringOption('private-key'), 'Private key');
            $this->configureRuntimeCredentials($compliance, $privateKeyPath);

            $manager = $this->resolveManager();
            $invoice = $this->sampleInvoice($manager);
            $prepared = $manager->prepare($invoice);
            $this->saveXmlIfRequested($prepared->finalXml);

            $result = $manager->complianceCheck($invoice);
        } catch (RuntimeException|ZatcaException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! ($result['success'] ?? false)) {
            $this->components->error((string) trans('zatca::commands.compliance_check_failed'));
            $this->line('HTTP status: ' . (string) ($result['status_code'] ?? 'unknown'));
            $this->printResponseIfRequested($result);

            return self::FAILURE;
        }

        $savePath = $this->saveResponseIfRequested($result);
        $body = $result['body'] ?? [];

        $this->components->info((string) trans('zatca::commands.compliance_check_complete'));
        $this->line('HTTP status: ' . (string) ($result['status_code'] ?? 'unknown'));

        if (is_array($body)) {
            $this->line('Status: ' . (string) ($body['status'] ?? $body['validationResults']['status'] ?? '[unknown]'));
            $this->line('Reporting status: ' . (string) ($body['reportingStatus'] ?? '[missing]'));
            $this->line('Clearance status: ' . (string) ($body['clearanceStatus'] ?? '[missing]'));
        }

        if ($savePath !== null) {
            $this->line('Saved response: ' . $savePath);
        }

        $this->printResponseIfRequested($result);

        return self::SUCCESS;
    }

    private function resolveManager(): ZatcaManager
    {
        $manager = $this->laravel->make(ZatcaManager::class);
        $tenant = $this->stringOption('tenant');

        return $tenant === null ? $manager : $manager->forTenant($tenant);
    }

    private function sampleInvoice(ZatcaManager $manager): InvoiceData
    {
        return $manager->invoice()
            ->invoiceNumber('COMP-' . CarbonImmutable::now('Asia/Riyadh')->format('YmdHis'))
            ->issuedAt(CarbonImmutable::now('Asia/Riyadh')->toIso8601String())
            ->seller([
                'name' => $this->stringOption('seller-name') ?? (string) config('zatca.default_tenant.seller_name'),
                'vat_number' => $this->stringOption('seller-vat') ?? (string) config('zatca.default_tenant.seller_vat_number'),
                'crn' => $this->stringOption('seller-crn') ?? '',
                'street' => $this->stringOption('street') ?? 'Saidya',
                'building_number' => $this->stringOption('building-number') ?? '7036',
                'additional_number' => $this->stringOption('additional-number') ?? '7036',
                'district' => $this->stringOption('district') ?? 'AL Duraihemiyah',
                'city' => $this->stringOption('city') ?? 'Riyadh',
                'postal_code' => $this->stringOption('postal-code') ?? '12796',
            ])
            ->buyer([
                'name' => 'ZATCA Compliance Buyer',
                'vat_number' => $this->stringOption('buyer-vat') ?? '300000000000013',
                'street' => 'King Road',
                'building_number' => '1111',
                'additional_number' => '5678',
                'district' => 'Al-Murooj',
                'city' => 'Riyadh',
                'postal_code' => '12222',
            ])
            ->addItem([
                'name' => 'Compliance Test Item',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_percent' => 15,
            ])
            ->meta([
                'icv' => '1',
                'transaction_type_code' => '0200000',
            ])
            ->generate();
    }

    /**
     * @return array<string, mixed>
     */
    private function readComplianceResponse(): array
    {
        $path = $this->resolveRequiredFile($this->stringOption('compliance-response'), 'Compliance response');
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

    private function configureRuntimeCredentials(array $compliance, string $privateKeyPath): void
    {
        $token = $compliance['binarySecurityToken'] ?? null;
        $secret = $compliance['secret'] ?? null;

        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException((string) trans('zatca::exceptions.production_csid_missing_token'));
        }

        if (! is_string($secret) || trim($secret) === '') {
            throw new RuntimeException((string) trans('zatca::exceptions.production_csid_missing_secret'));
        }

        config()->set('zatca.default_tenant.certificates.certificate', trim($token));
        config()->set('zatca.default_tenant.certificates.private_key_path', $privateKeyPath);
        config()->set('zatca.default_tenant.api.binary_security_token', trim($token));
        config()->set('zatca.default_tenant.api.secret', trim($secret));
    }

    private function saveXmlIfRequested(string $xml): void
    {
        $path = $this->resolvePath($this->stringOption('save-xml'));

        if ($path === null) {
            return;
        }

        $this->ensureParentDirectory(dirname($path));

        if (file_put_contents($path, $xml) === false) {
            throw new RuntimeException('Unable to save final compliance XML to: ' . $path);
        }
    }

    private function saveResponseIfRequested(array $result): ?string
    {
        $path = $this->resolvePath($this->stringOption('save'));

        if ($path === null) {
            return null;
        }

        $this->ensureParentDirectory(dirname($path));

        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($json) || file_put_contents($path, $json) === false) {
            throw new RuntimeException('Unable to save the Compliance Invoice API response to: ' . $path);
        }

        return $path;
    }

    private function printResponseIfRequested(array $result): void
    {
        if (! (bool) $this->option('show-response')) {
            return;
        }

        $this->newLine();
        $this->line(json_encode($result['body'] ?? $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '');
    }

    private function resolveRequiredFile(?string $path, string $label): string
    {
        $resolvedPath = $this->resolvePath($path);

        if ($resolvedPath === null || ! is_file($resolvedPath) || ! is_readable($resolvedPath)) {
            throw new RuntimeException($label . ' file was not found at: ' . ($path ?: '[not configured]'));
        }

        return $resolvedPath;
    }

    private function ensureParentDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create output directory: ' . $directory);
        }
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
