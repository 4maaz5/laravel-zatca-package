<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Maaz\LaravelZatca\DTOs\TenantContext;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;
use Maaz\LaravelZatca\Tenancy\Repositories\DatabaseTenantConfigRepository;
use Maaz\LaravelZatca\Tests\TestCase;

class TenantCredentialEncryptionTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_it_encrypts_sensitive_credential_fields_at_rest(): void
    {
        $tenant = ZatcaTenant::query()->create([
            'key' => 'secure-tenant',
            'legal_name' => 'Secure Tenant',
            'seller_name' => 'Secure Tenant',
            'vat_number' => '313138851500003',
            'default_environment' => 'sandbox',
            'locale' => 'en',
        ]);

        $credential = ZatcaTenantCredential::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'status' => 'production_issued',
            'csr_base64' => 'csr-base64',
            'csr_pem' => 'csr-pem',
            'private_key' => 'private-key',
            'private_key_secret' => 'private-secret',
            'compliance_binary_security_token' => 'compliance-token',
            'compliance_secret' => 'compliance-secret',
            'production_binary_security_token' => 'production-token',
            'production_secret' => 'production-secret',
        ]);

        $raw = DB::table('zatca_tenant_credentials')
            ->where('id', $credential->getKey())
            ->first([
                'csr_base64',
                'csr_pem',
                'private_key',
                'private_key_secret',
                'compliance_binary_security_token',
                'compliance_secret',
                'production_binary_security_token',
                'production_secret',
            ]);

        $this->assertNotNull($raw);
        $this->assertNotSame('csr-base64', $raw->csr_base64);
        $this->assertNotSame('csr-pem', $raw->csr_pem);
        $this->assertNotSame('private-key', $raw->private_key);
        $this->assertNotSame('private-secret', $raw->private_key_secret);
        $this->assertNotSame('compliance-token', $raw->compliance_binary_security_token);
        $this->assertNotSame('compliance-secret', $raw->compliance_secret);
        $this->assertNotSame('production-token', $raw->production_binary_security_token);
        $this->assertNotSame('production-secret', $raw->production_secret);

        $credential->refresh();

        $this->assertSame('csr-base64', $credential->csr_base64);
        $this->assertSame('csr-pem', $credential->csr_pem);
        $this->assertSame('private-key', $credential->private_key);
        $this->assertSame('private-secret', $credential->private_key_secret);
        $this->assertSame('compliance-token', $credential->compliance_binary_security_token);
        $this->assertSame('compliance-secret', $credential->compliance_secret);
        $this->assertSame('production-token', $credential->production_binary_security_token);
        $this->assertSame('production-secret', $credential->production_secret);
    }

    public function test_database_repository_reads_decrypted_credential_values(): void
    {
        $tenant = ZatcaTenant::query()->create([
            'key' => 'decrypted-tenant',
            'legal_name' => 'Decrypted Tenant',
            'seller_name' => 'Decrypted Tenant',
            'vat_number' => '313138851500003',
            'branch_name' => 'Riyadh Branch',
            'default_environment' => 'sandbox',
            'locale' => 'en',
        ]);

        ZatcaTenantCredential::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'status' => 'production_issued',
            'private_key' => 'private-key',
            'production_binary_security_token' => 'production-token',
            'production_secret' => 'production-secret',
            'compliance_request_id' => '1234567890123',
        ]);

        $repository = new DatabaseTenantConfigRepository($this->app['config']);
        $config = $repository->forTenant(new TenantContext('decrypted-tenant'));

        $this->assertSame('production-token', $config->certificates['certificate']);
        $this->assertSame('private-key', $config->certificates['private_key']);
        $this->assertSame('production-secret', $config->api['secret']);
    }
}
