<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Security;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Encryption\Encrypter;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;
use RuntimeException;

class CredentialRotationService
{
    public function __construct(
        protected ConfigRepository $config,
        protected DatabaseManager $database
    ) {
    }

    /**
     * @param  list<string>  $sourceKeys
     * @return array{dry_run: bool, credentials_scanned: int, credentials_rotated: int}
     */
    public function rotate(array $sourceKeys = [], ?string $targetKey = null, ?string $tenant = null, bool $dryRun = false): array
    {
        $targetEncrypter = $this->makeEncrypter($targetKey ?? (string) $this->config->get('app.key'));
        $sourceEncrypters = $this->buildSourceEncrypters($sourceKeys, $targetEncrypter);

        $query = $this->database->table('zatca_tenant_credentials as credentials')
            ->select(array_map(
                static fn (string $column): string => 'credentials.' . $column,
                array_merge(['id', 'tenant_id'], ZatcaTenantCredential::encryptedAttributes())
            ))
            ->orderBy('credentials.id');

        if ($tenant !== null && trim($tenant) !== '') {
            $query->join('zatca_tenants as tenants', 'tenants.id', '=', 'credentials.tenant_id')
                ->where(static function ($builder) use ($tenant): void {
                    $tenant = trim($tenant);

                    $builder->where('tenants.key', $tenant);

                    if (preg_match('/^\d+$/', $tenant) === 1) {
                        $builder->orWhere('credentials.tenant_id', $tenant);
                    }
                });
        }

        $rows = $query->get();
        $rotated = 0;

        foreach ($rows as $row) {
            $updates = [];

            foreach (ZatcaTenantCredential::encryptedAttributes() as $column) {
                $encryptedValue = $row->{$column} ?? null;

                if (! is_string($encryptedValue) || $encryptedValue === '') {
                    continue;
                }

                $decrypted = $this->decryptWithAnyKey($encryptedValue, $sourceEncrypters, (int) $row->id, $column);
                $updates[$column] = $targetEncrypter->encryptString($decrypted);
            }

            if ($updates === []) {
                continue;
            }

            $rotated++;

            if ($dryRun) {
                continue;
            }

            $updates['updated_at'] = now();

            $this->database->table('zatca_tenant_credentials')
                ->where('id', $row->id)
                ->update($updates);
        }

        return [
            'dry_run' => $dryRun,
            'credentials_scanned' => $rows->count(),
            'credentials_rotated' => $rotated,
        ];
    }

    /**
     * @param  list<string>  $sourceKeys
     * @return list<Encrypter>
     */
    private function buildSourceEncrypters(array $sourceKeys, Encrypter $targetEncrypter): array
    {
        $keys = array_values(array_unique(array_filter([
            ...$sourceKeys,
            ...$this->configuredPreviousKeys(),
            (string) $this->config->get('app.key'),
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '')));

        $encrypters = [];

        foreach ($keys as $key) {
            $encrypters[] = $this->makeEncrypter($key);
        }

        $encrypters[] = $targetEncrypter;

        return $encrypters;
    }

    /**
     * @return list<string>
     */
    private function configuredPreviousKeys(): array
    {
        $configured = $this->config->get('app.previous_keys', []);

        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(array_map(static function (mixed $value): ?string {
            if (! is_string($value)) {
                return null;
            }

            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }, $configured)));
    }

    private function makeEncrypter(string $key): Encrypter
    {
        try {
            return new Encrypter($this->parseKey($key), (string) $this->config->get('app.cipher', 'AES-256-CBC'));
        } catch (\Throwable $exception) {
            throw new RuntimeException((string) trans('zatca::exceptions.credential_rotation_invalid_key'), previous: $exception);
        }
    }

    private function parseKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if (! is_string($decoded) || $decoded === '') {
                throw new RuntimeException((string) trans('zatca::exceptions.credential_rotation_invalid_key'));
            }

            return $decoded;
        }

        return $key;
    }

    /**
     * @param  list<Encrypter>  $encrypters
     */
    private function decryptWithAnyKey(string $encryptedValue, array $encrypters, int $credentialId, string $column): string
    {
        foreach ($encrypters as $encrypter) {
            try {
                return $encrypter->decryptString($encryptedValue);
            } catch (DecryptException) {
                continue;
            }
        }

        throw new RuntimeException((string) trans('zatca::exceptions.credential_rotation_undecryptable', [
            'id' => $credentialId,
            'column' => $column,
        ]));
    }
}
