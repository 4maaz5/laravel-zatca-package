<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Maaz\LaravelZatca\DTOs\TenantContext;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantInvoiceState;
use Maaz\LaravelZatca\Tenancy\Repositories\DatabaseTenantConfigRepository;
use Maaz\LaravelZatca\Tests\TestCase;

class DatabaseTenantConfigRepositoryTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_it_builds_tenant_config_from_database_records(): void
    {
        $tenant = ZatcaTenant::query()->create([
            'key' => 'bi-tech',
            'legal_name' => 'BI Technology Company',
            'legal_name_ar' => 'شركة بي آي للتقنية',
            'seller_name' => 'BI Technology Company',
            'seller_name_ar' => 'بي آي للتقنية',
            'vat_number' => '313138851500003',
            'crn' => '7050816433',
            'branch_name' => 'Riyadh Branch',
            'branch_name_ar' => 'فرع الرياض',
            'country_code' => 'SA',
            'city' => 'Riyadh',
            'district' => 'AL Duraihemiyah',
            'street' => 'Saidya',
            'building_number' => '7036',
            'additional_number' => '7036',
            'postal_code' => '12796',
            'locale' => 'en',
            'default_environment' => 'sandbox',
            'onboarding_status' => 'production_issued',
            'is_active' => true,
        ]);

        ZatcaTenantCredential::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'status' => 'active',
            'private_key' => 'private-key',
            'production_binary_security_token' => 'production-token',
            'production_secret' => 'production-secret',
            'compliance_request_id' => '1234567890123',
        ]);

        ZatcaTenantInvoiceState::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'last_icv' => 41,
            'previous_invoice_hash' => 'hash-41',
        ]);

        $repository = new DatabaseTenantConfigRepository($this->app['config']);
        $config = $repository->forTenant(new TenantContext('bi-tech'));

        $this->assertSame((string) $tenant->getKey(), $config->tenantId);
        $this->assertSame('BI Technology Company', $config->sellerName);
        $this->assertSame('313138851500003', $config->sellerVatNumber);
        $this->assertSame('Riyadh Branch', $config->branchName);
        $this->assertSame('production-token', $config->certificates['certificate']);
        $this->assertSame('private-key', $config->certificates['private_key']);
        $this->assertSame('production-secret', $config->api['secret']);
        $this->assertSame('42', $config->meta['icv']);
        $this->assertSame('hash-41', $config->meta['pih']);
        $this->assertSame('production_issued', $config->meta['onboarding_status']);
    }

    public function test_migrations_create_expected_saas_tables(): void
    {
        $this->assertTrue(Schema::hasTable('zatca_tenants'));
        $this->assertTrue(Schema::hasTable('zatca_tenant_credentials'));
        $this->assertTrue(Schema::hasTable('zatca_tenant_invoice_states'));
    }
}
