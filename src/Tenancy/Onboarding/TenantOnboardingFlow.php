<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Onboarding;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Maaz\LaravelZatca\Exceptions\ApiException;
use Maaz\LaravelZatca\Services\ZatcaManager;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantInvoiceState;

class TenantOnboardingFlow
{
    public function __construct(
        protected ZatcaManager $manager
    ) {
    }

    public function findTenantOrFail(string $tenant): ZatcaTenant
    {
        return ZatcaTenant::query()
            ->with(['credentials', 'invoiceStates', 'notificationHooks'])
            ->whereKey($tenant)
            ->orWhere('key', $tenant)
            ->firstOrFail();
    }

    public function listTenants(): Collection
    {
        return ZatcaTenant::query()
            ->with(['credentials', 'invoiceStates', 'notificationHooks'])
            ->orderBy('legal_name')
            ->get();
    }

    public function createTenant(array $payload): ZatcaTenant
    {
        return DB::transaction(function () use ($payload): ZatcaTenant {
            $tenant = ZatcaTenant::query()->create([
                'key' => $payload['key'],
                'legal_name' => $payload['legal_name'],
                'legal_name_ar' => $payload['legal_name_ar'] ?? null,
                'seller_name' => $payload['seller_name'] ?? $payload['legal_name'],
                'seller_name_ar' => $payload['seller_name_ar'] ?? null,
                'vat_number' => $payload['vat_number'],
                'crn' => $payload['crn'] ?? null,
                'branch_name' => $payload['branch_name'] ?? null,
                'branch_name_ar' => $payload['branch_name_ar'] ?? null,
                'country_code' => strtoupper((string) ($payload['country_code'] ?? 'SA')),
                'city' => $payload['city'] ?? null,
                'district' => $payload['district'] ?? null,
                'street' => $payload['street'] ?? null,
                'building_number' => $payload['building_number'] ?? null,
                'additional_number' => $payload['additional_number'] ?? null,
                'postal_code' => $payload['postal_code'] ?? null,
                'locale' => $payload['locale'] ?? 'en',
                'timezone' => $payload['timezone'] ?? 'Asia/Riyadh',
                'default_environment' => $payload['default_environment'] ?? 'sandbox',
                'onboarding_status' => 'profile_completed',
                'is_active' => true,
                'metadata' => $payload['metadata'] ?? [],
            ]);

            foreach (['sandbox', 'production'] as $environment) {
                ZatcaTenantCredential::query()->create([
                    'tenant_id' => $tenant->getKey(),
                    'environment' => $environment,
                    'signer' => 'sdk',
                    'status' => 'draft',
                ]);

                ZatcaTenantInvoiceState::query()->create([
                    'tenant_id' => $tenant->getKey(),
                    'environment' => $environment,
                    'last_icv' => 0,
                ]);
            }

            return $tenant->fresh(['credentials', 'invoiceStates', 'notificationHooks']);
        });
    }

    public function updateTenant(ZatcaTenant $tenant, array $payload): ZatcaTenant
    {
        $tenant->fill(Arr::only($payload, [
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
            'is_active',
            'metadata',
        ]));

        if ($tenant->onboarding_status === 'draft') {
            $tenant->onboarding_status = 'profile_completed';
        }

        $tenant->save();

        return $tenant->fresh(['credentials', 'invoiceStates', 'notificationHooks']);
    }

    public function generateCsr(ZatcaTenant $tenant, array $payload)
    {
        $environment = $this->environment($tenant, $payload);
        $result = $this->managerForTenant($tenant)->generateCsr([
            'common_name' => $payload['common_name'],
            'serial_number' => $payload['serial_number'],
            'organization_identifier' => $payload['organization_identifier'] ?? $tenant->vat_number,
            'organization_unit_name' => $payload['organization_unit_name'] ?? $tenant->branch_name ?? $tenant->branch_name_ar,
            'organization_name' => $payload['organization_name'] ?? $tenant->seller_name ?? $tenant->legal_name,
            'country_name' => $payload['country_name'] ?? $tenant->country_code,
            'invoice_type' => $payload['invoice_type'] ?? '1100',
            'location_address' => $payload['location_address'],
            'industry_business_category' => $payload['industry_business_category'],
            'simulation' => (bool) ($payload['simulation'] ?? false),
            'non_production' => (bool) ($payload['non_production'] ?? false),
        ]);

        $credential = $this->credential($tenant, $environment);
        $credential->fill([
            'status' => 'csr_generated',
            'csr_base64' => $result->csrBase64,
            'csr_pem' => $result->csrPem,
            'private_key' => $result->privateKeyPem,
            'private_key_secret' => null,
            'compliance_request_id' => null,
            'compliance_binary_security_token' => null,
            'compliance_secret' => null,
            'compliance_issued_at' => null,
            'production_binary_security_token' => null,
            'production_secret' => null,
            'production_issued_at' => null,
            'metadata' => array_replace($credential->metadata ?? [], [
                'csr_properties' => $result->properties,
                'csr_path' => $result->csrPath,
                'private_key_path' => $result->privateKeyPath,
                'last_compliance_response' => null,
                'last_production_response' => null,
            ]),
        ])->save();

        $tenant->update(['onboarding_status' => 'csr_generated']);

        return $result;
    }

