<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantInvoiceState;

class SimpleWorkspaceManager
{
    public function __construct(
        protected ConfigRepository $config
    ) {
    }

    public function enabled(): bool
    {
        return (bool) $this->config->get('zatca.onboarding.simple_mode.enabled', false);
    }

    public function tenantKey(): string
    {
        return (string) $this->config->get('zatca.onboarding.simple_mode.tenant_key', 'default');
    }

    public function showNotificationHooks(): bool
    {
        return (bool) $this->config->get('zatca.onboarding.simple_mode.show_notification_hooks', false);
    }

    public function ensureWorkspace(): ?ZatcaTenant
    {
        if (! $this->enabled() || ! $this->tablesExist()) {
            return null;
        }

        return DB::transaction(function (): ZatcaTenant {
            $defaults = $this->defaults();
            $tenant = $this->resolveWorkspaceTenant($defaults);

            $profileAttributes = [
                'legal_name',
                'legal_name_ar',
                'seller_name',
                'seller_name_ar',
                'vat_number',
                'crn',
                'branch_name',
                'branch_name_ar',
                'country_code',
                'city',
                'district',
                'street',
                'building_number',
                'additional_number',
                'postal_code',
                'locale',
                'timezone',
                'default_environment',
            ];

            if (! $tenant->exists) {
                $tenant->fill($defaults);
                $tenant->save();
            } else {
                $dirty = false;

                foreach ($profileAttributes as $attribute) {
                    if ($this->isBlank($tenant->{$attribute}) && ! $this->isBlank($defaults[$attribute] ?? null)) {
                        $tenant->{$attribute} = $defaults[$attribute];
                        $dirty = true;
                    }
                }

                $existingMetadata = is_array($tenant->metadata) ? $tenant->metadata : [];
                $mergedMetadata = array_replace_recursive(
                    (array) ($defaults['metadata'] ?? []),
                    $existingMetadata
                );

                if ($mergedMetadata !== $existingMetadata) {
                    $tenant->metadata = $mergedMetadata;
                    $dirty = true;
                }

                if ($tenant->onboarding_status === '') {
                    $tenant->onboarding_status = 'draft';
                    $dirty = true;
                }

                if (! $tenant->is_active) {
                    $tenant->is_active = true;
                    $dirty = true;
                }

                if ($dirty) {
                    $tenant->save();
                }
            }

            foreach (['sandbox', 'production'] as $environment) {
                ZatcaTenantCredential::query()->firstOrCreate([
                    'tenant_id' => $tenant->getKey(),
                    'environment' => $environment,
                ], [
                    'signer' => 'sdk',
                    'status' => 'draft',
                ]);

                ZatcaTenantInvoiceState::query()->firstOrCreate([
                    'tenant_id' => $tenant->getKey(),
                    'environment' => $environment,
                ], [
                    'last_icv' => 0,
                ]);
            }

            return $tenant->fresh(['credentials', 'invoiceStates', 'notificationHooks']);
        });
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    protected function resolveWorkspaceTenant(array $defaults): ZatcaTenant
    {
        $tenantKey = (string) ($defaults['key'] ?? 'default');
        $vatNumber = trim((string) ($defaults['vat_number'] ?? ''));

        $tenant = ZatcaTenant::query()->where('key', $tenantKey)->first();

        if ($tenant instanceof ZatcaTenant) {
            return $tenant;
        }

        if ($vatNumber !== '') {
            $tenant = ZatcaTenant::query()->where('vat_number', $vatNumber)->first();

            if ($tenant instanceof ZatcaTenant) {
                if ($tenant->key !== $tenantKey && ! ZatcaTenant::query()->where('key', $tenantKey)->exists()) {
                    $tenant->key = $tenantKey;
                }

                return $tenant;
            }
        }

        return new ZatcaTenant([
            'key' => $tenantKey,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        $defaultTenant = (array) $this->config->get('zatca.default_tenant', []);
        $tenantKey = $this->tenantKey();
        $legalName = (string) ($defaultTenant['legal_name'] ?? $defaultTenant['seller_name'] ?? 'ZATCA Workspace');
        $sellerName = (string) ($defaultTenant['seller_name'] ?? $legalName);
        $branchName = isset($defaultTenant['branch_name']) ? (string) $defaultTenant['branch_name'] : null;
        $countryCode = strtoupper((string) ($defaultTenant['country_code'] ?? 'SA'));
        $vatNumber = (string) ($defaultTenant['seller_vat_number'] ?? '');
        $crn = (string) ($defaultTenant['crn'] ?? '');

        return [
            'key' => $tenantKey,
            'legal_name' => $legalName,
            'legal_name_ar' => $defaultTenant['legal_name_ar'] ?? null,
            'seller_name' => $sellerName,
            'seller_name_ar' => $defaultTenant['seller_name_ar'] ?? null,
            'vat_number' => $vatNumber,
            'crn' => $crn !== '' ? $crn : null,
            'branch_name' => $branchName,
            'branch_name_ar' => $defaultTenant['branch_name_ar'] ?? null,
            'country_code' => $countryCode,
            'city' => $defaultTenant['city'] ?? null,
            'district' => $defaultTenant['district'] ?? null,
            'street' => $defaultTenant['street'] ?? null,
            'building_number' => $defaultTenant['building_number'] ?? null,
            'additional_number' => $defaultTenant['additional_number'] ?? null,
            'postal_code' => $defaultTenant['postal_code'] ?? null,
            'locale' => (string) ($defaultTenant['locale'] ?? $defaultTenant['language'] ?? 'en'),
            'timezone' => (string) ($defaultTenant['timezone'] ?? 'Asia/Riyadh'),
            'default_environment' => (string) ($defaultTenant['environment'] ?? 'sandbox'),
            'onboarding_status' => 'draft',
            'is_active' => true,
            'metadata' => $this->defaultMetadata($tenantKey, $legalName, $branchName, $countryCode, $vatNumber, $crn),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultMetadata(
        string $tenantKey,
        string $legalName,
        ?string $branchName,
        string $countryCode,
        string $vatNumber,
        string $crn
    ): array {
        $defaultMeta = (array) $this->config->get('zatca.default_tenant.meta', []);
        $configuredCsrDefaults = (array) ($defaultMeta['csr_defaults'] ?? []);
        $csrDefaults = array_replace([
            'common_name' => ($crn !== '' && $vatNumber !== '') ? sprintf('TST-%s-%s', $crn, $vatNumber) : null,
            'serial_number_prefix' => sprintf('1-%s|2-LARAVEL-ZATCA|3-', strtoupper(str_replace(' ', '-', $tenantKey))),
            'organization_identifier' => $vatNumber !== '' ? $vatNumber : null,
            'organization_name' => $legalName !== '' ? $legalName : null,
            'organization_unit_name' => $branchName,
            'country_name' => $countryCode,
            'invoice_type' => '1100',
            'location_address' => null,
            'industry_business_category' => null,
        ], $configuredCsrDefaults);

        $defaultMeta['csr_defaults'] = array_filter($csrDefaults, static fn ($value): bool => $value !== null && $value !== '');
        $defaultMeta['provisioned_by'] = $defaultMeta['provisioned_by'] ?? 'simple_mode';

        return $defaultMeta;
    }

    protected function tablesExist(): bool
    {
        return Schema::hasTable('zatca_tenants')
            && Schema::hasTable('zatca_tenant_credentials')
            && Schema::hasTable('zatca_tenant_invoice_states');
    }

    protected function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
