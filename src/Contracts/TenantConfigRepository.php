<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Contracts;

use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\DTOs\TenantContext;

interface TenantConfigRepository
{
    public function forTenant(?TenantContext $tenant = null): TenantConfig;
}