    public function issueComplianceCsid(ZatcaTenant $tenant, array $payload): array
    {
        $environment = $this->environment($tenant, $payload);
        $credential = $this->credential($tenant, $environment);
        $csr = (string) ($payload['csr'] ?? $credential->csr_base64 ?? '');

        $result = $this->managerForTenant($tenant)->onboardComplianceCsid([
            'otp' => $payload['otp'],
            'csr' => $csr,
        ]);

        $body = $this->validatedOnboardingBody($result, ['requestID', 'binarySecurityToken', 'secret'], 'Compliance CSID');

        $credential->fill([
            'status' => 'compliance_issued',
            'compliance_request_id' => (string) ($body['requestID'] ?? $credential->compliance_request_id),
            'compliance_binary_security_token' => $body['binarySecurityToken'] ?? $credential->compliance_binary_security_token,
            'compliance_secret' => $body['secret'] ?? $credential->compliance_secret,
            'compliance_issued_at' => now(),
            'production_binary_security_token' => null,
            'production_secret' => null,
            'production_issued_at' => null,
            'metadata' => array_replace($credential->metadata ?? [], [
                'last_compliance_response' => $body,
                'last_production_response' => null,
            ]),
        ])->save();

        $tenant->update(['onboarding_status' => 'compliance_issued']);

        return $result;
    }

    public function issueProductionCsid(ZatcaTenant $tenant, array $payload = []): array
    {
        $environment = $this->environment($tenant, $payload);
        $credential = $this->credential($tenant, $environment);

        if (empty($credential->compliance_request_id) || empty($credential->compliance_binary_security_token) || empty($credential->compliance_secret)) {
            throw new ApiException((string) trans('zatca::exceptions.production_csid_missing_compliance_material'));
        }

        $result = $this->managerForTenant($tenant)->onboardProductionCsid([
            'compliance_request_id' => (string) $credential->compliance_request_id,
            'binary_security_token' => (string) $credential->compliance_binary_security_token,
            'secret' => (string) $credential->compliance_secret,
        ]);

        $body = $this->validatedOnboardingBody($result, ['binarySecurityToken', 'secret'], 'Production CSID');

        $credential->fill([
            'status' => 'production_issued',
            'production_binary_security_token' => $body['binarySecurityToken'] ?? $credential->production_binary_security_token,
            'production_secret' => $body['secret'] ?? $credential->production_secret,
            'production_issued_at' => now(),
            'metadata' => array_replace($credential->metadata ?? [], [
                'last_production_response' => $body,
            ]),
        ])->save();

        $tenant->update(['onboarding_status' => 'production_issued']);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<string>  $requiredKeys
     * @return array<string, mixed>
     */
    private function validatedOnboardingBody(array $result, array $requiredKeys, string $stage): array
    {
        $body = is_array($result['body'] ?? null) ? $result['body'] : [];

        if (! (bool) ($result['success'] ?? false)) {
            throw new ApiException($this->onboardingFailureMessage($stage, $result, $body));
        }

        foreach ($requiredKeys as $key) {
            if (! is_scalar($body[$key] ?? null) || trim((string) $body[$key]) === '') {
                throw new ApiException((string) trans('zatca::exceptions.onboarding_response_missing_field', [
                    'stage' => $stage,
                    'field' => $key,
                ]));
            }
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $body
     */
    private function onboardingFailureMessage(string $stage, array $result, array $body): string
    {
        $statusCode = (string) ($result['status_code'] ?? 'unknown');
        $rawBody = $result['body'] ?? null;
        $message = $body['message'] ?? $body['error'] ?? $body['dispositionMessage'] ?? null;

        if ((! is_scalar($message) || trim((string) $message) === '') && is_scalar($rawBody) && trim((string) $rawBody) !== '') {
            $message = (string) $rawBody;
        }

        if (is_scalar($message) && trim((string) $message) !== '') {
            return (string) trans('zatca::exceptions.onboarding_request_failed_with_message', [
                'stage' => $stage,
                'status' => $statusCode,
                'message' => (string) $message,
            ]);
        }

        return (string) trans('zatca::exceptions.onboarding_request_failed', [
            'stage' => $stage,
            'status' => $statusCode,
        ]);
    }

    private function credential(ZatcaTenant $tenant, string $environment): ZatcaTenantCredential
    {
        $credential = $tenant->credentials->firstWhere('environment', $environment);

        if ($credential instanceof ZatcaTenantCredential) {
            return $credential;
        }

        throw (new ModelNotFoundException())->setModel(ZatcaTenantCredential::class, [$tenant->getKey(), $environment]);
    }

    private function environment(ZatcaTenant $tenant, array $payload): string
    {
        return (string) ($payload['environment'] ?? $tenant->default_environment ?? 'sandbox');
    }

    private function managerForTenant(ZatcaTenant $tenant): ZatcaManager
    {
        return $this->manager->forTenant($tenant->key ?: (string) $tenant->getKey());
    }
}
