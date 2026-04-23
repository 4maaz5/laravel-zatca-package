<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Concerns;

trait InteractsWithZatcaTenantWorkspace
{
    public function zatcaTenantKey(): ?string
    {
        foreach (['zatca_tenant_key', 'tenant_key', 'tenant_id'] as $attribute) {
            $value = data_get($this, $attribute);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    public function zatcaCanManageTenants(): bool
    {
        foreach (['is_super_admin', 'is_admin'] as $attribute) {
            if ((bool) data_get($this, $attribute, false) === true) {
                return true;
            }
        }

        foreach (['isSuperAdmin', 'isAdmin'] as $method) {
            if (method_exists($this, $method) && $this->{$method}() === true) {
                return true;
            }
        }

        return false;
    }
}
