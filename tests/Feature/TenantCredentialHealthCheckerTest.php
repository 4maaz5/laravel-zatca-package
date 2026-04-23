<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Maaz\LaravelZatca\Tenancy\Health\TenantCredentialHealthChecker;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;
use Maaz\LaravelZatca\Tests\TestCase;

class TenantCredentialHealthCheckerTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_it_reports_missing_credential_material_as_errors(): void
    {
        $tenant = ZatcaTenant::query()->create([
            'key' => 'health-missing',
            'legal_name' => 'Health Missing',
            'seller_name' => 'Health Missing',
            'vat_number' => '313138851500003',
            'default_environment' => 'sandbox',
            'locale' => 'en',
        ]);

        $credential = ZatcaTenantCredential::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'status' => 'production_issued',
        ]);

        $checker = $this->app->make(TenantCredentialHealthChecker::class);
        $health = $checker->forCredential($tenant, $credential);
        $codes = collect($health['issues'])->pluck('code')->all();

        $this->assertSame('error', $health['status']);
        $credential->refresh();
        $this->assertNotNull($credential->last_validated_at);
        $this->assertContains('missing_private_key', $codes);
        $this->assertContains('missing_authentication_certificate', $codes);
        $this->assertContains('missing_compliance_token', $codes);
        $this->assertContains('missing_production_token', $codes);
    }

    public function test_it_detects_certificate_vat_mismatches(): void
    {
        $tenant = ZatcaTenant::query()->create([
            'key' => 'health-vat',
            'legal_name' => 'Health VAT',
            'seller_name' => 'Health VAT',
            'vat_number' => '313138851500003',
            'default_environment' => 'sandbox',
            'locale' => 'en',
        ]);

        ZatcaTenantCredential::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'status' => 'production_issued',
            'private_key' => 'private-key',
            'production_binary_security_token' => $this->sdkCertificate(),
            'production_secret' => 'secret',
            'compliance_binary_security_token' => $this->sdkCertificate(),
            'compliance_secret' => 'secret',
        ]);

        $checker = $this->app->make(TenantCredentialHealthChecker::class);
        $health = $checker->forTenant($tenant->load('credentials'))[0];
        $codes = collect($health['issues'])->pluck('code')->all();

        $this->assertContains('certificate_vat_mismatch', $codes);
        $this->assertSame('error', $health['status']);
        $this->assertNotNull($health['certificate']['valid_to']);
    }

    private function sdkCertificate(): string
    {
        $path = dirname(__DIR__, 2) . '/.net sdk/zatca-einvoicing-sdk-DotNet-238-R3.4.8/Data/Certificates/cert.pem';

        if (! is_file($path)) {
            $this->markTestSkipped('Official SDK certificate fixture is not available.');
        }

        $contents = file_get_contents($path);

        $this->assertIsString($contents);

        return $contents;
    }
}
