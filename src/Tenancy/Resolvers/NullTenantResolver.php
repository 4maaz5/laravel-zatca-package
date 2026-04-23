<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Resolvers;

use Maaz\LaravelZatca\Contracts\TenantResolver;
use Maaz\LaravelZatca\DTOs\TenantContext;

class NullTenantResolver implements TenantResolver
{
    public function resolve(): ?TenantContext
    {
        return null;
    }
}
