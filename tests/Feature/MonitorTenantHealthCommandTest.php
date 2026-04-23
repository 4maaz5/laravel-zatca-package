<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Maaz\LaravelZatca\Events\TenantCredentialHealthAlertDetected;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;
use Maaz\LaravelZatca\Tests\TestCase;

class MonitorTenantHealthCommandTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_it_dispatches_alert_events_for_warning_or_error_health_items(): void
    {
        Event::fake();

        $tenant = ZatcaTenant::query()->create([
            'key' => 'monitor-health',
            'legal_name' => 'Monitor Health',
            'seller_name' => 'Monitor Health',
            'vat_number' => '313138851500003',
            'default_environment' => 'sandbox',
            'locale' => 'en',
        ]);

        ZatcaTenantCredential::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'status' => 'draft',
        ]);

        $this->artisan('zatca:tenant-health-monitor', [
            '--tenant' => 'monitor-health',
            '--minimum-severity' => 'warning',
        ])
            ->expectsOutputToContain('Alerts dispatched: 1')
            ->assertExitCode(Command::SUCCESS);

        Event::assertDispatched(TenantCredentialHealthAlertDetected::class, 1);
    }

    public function test_it_can_ignore_warnings_for_exit_status(): void
    {
        Event::fake();

        $tenant = ZatcaTenant::query()->create([
            'key' => 'monitor-health-ok',
            'legal_name' => 'Monitor Health OK',
            'seller_name' => 'Monitor Health OK',
            'vat_number' => '313138851500003',
            'default_environment' => 'sandbox',
            'locale' => 'en',
        ]);

        ZatcaTenantCredential::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'status' => 'draft',
        ]);

        $this->artisan('zatca:tenant-health-monitor', [
            '--tenant' => 'monitor-health-ok',
            '--minimum-severity' => 'warning',
            '--fail-on' => 'error',
        ])
            ->assertExitCode(Command::SUCCESS);
    }
}
