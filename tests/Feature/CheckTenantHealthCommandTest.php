<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Console\Command;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;
use Maaz\LaravelZatca\Tests\TestCase;

class CheckTenantHealthCommandTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_it_reports_errors_for_unhealthy_tenant_credentials(): void
    {
        $tenant = ZatcaTenant::query()->create([
            'key' => 'health-command',
            'legal_name' => 'Health Command',
            'seller_name' => 'Health Command',
            'vat_number' => '313138851500003',
            'default_environment' => 'sandbox',
            'locale' => 'en',
        ]);

        ZatcaTenantCredential::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'status' => 'production_issued',
        ]);

        $this->artisan('zatca:tenant-health', [
            '--tenant' => 'health-command',
            '--show-issues' => true,
        ])
            ->expectsOutputToContain('health-command')
            ->expectsOutputToContain('missing_private_key')
            ->expectsOutputToContain('Tenant credential health check completed successfully.')
            ->assertExitCode(Command::FAILURE);
    }
}
