<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Commands;

use Illuminate\Console\Command;
use Maaz\LaravelZatca\Tenancy\Health\TenantCredentialHealthChecker;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;

class CheckTenantHealthCommand extends Command
{
    protected $signature = 'zatca:tenant-health
        {--tenant= : Limit the check to a specific tenant id or key}
        {--show-issues : Print detailed issues for each environment}';

    protected $description = 'Check tenant certificate and credential health for ZATCA onboarding and submissions.';

    public function handle(TenantCredentialHealthChecker $checker): int
    {
        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->components->warn('No tenants matched the requested health check.');

            return self::SUCCESS;
        }

        $rows = [];
        $hasErrors = false;

        foreach ($tenants as $tenant) {
            $tenant->loadMissing('credentials');
            $healthItems = $checker->forTenant($tenant);

            foreach ($healthItems as $item) {
                $rows[] = [
                    $tenant->key ?: (string) $tenant->getKey(),
                    $item['environment'],
                    strtoupper($item['status']),
                    count($item['issues']),
                    $item['certificate']['vat_number'] ?? '[missing]',
                    $item['certificate']['valid_to'] ?? '[missing]',
                ];

                if ($item['status'] === 'error') {
                    $hasErrors = true;
                }

                if ((bool) $this->option('show-issues') && $item['issues'] !== []) {
                    $this->newLine();
                    $this->line('Tenant: ' . ($tenant->key ?: (string) $tenant->getKey()) . ' [' . $item['environment'] . ']');

                    foreach ($item['issues'] as $issue) {
                        $this->line('- ' . strtoupper((string) $issue['severity']) . ' ' . (string) $issue['code'] . ': ' . (string) ($issue['message']['en'] ?? ''));
                    }
                }
            }
        }

        $this->table(
            ['Tenant', 'Environment', 'Status', 'Issues', 'Cert VAT', 'Cert Expiry'],
            $rows
        );

        $this->components->info((string) trans('zatca::commands.tenant_health_complete'));

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }

    private function tenants()
    {
        $tenant = $this->stringOption('tenant');

        return ZatcaTenant::query()
            ->when($tenant !== null, static function ($query) use ($tenant): void {
                $query->whereKey($tenant)->orWhere('key', $tenant);
            })
            ->orderBy('id')
            ->get();
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
