<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Maaz\LaravelZatca\DTOs\GeneratedCsrResult;
use Maaz\LaravelZatca\Services\ZatcaManager;
use Maaz\LaravelZatca\Tenancy\Onboarding\TenantOnboardingFlow;
use Maaz\LaravelZatca\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TenantOnboardingApiTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    #[Test]
    public function it_creates_and_shows_a_bilingual_tenant_profile(): void
    {
        $create = $this->postJson('/api/zatca/onboarding/tenants', [
            'key' => 'bi-tech',
            'legal_name' => 'BI Technology Company',
            'legal_name_ar' => 'شركة بي آي للتقنية',
            'seller_name' => 'BI Technology Company',
            'seller_name_ar' => 'بي آي للتقنية',
            'vat_number' => '313138851500003',
            'crn' => '7050816433',
            'branch_name' => 'Riyadh Branch',
            'branch_name_ar' => 'فرع الرياض',
            'street' => 'Saidya',
            'district' => 'AL Duraihemiyah',
            'city' => 'Riyadh',
            'building_number' => '7036',
            'additional_number' => '7036',
            'postal_code' => '12796',
            'locale' => 'ar',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.key', 'bi-tech')
            ->assertJsonPath('data.legal_name_ar', 'شركة بي آي للتقنية')
            ->assertJsonPath('data.onboarding_status_labels.ar', 'تم استكمال الملف');

        $show = $this->getJson('/api/zatca/onboarding/tenants/bi-tech');

        $show->assertOk()
            ->assertJsonPath('data.vat_number', '313138851500003')
            ->assertJsonCount(2, 'data.credentials')
            ->assertJsonCount(2, 'data.invoice_states')
            ->assertJsonPath('data.credentials.0.health.status', 'warning');

        $index = $this->getJson('/api/zatca/onboarding/tenants');

        $index->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'bi-tech');
    }

    #[Test]
    public function it_runs_csr_and_csid_onboarding_actions_and_persists_status(): void
    {
        $this->postJson('/api/zatca/onboarding/tenants', [
            'key' => 'tenant-csid',
            'legal_name' => 'BI Technology Company',
            'vat_number' => '313138851500003',
            'branch_name' => 'Riyadh Branch',
        ])->assertCreated();

        $manager = $this->mock(ZatcaManager::class);
        $manager->shouldReceive('forTenant')
            ->times(3)
            ->with('tenant-csid')
            ->andReturnSelf();
        $manager->shouldReceive('generateCsr')
            ->once()
            ->andReturn(new GeneratedCsrResult(
                csrPath: 'csr-path',
                privateKeyPath: 'key-path',
                csrBase64: 'csr-base64',
                csrPem: 'csr-pem',
                privateKeyPem: 'private-key-pem',
                properties: ['common_name' => 'TST-7050816433-313138851500003']
            ));
        $manager->shouldReceive('onboardComplianceCsid')
            ->once()
            ->andReturn([
                'success' => true,
                'body' => [
                    'requestID' => 1234567890123,
                    'binarySecurityToken' => 'compliance-token',
                    'secret' => 'compliance-secret',
                ],
            ]);
        $manager->shouldReceive('onboardProductionCsid')
            ->once()
            ->andReturn([
                'success' => true,
                'body' => [
                    'requestID' => 30368,
                    'binarySecurityToken' => 'production-token',
                    'secret' => 'production-secret',
                ],
            ]);

        $this->app->instance(ZatcaManager::class, $manager);
        $this->app->forgetInstance(TenantOnboardingFlow::class);

        $this->postJson('/api/zatca/onboarding/tenants/tenant-csid/csr', [
            'common_name' => 'TST-7050816433-313138851500003',
            'serial_number' => '1-BI-TECH|2-LARAVEL-ZATCA|3-guid',
            'location_address' => 'RRRD7036',
            'industry_business_category' => 'Technology services',
        ])->assertOk()
            ->assertJsonPath('tenant.onboarding_status', 'csr_generated')
            ->assertJsonPath('csr.base64', 'csr-base64');

        $this->postJson('/api/zatca/onboarding/tenants/tenant-csid/compliance-csid', [
            'otp' => '664608',
        ])->assertOk()
            ->assertJsonPath('tenant.onboarding_status', 'compliance_issued')
            ->assertJsonPath('compliance_csid.requestID', 1234567890123);

        $this->postJson('/api/zatca/onboarding/tenants/tenant-csid/production-csid')
            ->assertOk()
            ->assertJsonPath('tenant.onboarding_status', 'production_issued')
            ->assertJsonPath('production_csid.requestID', 30368);
    }

    #[Test]
    public function generating_a_new_csr_clears_old_csid_material_for_that_environment(): void
    {
        $this->postJson('/api/zatca/onboarding/tenants', [
            'key' => 'tenant-rotate',
            'legal_name' => 'BI Technology Company',
            'vat_number' => '313138851500003',
            'branch_name' => 'Riyadh Branch',
        ])->assertCreated();

        $manager = $this->mock(ZatcaManager::class);
        $manager->shouldReceive('forTenant')
            ->once()
            ->with('tenant-rotate')
            ->andReturnSelf();
        $manager->shouldReceive('generateCsr')
            ->once()
            ->andReturn(new GeneratedCsrResult(
                csrPath: 'csr-path',
                privateKeyPath: 'key-path',
                csrBase64: 'csr-base64-new',
                csrPem: 'csr-pem-new',
                privateKeyPem: 'private-key-pem-new',
                properties: ['common_name' => 'TST-7050816433-313138851500003']
            ));

        $this->app->instance(ZatcaManager::class, $manager);
        $this->app->forgetInstance(TenantOnboardingFlow::class);

        $tenant = \Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant::where('key', 'tenant-rotate')->firstOrFail();
        $credential = $tenant->credentials()->where('environment', 'sandbox')->firstOrFail();
        $credential->forceFill([
            'status' => 'production_issued',
            'private_key' => 'old-private-key',
            'compliance_request_id' => 'old-request-id',
            'compliance_binary_security_token' => 'old-compliance-token',
            'compliance_secret' => 'old-compliance-secret',
            'production_binary_security_token' => 'old-production-token',
            'production_secret' => 'old-production-secret',
            'metadata' => [
                'last_compliance_response' => ['old' => true],
                'last_production_response' => ['old' => true],
            ],
        ])->save();

        $this->postJson('/api/zatca/onboarding/tenants/tenant-rotate/csr', [
            'environment' => 'sandbox',
            'common_name' => 'TST-7050816433-313138851500003',
            'serial_number' => '1-BI-TECH|2-LARAVEL-ZATCA|3-guid',
            'location_address' => 'RRRD7036',
            'industry_business_category' => 'Technology services',
        ])->assertOk()
            ->assertJsonPath('tenant.onboarding_status', 'csr_generated')
            ->assertJsonPath('tenant.credentials.0.has_compliance_csid', false)
            ->assertJsonPath('tenant.credentials.0.has_production_csid', false);
    }

    #[Test]
    public function tenant_users_only_see_and_access_their_own_tenant(): void
    {
        $this->postJson('/api/zatca/onboarding/tenants', [
            'key' => 'bi-tech',
            'legal_name' => 'BI Technology Company',
            'vat_number' => '313138851500003',
            'branch_name' => 'Riyadh Branch',
        ])->assertCreated();

        $this->postJson('/api/zatca/onboarding/tenants', [
            'key' => 'another-tenant',
            'legal_name' => 'Another Company',
            'vat_number' => '300000000000013',
            'branch_name' => 'Jeddah Branch',
        ])->assertCreated();

        $this->actingAs($this->tenantUser('bi-tech'));

        $this->getJson('/api/zatca/onboarding/tenants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'bi-tech');

        $this->getJson('/api/zatca/onboarding/tenants/bi-tech')
            ->assertOk()
            ->assertJsonPath('data.key', 'bi-tech');

        $this->getJson('/api/zatca/onboarding/tenants/another-tenant')
            ->assertForbidden();

        $this->postJson('/api/zatca/onboarding/tenants', [
            'key' => 'forbidden-tenant',
            'legal_name' => 'Forbidden Company',
            'vat_number' => '300000000000023',
        ])->assertForbidden();
    }

    private function tenantUser(string $tenantKey): Authenticatable
    {
        return new class ($tenantKey) extends Authenticatable {
            public string $tenant_key;

            public function __construct(string $tenantKey)
            {
                $this->tenant_key = $tenantKey;
            }
        };
    }
}
