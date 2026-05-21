<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Maaz\LaravelZatca\Events\TenantCredentialHealthAlertDetected;
use Maaz\LaravelZatca\Tenancy\Health\TenantCredentialHealthChecker;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;

class MonitorTenantHealthCommand extends Command
{
    protected $signature = 'zatca:tenant-health-monitor
        {--tenant= : Limit monitoring to a specific tenant id or key}
        {--minimum-severity=warning : Dispatch alerts for warning or error, or only error}
        {--fail-on=error : Return a failure code on warning, error, or never}
        {--show-issues : Print detailed issues for triggered alerts}';

    protected $description = 'Monitor tenant certificate and credential health and dispatch alerts for actionable issues.';

    public function handle(TenantCredentialHealthChecker $checker): int
    {
        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->components->warn('No tenants matched the requested health monitor.');

            return self::SUCCESS;
        }

        $triggeredAlerts = 0;
        $hasWarnings = false;
        $hasErrors = false;

        foreach ($tenants as $tenant) {
            $tenant->loadMissing('credentials');

            foreach ($tenant->credentials->sortBy('environment') as $credential) {
                $health = $checker->forCredential($tenant, $credential);
                $status = (string) $health['status'];

                if ($status === 'warning') {
                    $hasWarnings = true;
                }

                if ($status === 'error') {
                    $hasErrors = true;
                }

                if (! $this->shouldDispatch($status)) {
                    continue;
                }

                $triggeredAlerts++;
                Event::dispatch(new TenantCredentialHealthAlertDetected($tenant, $credential, $health));

                if ((bool) $this->option('show-issues')) {
                    $this->newLine();
                    $this->line('Tenant: ' . ($tenant->key ?: (string) $tenant->getKey()) . ' [' . $credential->environment . ']');

                    foreach ($health['issues'] as $issue) {
                        $this->line('- ' . strtoupper((string) $issue['severity']) . ' ' . (string) $issue['code'] . ': ' . (string) ($issue['message']['en'] ?? ''));
                    }
                }
            }
        }

        $this->components->info((string) trans('zatca::commands.tenant_health_monitor_complete'));
        $this->line('Alerts dispatched: ' . $triggeredAlerts);

        return match ($this->failOn()) {
            'warning' => ($hasWarnings || $hasErrors) ? self::FAILURE : self::SUCCESS,
            'error' => $hasErrors ? self::FAILURE : self::SUCCESS,
            default => self::SUCCESS,
        };
    }

    private function tenants()
    {
        $tenant = $this->stringOption('tenant');

        return ZatcaTenant::query()
            ->when($tenant !== null, static function ($query) use ($tenant): void {
                $query->whereTenantIdentifier($tenant);
            })
            ->orderBy('id')
            ->get();
    }

    private function shouldDispatch(string $status): bool
    {
        $minimumSeverity = $this->minimumSeverity();

        return match ($minimumSeverity) {
            'error' => $status === 'error',
            default => in_array($status, ['warning', 'error'], true),
        };
    }

    private function minimumSeverity(): string
    {
        $severity = strtolower((string) $this->option('minimum-severity'));

        return in_array($severity, ['warning', 'error'], true) ? $severity : 'warning';
    }

    private function failOn(): string
    {
        $value = strtolower((string) $this->option('fail-on'));

        return in_array($value, ['never', 'warning', 'error'], true) ? $value : 'error';
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
