<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Health;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository as ConfigRepository;
use Maaz\LaravelZatca\Support\CertificateLoader;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;

class TenantCredentialHealthChecker
{
    public function __construct(
        protected CertificateLoader $certificateLoader,
        protected ConfigRepository $config
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forTenant(ZatcaTenant $tenant): array
    {
        return $tenant->credentials
            ->sortBy('environment')
            ->map(fn (ZatcaTenantCredential $credential): array => $this->forCredential($tenant, $credential))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function forCredential(ZatcaTenant $tenant, ZatcaTenantCredential $credential): array
    {
        $checkedAt = now();
        $issues = [];
        $certificateValue = $credential->production_binary_security_token ?: $credential->compliance_binary_security_token;
        $certificateSource = $credential->production_binary_security_token ? 'production' : ($credential->compliance_binary_security_token ? 'compliance' : null);

        if (empty($credential->private_key)) {
            $issues[] = $this->issue(
                'missing_private_key',
                $credential->status === 'draft' ? 'warning' : 'error',
                'Private key is missing.',
                'المفتاح الخاص غير موجود.'
            );
        }

        if ($credential->status === 'compliance_issued' || $credential->status === 'production_issued') {
            if (empty($credential->compliance_binary_security_token)) {
                $issues[] = $this->issue(
                    'missing_compliance_token',
                    'error',
                    'Compliance binary security token is missing.',
                    'رمز الأمان الثنائي الخاص بالامتثال غير موجود.'
                );
            }

            if (empty($credential->compliance_secret)) {
                $issues[] = $this->issue(
                    'missing_compliance_secret',
                    'error',
                    'Compliance secret is missing.',
                    'سر الامتثال غير موجود.'
                );
            }
        }

        if ($credential->status === 'production_issued') {
            if (empty($credential->production_binary_security_token)) {
                $issues[] = $this->issue(
                    'missing_production_token',
                    'error',
                    'Production binary security token is missing.',
                    'رمز الأمان الثنائي الخاص بالإنتاج غير موجود.'
                );
            }

            if (empty($credential->production_secret)) {
                $issues[] = $this->issue(
                    'missing_production_secret',
                    'error',
                    'Production secret is missing.',
                    'سر الإنتاج غير موجود.'
                );
            }
        }

        $certificateHealth = [
            'source' => $certificateSource,
            'vat_number' => null,
            'subject_common_name' => null,
            'issuer_common_name' => null,
            'valid_from' => null,
            'valid_to' => null,
            'days_until_expiry' => null,
            'is_expired' => false,
            'is_expiring_soon' => false,
        ];

        if ($certificateValue === null || trim($certificateValue) === '') {
            $issues[] = $this->issue(
                'missing_authentication_certificate',
                $credential->status === 'draft' ? 'warning' : 'error',
                'Authentication certificate is missing.',
                'شهادة المصادقة غير موجودة.'
            );
        } else {
            $inspection = $this->certificateLoader->inspectCertificate($certificateValue);

            if ($inspection === null) {
                $issues[] = $this->issue(
                    'invalid_authentication_certificate',
                    'error',
                    'Authentication certificate could not be parsed.',
                    'تعذر تحليل شهادة المصادقة.'
                );
            } else {
                $certificateHealth['vat_number'] = $inspection['vat_number'];
                $certificateHealth['subject_common_name'] = $inspection['subject']['CN'] ?? null;
                $certificateHealth['issuer_common_name'] = $inspection['issuer']['CN'] ?? null;

                if (is_int($inspection['valid_from'] ?? null)) {
                    $certificateHealth['valid_from'] = CarbonImmutable::createFromTimestampUTC($inspection['valid_from'])->toIso8601String();
                }

                if (is_int($inspection['valid_to'] ?? null)) {
                    $validTo = CarbonImmutable::createFromTimestampUTC($inspection['valid_to']);
                    $certificateHealth['valid_to'] = $validTo->toIso8601String();
                    $certificateHealth['days_until_expiry'] = now()->diffInDays($validTo, false);
                    $certificateHealth['is_expired'] = $validTo->isPast();
                    $certificateHealth['is_expiring_soon'] = ! $certificateHealth['is_expired']
                        && $certificateHealth['days_until_expiry'] <= $this->expiryWarningDays();

                    if ($certificateHealth['is_expired']) {
                        $issues[] = $this->issue(
                            'certificate_expired',
                            'error',
                            'Authentication certificate has expired.',
                            'انتهت صلاحية شهادة المصادقة.'
                        );
                    } elseif ($certificateHealth['is_expiring_soon']) {
                        $issues[] = $this->issue(
                            'certificate_expiring_soon',
                            'warning',
                            'Authentication certificate is expiring soon.',
                            'ستنتهي صلاحية شهادة المصادقة قريباً.'
                        );
                    }
                }

                if (($inspection['vat_number'] ?? null) !== null && (string) $inspection['vat_number'] !== (string) $tenant->vat_number) {
                    $issues[] = $this->issue(
                        'certificate_vat_mismatch',
                        'error',
                        'Certificate VAT does not match the tenant VAT number.',
                        'رقم ضريبة الشهادة لا يطابق رقم ضريبة المستأجر.'
                    );
                }
            }
        }

        $status = $this->statusFromIssues($issues);
        $this->persistValidationTimestamp($credential, $checkedAt);

        return [
            'environment' => $credential->environment,
            'status' => $status,
            'labels' => $this->statusLabels($status),
            'checked_at' => $checkedAt->toIso8601String(),
            'issues' => $issues,
            'certificate' => $certificateHealth,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function statusFromIssues(array $issues): string
    {
        foreach ($issues as $issue) {
            if (($issue['severity'] ?? null) === 'error') {
                return 'error';
            }
        }

        foreach ($issues as $issue) {
            if (($issue['severity'] ?? null) === 'warning') {
                return 'warning';
            }
        }

        return 'healthy';
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(string $code, string $severity, string $english, string $arabic): array
    {
        return [
            'code' => $code,
            'severity' => $severity,
            'message' => [
                'en' => $english,
                'ar' => $arabic,
            ],
        ];
    }

    /**
     * @return array{en: string, ar: string}
     */
    private function statusLabels(string $status): array
    {
        return match ($status) {
            'healthy' => ['en' => 'Healthy', 'ar' => 'سليم'],
            'warning' => ['en' => 'Warning', 'ar' => 'تحذير'],
            default => ['en' => 'Error', 'ar' => 'خطأ'],
        };
    }

    private function expiryWarningDays(): int
    {
        return (int) $this->config->get('zatca.health.certificate_expiry_warning_days', 30);
    }

    private function persistValidationTimestamp(ZatcaTenantCredential $credential, \Illuminate\Support\Carbon $checkedAt): void
    {
        $credential->forceFill([
            'last_validated_at' => $checkedAt,
        ])->saveQuietly();
    }
}
