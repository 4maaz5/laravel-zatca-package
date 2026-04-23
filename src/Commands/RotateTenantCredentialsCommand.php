<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Maaz\LaravelZatca\Tenancy\Security\CredentialRotationService;

class RotateTenantCredentialsCommand extends Command
{
    protected $signature = 'zatca:rotate-credentials
        {--tenant= : Limit rotation to a specific tenant id or key}
        {--from=* : Previous APP_KEY value(s) used to decrypt stored credentials}
        {--to= : Target APP_KEY value, defaults to the current app key}
        {--dry-run : Verify that credentials can be decrypted and re-encrypted without writing changes}';

    protected $description = 'Re-encrypt stored tenant credentials after an APP_KEY change.';

    public function handle(CredentialRotationService $service): int
    {
        try {
            $result = $service->rotate(
                sourceKeys: $this->sourceKeys(),
                targetKey: $this->stringOption('to'),
                tenant: $this->stringOption('tenant'),
                dryRun: (bool) $this->option('dry-run')
            );
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $messageKey = $result['dry_run']
            ? 'zatca::commands.credential_rotation_dry_run_complete'
            : 'zatca::commands.credential_rotation_complete';

        $this->components->info((string) trans($messageKey));
        $this->line('Credentials scanned: ' . $result['credentials_scanned']);
        $this->line('Credentials re-encrypted: ' . $result['credentials_rotated']);

        if ($tenant = $this->stringOption('tenant')) {
            $this->line('Tenant filter: ' . $tenant);
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function sourceKeys(): array
    {
        $values = $this->option('from');

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(static function (mixed $value): ?string {
            if (! is_string($value)) {
                return null;
            }

            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }, $values)));
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
