<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\DTOs\SubmissionResult;
use Maaz\LaravelZatca\Exceptions\ZatcaException;
use Maaz\LaravelZatca\Services\ZatcaManager;
use RuntimeException;

class SubmitSampleInvoiceCommand extends Command
{
    protected $signature = 'zatca:submit-sample
        {--mode=reporting : Submission mode: reporting or clearance}
        {--tenant= : Tenant id or key}
        {--production-response= : Saved Production CSID response JSON path}
        {--private-key= : Private key path generated with the CSR}
        {--save= : Optional JSON file path to save the Reporting/Clearance API response}
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
        {--buyer-name=ZATCA Sandbox Buyer : Buyer name for the sample invoice}
        {--buyer-vat=300000000000013 : Buyer VAT number for the sample invoice}
        {--show-response : Print the full JSON response body}';

    protected $description = 'Submit a signed sample invoice to the ZATCA Reporting or Clearance API using Production CSID credentials.';

    public function handle(): int
    {
        try {
            $mode = $this->resolveMode();
            $production = $this->readProductionResponse();
            $privateKeyPath = $this->resolveRequiredFile($this->stringOption('private-key'), 'Private key');
            $this->configureRuntimeCredentials($production, $privateKeyPath);

            $manager = $this->resolveManager();
            $invoice = $this->sampleInvoice($manager, $mode);
            $result = $mode === 'clearance'
                ? $manager->clearance($invoice)
                : $manager->report($invoice);

            $this->saveXmlIfRequested($result);
        } catch (RuntimeException|ZatcaException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $result->accepted()) {
            $this->components->error((string) trans('zatca::commands.submit_sample_failed'));
            $this->line('HTTP status: ' . (string) ($result->apiResponse['status_code'] ?? 'unknown'));
            $this->printResponseIfRequested($result);

            return self::FAILURE;
        }

        $savePath = $this->saveResponseIfRequested($result);
        $body = $result->apiResponse['body'] ?? [];

        $this->components->info((string) trans('zatca::commands.submit_sample_complete'));
        $this->line('Mode: ' . $result->mode);
        $this->line('HTTP status: ' . (string) ($result->apiResponse['status_code'] ?? 'unknown'));

        if (is_array($body)) {
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

    private function resolveMode(): string
    {
        $mode = strtolower($this->stringOption('mode') ?? 'reporting');

        if (! in_array($mode, ['reporting', 'clearance'], true)) {
            throw new RuntimeException('Unsupported submission mode. Use reporting or clearance.');
        }

        return $mode;
    }

    private function sampleInvoice(ZatcaManager $manager, string $mode): InvoiceData
    {
        return $manager->invoice()
            ->invoiceNumber(($mode === 'clearance' ? 'CLR-' : 'RPT-') . CarbonImmutable::now('Asia/Riyadh')->format('YmdHis'))
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
                'name' => $this->stringOption('buyer-name') ?? 'ZATCA Sandbox Buyer',
                'vat_number' => $this->stringOption('buyer-vat') ?? '300000000000013',
                'street' => 'King Road',
                'building_number' => '1111',
                'additional_number' => '5678',
                'district' => 'Al-Murooj',
                'city' => 'Riyadh',
                'postal_code' => '12222',
            ])
            ->addItem([
                'name' => $mode === 'clearance' ? 'Clearance Test Item' : 'Reporting Test Item',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_percent' => 15,
            ])
            ->meta([
                'icv' => '1',
                'transaction_type_code' => $mode === 'clearance' ? '0100000' : '0200000',
            ])
            ->generate();
    }

    /**
     * @return array<string, mixed>
     */
    private function readProductionResponse(): array
    {
        $path = $this->resolveRequiredFile($this->stringOption('production-response'), 'Production response');
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

    private function configureRuntimeCredentials(array $production, string $privateKeyPath): void
    {
        $token = $production['binarySecurityToken'] ?? null;
        $secret = $production['secret'] ?? null;

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

    private function saveXmlIfRequested(SubmissionResult $result): void
    {
        $path = $this->resolvePath($this->stringOption('save-xml'));

        if ($path === null) {
            return;
        }

        $this->ensureParentDirectory(dirname($path));

        if (file_put_contents($path, $result->signedXml) === false) {
            throw new RuntimeException('Unable to save final submission XML to: ' . $path);
        }
    }

    private function saveResponseIfRequested(SubmissionResult $result): ?string
    {
        $path = $this->resolvePath($this->stringOption('save'));

        if ($path === null) {
            return null;
        }

        $this->ensureParentDirectory(dirname($path));

        $json = json_encode($result->apiResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($json) || file_put_contents($path, $json) === false) {
            throw new RuntimeException('Unable to save the submission API response to: ' . $path);
        }

        return $path;
    }

    private function printResponseIfRequested(SubmissionResult $result): void
    {
        if (! (bool) $this->option('show-response')) {
            return;
        }

        $this->newLine();
        $this->line(json_encode($result->apiResponse['body'] ?? $result->apiResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '');
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
