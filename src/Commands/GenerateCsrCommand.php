<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Commands;

use Illuminate\Console\Command;
use Maaz\LaravelZatca\Exceptions\ZatcaException;
use Maaz\LaravelZatca\Services\ZatcaManager;

class GenerateCsrCommand extends Command
{
    protected $signature = 'zatca:csr-generate
        {--tenant= : Tenant id or key}
        {--config= : Existing CSR properties file path}
        {--save-config= : Path where the generated CSR properties file should be written}
        {--common-name= : CSR common name}
        {--serial-number= : CSR serial number in 1-.../2-.../3-... format}
        {--organization-identifier= : Organization identifier, defaults to the tenant seller VAT number}
        {--organization-unit-name= : Organization unit or branch name, defaults to the tenant branch name}
        {--organization-name= : Organization legal name, defaults to the tenant seller name}
        {--country-name=SA : Country code}
        {--invoice-type=1100 : Invoice type code used in the CSR}
        {--location-address= : Location address code}
        {--industry-business-category= : Industry/business category}
        {--generated-csr= : Output path for the generated CSR file}
        {--private-key= : Output path for the generated private key file}
        {--raw : Generate raw SDK outputs instead of PEM files}
        {--sim : Generate simulation CSR/private key files}
        {--nonprod : Generate non-production CSR/private key files}
        {--show-csr : Print the normalized base64 CSR payload after generation}';

    protected $description = 'Generate a ZATCA onboarding CSR and private key using the official SDK CLI.';

    public function handle(): int
    {
        try {
            $manager = $this->resolveManager();
            $result = $manager->generateCsr($this->payload());
        } catch (ZatcaException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info((string) trans('zatca::commands.csr_generate_complete'));
        $this->line('CSR file: ' . $result->csrPath);
        $this->line('Private key file: ' . $result->privateKeyPath);
        $this->line('CSR output format: ' . ($result->rawOutput ? 'raw SDK output' : 'PEM'));

        if ($result->configPath !== null) {
            $this->line('CSR config: ' . $result->configPath);
        }

        $this->line('CSR base64 length: ' . strlen($result->csrBase64));

        if ((bool) $this->option('show-csr')) {
            $this->newLine();
            $this->components->twoColumnDetail('CSR Base64', $result->csrBase64);
            $this->line((string) trans('zatca::commands.csr_show_csr_hint'));
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
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return array_filter([
            'config_path' => $this->stringOption('config'),
            'save_config_path' => $this->stringOption('save-config'),
            'common_name' => $this->stringOption('common-name'),
            'serial_number' => $this->stringOption('serial-number'),
            'organization_identifier' => $this->stringOption('organization-identifier'),
            'organization_unit_name' => $this->stringOption('organization-unit-name'),
            'organization_name' => $this->stringOption('organization-name'),
            'country_name' => $this->stringOption('country-name'),
            'invoice_type' => $this->stringOption('invoice-type'),
            'location_address' => $this->stringOption('location-address'),
            'industry_business_category' => $this->stringOption('industry-business-category'),
            'generated_csr_path' => $this->stringOption('generated-csr'),
            'private_key_path' => $this->stringOption('private-key'),
            'raw' => (bool) $this->option('raw'),
            'simulation' => (bool) $this->option('sim'),
            'non_production' => (bool) $this->option('nonprod'),
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
