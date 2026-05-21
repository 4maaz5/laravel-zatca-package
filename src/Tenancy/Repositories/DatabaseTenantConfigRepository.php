<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Repositories;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Maaz\LaravelZatca\Contracts\TenantConfigRepository;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\DTOs\TenantContext;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;

class DatabaseTenantConfigRepository implements TenantConfigRepository
{
    public function __construct(
        protected ConfigRepository $config
    ) {
    }

    public function forTenant(?TenantContext $tenant = null): TenantConfig
    {
        $defaultConfig = (array) $this->config->get('zatca.default_tenant', []);

        if (! $tenant instanceof TenantContext) {
            return TenantConfig::fromArray($defaultConfig, $tenant);
        }

        $tenantModel = ZatcaTenant::query()
            ->with(['credentials', 'invoiceStates'])
            ->whereTenantIdentifier($tenant->id)
            ->first();

        if (! $tenantModel instanceof ZatcaTenant) {
            return TenantConfig::fromArray($defaultConfig, $tenant);
        }

        $environment = (string) ($tenant->meta['environment'] ?? $tenantModel->default_environment ?? $defaultConfig['environment'] ?? 'sandbox');
        $credential = $tenantModel->credentials->firstWhere('environment', $environment)
            ?? $tenantModel->credentials->firstWhere('environment', 'sandbox');
        $invoiceState = $tenantModel->invoiceStates->firstWhere('environment', $environment);
        $language = (string) ($tenantModel->locale ?: ($defaultConfig['language'] ?? 'en'));

        $sellerName = $language === 'ar'
            ? ($tenantModel->seller_name_ar ?: $tenantModel->seller_name ?: $tenantModel->legal_name_ar ?: $tenantModel->legal_name)
            : ($tenantModel->seller_name ?: $tenantModel->legal_name ?: $tenantModel->seller_name_ar ?: $tenantModel->legal_name_ar);

        $branchName = $language === 'ar'
            ? ($tenantModel->branch_name_ar ?: $tenantModel->branch_name)
            : ($tenantModel->branch_name ?: $tenantModel->branch_name_ar);

        $certificate = $credential?->production_binary_security_token
            ?: $credential?->compliance_binary_security_token
            ?: null;
        $apiSecret = $credential?->production_secret
            ?: $credential?->compliance_secret
            ?: null;

        $tenantConfig = [
            'tenant_id' => (string) $tenantModel->getKey(),
            'environment' => $environment,
            'seller_name' => $sellerName,
            'seller_vat_number' => (string) $tenantModel->vat_number,
            'branch_name' => $branchName,
            'language' => $language,
            'certificates' => [
                'certificate' => $certificate,
                'private_key' => $credential?->private_key,
                'secret' => $credential?->private_key_secret,
            ],
            'api' => [
                'binary_security_token' => $certificate,
                'secret' => $apiSecret,
            ],
            'features' => array_replace_recursive((array) ($defaultConfig['features'] ?? []), [
                'multi_tenant' => true,
            ]),
            'meta' => array_filter([
                'legal_name' => $tenantModel->legal_name,
                'legal_name_ar' => $tenantModel->legal_name_ar,
                'seller_name_ar' => $tenantModel->seller_name_ar,
                'branch_name_ar' => $tenantModel->branch_name_ar,
                'country_code' => $tenantModel->country_code,
                'city' => $tenantModel->city,
                'district' => $tenantModel->district,
                'street' => $tenantModel->street,
                'building_number' => $tenantModel->building_number,
                'additional_number' => $tenantModel->additional_number,
                'postal_code' => $tenantModel->postal_code,
                'crn' => $tenantModel->crn,
                'onboarding_status' => $tenantModel->onboarding_status,
                'credentials_status' => $credential?->status,
                'compliance_request_id' => $credential?->compliance_request_id,
                'icv' => $invoiceState?->last_icv ? (string) ($invoiceState->last_icv + 1) : null,
                'pih' => $invoiceState?->previous_invoice_hash,
                'timezone' => $tenantModel->timezone,
                'tenant_key' => $tenantModel->key,
                'tenant_metadata' => $tenantModel->metadata,
                'credential_metadata' => $credential?->metadata,
                'invoice_state_metadata' => $invoiceState?->metadata,
            ], static fn ($value): bool => $value !== null && $value !== ''),
        ];

        return TenantConfig::fromArray(array_replace_recursive($defaultConfig, $tenantConfig), $tenant);
    }
}
