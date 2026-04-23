<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Resolvers;

use Maaz\LaravelZatca\Contracts\TenantResolver;
use Maaz\LaravelZatca\DTOs\TenantContext;

class CompositeTenantResolver implements TenantResolver
{
    /**
     * @param  array<int, TenantResolver>  $resolvers
     */
    public function __construct(
        protected array $resolvers
    ) {
    }

    public function resolve(): ?TenantContext
    {
        foreach ($this->resolvers as $resolver) {
            $tenant = $resolver->resolve();

            if ($tenant instanceof TenantContext) {
                return $tenant;
            }
        }

        return null;
    }
}
