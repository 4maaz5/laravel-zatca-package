<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Repositories;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Arr;
use Maaz\LaravelZatca\Contracts\TenantConfigRepository;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\DTOs\TenantContext;

class ConfigTenantConfigRepository implements TenantConfigRepository
{
    public function __construct(
        protected ConfigRepository $config
    ) {
    }

    public function forTenant(?TenantContext $tenant = null): TenantConfig
    {
        $defaultConfig = (array) $this->config->get('zatca.default_tenant', []);
        $tenants = (array) $this->config->get('zatca.tenants', []);
        $tenantConfig = $tenant instanceof TenantContext
            ? (array) Arr::get($tenants, $tenant->id, [])
            : [];

        return TenantConfig::fromArray(
            array_replace_recursive($defaultConfig, $tenantConfig),
            $tenant
        );
    }
}
