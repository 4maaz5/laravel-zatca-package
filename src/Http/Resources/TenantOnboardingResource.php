<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Maaz\LaravelZatca\Tenancy\Health\TenantCredentialHealthChecker;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;

/** @mixin ZatcaTenant */
class TenantOnboardingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var TenantCredentialHealthChecker $healthChecker */
        $healthChecker = app(TenantCredentialHealthChecker::class);
        $healthByEnvironment = collect($healthChecker->forTenant($this->resource))
            ->keyBy('environment');

        $credentials = $this->whenLoaded('credentials', function () use ($healthByEnvironment): array {
            return $this->credentials
                ->sortBy('environment')
                ->map(fn ($credential): array => [
                    'environment' => $credential->environment,
                    'signer' => $credential->signer,
                    'status' => $credential->status,
                    'compliance_request_id' => $credential->compliance_request_id,
                    'has_private_key' => ! empty($credential->private_key),
                    'has_compliance_csid' => ! empty($credential->compliance_binary_security_token),
                    'has_production_csid' => ! empty($credential->production_binary_security_token),
                    'compliance_issued_at' => optional($credential->compliance_issued_at)?->toIso8601String(),
                    'production_issued_at' => optional($credential->production_issued_at)?->toIso8601String(),
                    'last_validated_at' => optional($credential->last_validated_at)?->toIso8601String(),
                    'health' => $healthByEnvironment->get($credential->environment),
                    'metadata' => $credential->metadata ?? [],
                ])->values()->all();
        }, []);

        $invoiceStates = $this->whenLoaded('invoiceStates', function (): array {
            return $this->invoiceStates
                ->sortBy('environment')
                ->map(fn ($state): array => [
                    'environment' => $state->environment,
                    'last_icv' => $state->last_icv,
                    'next_icv' => $state->last_icv + 1,
                    'previous_invoice_hash' => $state->previous_invoice_hash,
                    'last_invoice_uuid' => $state->last_invoice_uuid,
                    'last_invoice_hash' => $state->last_invoice_hash,
                    'last_submitted_at' => optional($state->last_submitted_at)?->toIso8601String(),
                    'metadata' => $state->metadata ?? [],
                ])->values()->all();
        }, []);

        $notificationHooks = $this->whenLoaded('notificationHooks', function (): array {
            return $this->notificationHooks
                ->sortByDesc('created_at')
                ->map(fn ($hook): array => (new TenantNotificationHookResource($hook))->resolve())
                ->values()
                ->all();
        }, []);

        $invoiceReadiness = collect($credentials)
            ->mapWithKeys(function (array $credential): array {
                $environment = (string) ($credential['environment'] ?? 'sandbox');
                $ready = (bool) ($credential['has_private_key'] ?? false)
                    && (
                        (bool) ($credential['has_compliance_csid'] ?? false)
                        || (bool) ($credential['has_production_csid'] ?? false)
                    );

                return [
                    $environment => [
                        'ready' => $ready,
                        'environment' => $environment,
                    ],
                ];
            })
            ->all();

        return [
            'id' => (string) $this->getKey(),
            'key' => $this->key,
            'legal_name' => $this->legal_name,
            'legal_name_ar' => $this->legal_name_ar,
            'seller_name' => $this->seller_name,
            'seller_name_ar' => $this->seller_name_ar,
            'vat_number' => $this->vat_number,
            'crn' => $this->crn,
            'branch_name' => $this->branch_name,
            'branch_name_ar' => $this->branch_name_ar,
            'address' => [
                'country_code' => $this->country_code,
                'city' => $this->city,
                'district' => $this->district,
                'street' => $this->street,
                'building_number' => $this->building_number,
                'additional_number' => $this->additional_number,
                'postal_code' => $this->postal_code,
            ],
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'default_environment' => $this->default_environment,
            'onboarding_status' => $this->onboarding_status,
            'onboarding_status_labels' => $this->statusLabels($this->onboarding_status),
            'is_active' => $this->is_active,
            'metadata' => $this->metadata ?? [],
            'credentials' => $credentials,
            'invoice_submission_readiness' => $invoiceReadiness,
            'invoice_states' => $invoiceStates,
            'notification_hooks' => $notificationHooks,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }

    private function statusLabels(string $status): array
    {
        $map = [
            'draft' => ['en' => 'Draft', 'ar' => 'مسودة'],
            'profile_completed' => ['en' => 'Profile Completed', 'ar' => 'تم استكمال الملف'],
            'csr_generated' => ['en' => 'CSR Generated', 'ar' => 'تم إنشاء CSR'],
            'compliance_issued' => ['en' => 'Compliance Issued', 'ar' => 'تم إصدار شهادة الامتثال'],
            'production_issued' => ['en' => 'Production Issued', 'ar' => 'تم إصدار شهادة الإنتاج'],
            'active' => ['en' => 'Active', 'ar' => 'نشط'],
        ];

        return $map[$status] ?? ['en' => ucfirst(str_replace('_', ' ', $status)), 'ar' => $status];
    }
}
