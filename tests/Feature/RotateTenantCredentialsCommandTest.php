<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;
use Maaz\LaravelZatca\Tests\TestCase;

class RotateTenantCredentialsCommandTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_it_reencrypts_tenant_credentials_with_the_current_app_key(): void
    {
        $tenant = ZatcaTenant::query()->create([
            'key' => 'rotate-me',
            'legal_name' => 'Rotate Me',
            'seller_name' => 'Rotate Me',
            'vat_number' => '313138851500003',
            'default_environment' => 'sandbox',
            'locale' => 'en',
        ]);

        $oldKey = 'base64:' . base64_encode(str_repeat('b', 32));
        $oldEncrypter = new Encrypter(str_repeat('b', 32), 'AES-256-CBC');

        DB::table('zatca_tenant_credentials')->insert([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'signer' => 'sdk',
            'status' => 'production_issued',
            'private_key' => $oldEncrypter->encryptString('private-key'),
            'compliance_binary_security_token' => $oldEncrypter->encryptString('compliance-token'),
            'compliance_secret' => $oldEncrypter->encryptString('compliance-secret'),
            'production_binary_security_token' => $oldEncrypter->encryptString('production-token'),
            'production_secret' => $oldEncrypter->encryptString('production-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = DB::table('zatca_tenant_credentials')->value('private_key');

        $this->artisan('zatca:rotate-credentials', [
            '--from' => [$oldKey],
        ])
            ->expectsOutputToContain('Tenant credential re-encryption completed successfully.')
            ->expectsOutputToContain('Credentials re-encrypted: 1')
            ->assertExitCode(Command::SUCCESS);

        $credential = ZatcaTenantCredential::query()->firstOrFail();
        $after = DB::table('zatca_tenant_credentials')->value('private_key');

        $this->assertSame('private-key', $credential->private_key);
        $this->assertSame('production-token', $credential->production_binary_security_token);
        $this->assertNotSame($before, $after);
    }

    public function test_it_supports_dry_run_without_writing_changes(): void
    {
        $tenant = ZatcaTenant::query()->create([
            'key' => 'rotate-dry-run',
            'legal_name' => 'Rotate Dry Run',
            'seller_name' => 'Rotate Dry Run',
            'vat_number' => '313138851500003',
            'default_environment' => 'sandbox',
            'locale' => 'en',
        ]);

        $oldKey = 'base64:' . base64_encode(str_repeat('c', 32));
        $oldEncrypter = new Encrypter(str_repeat('c', 32), 'AES-256-CBC');

        DB::table('zatca_tenant_credentials')->insert([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'signer' => 'sdk',
            'status' => 'production_issued',
            'private_key' => $oldEncrypter->encryptString('private-key'),
            'production_secret' => $oldEncrypter->encryptString('production-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = DB::table('zatca_tenant_credentials')->value('private_key');

        $this->artisan('zatca:rotate-credentials', [
            '--from' => [$oldKey],
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Tenant credential re-encryption dry run completed successfully.')
            ->assertExitCode(Command::SUCCESS);

        $after = DB::table('zatca_tenant_credentials')->value('private_key');

        $this->assertSame($before, $after);
    }
}
