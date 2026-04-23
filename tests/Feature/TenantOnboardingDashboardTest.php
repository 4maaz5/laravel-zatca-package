<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;
use Maaz\LaravelZatca\Tests\TestCase;

class TenantOnboardingDashboardTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_it_renders_the_dashboard_in_english(): void
    {
        $this->createTenant('bi-tech', 'BI Technology Company', '313138851500003');

        $response = $this->get('/zatca/onboarding/dashboard?lang=en');

        $response->assertOk()
            ->assertSee('Tenant Onboarding Dashboard')
            ->assertSee('BI Technology Company')
            ->assertSee('Overview')
            ->assertSee('Setup')
            ->assertSee('Invoices')
            ->assertSee('Monitoring')
            ->assertSee('Notification Hooks');
    }

    public function test_it_renders_the_dashboard_in_arabic(): void
    {
        $tenant = $this->createTenant('bi-tech-ar', 'BI Technology Company', '313138851500003');
        $tenant->update([
            'legal_name_ar' => 'شركة بي آي للتقنية',
            'seller_name_ar' => 'بي آي للتقنية',
            'locale' => 'ar',
        ]);

        $response = $this->get('/zatca/onboarding/dashboard/bi-tech-ar?lang=ar');

        $response->assertOk()
            ->assertSee('لوحة متابعة تهيئة المستأجرين', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('data-locale=\'"ar"\'', false)
            ->assertSee('خطافات الإشعارات', false);
    }

    public function test_tenant_users_do_not_see_admin_tenant_controls(): void
    {
        $this->createTenant('bi-tech', 'BI Technology Company', '313138851500003');
        $this->createTenant('another-tenant', 'Another Company', '300000000000013');

        $this->actingAs($this->tenantUser('bi-tech'));

        $response = $this->get('/zatca/onboarding/dashboard?lang=en');

        $response->assertOk()
            ->assertSee('BI Technology Company')
            ->assertDontSee('Another Company')
            ->assertDontSee('id="new-tenant-toggle"', false);
    }

    public function test_super_admin_users_still_see_admin_tenant_controls(): void
    {
        $this->createTenant('bi-tech', 'BI Technology Company', '313138851500003');
        $this->createTenant('another-tenant', 'Another Company', '300000000000013');

        $this->actingAs($this->adminUser());

        $response = $this->get('/zatca/onboarding/dashboard?lang=en');

        $response->assertOk()
            ->assertSee('id="new-tenant-toggle"', false)
            ->assertSee('BI Technology Company')
            ->assertSee('Another Company');
    }

    private function createTenant(string $key, string $sellerName, string $vatNumber): ZatcaTenant
    {
        $tenant = ZatcaTenant::query()->create([
            'key' => $key,
            'legal_name' => $sellerName,
            'seller_name' => $sellerName,
            'vat_number' => $vatNumber,
            'default_environment' => 'sandbox',
            'locale' => 'en',
        ]);

        ZatcaTenantCredential::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'status' => 'draft',
        ]);

        return $tenant;
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

    private function adminUser(): Authenticatable
    {
        return new class () extends Authenticatable {
            public bool $is_super_admin = true;
        };
    }
}
