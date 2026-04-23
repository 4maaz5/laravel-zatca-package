<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Resolvers;

use Illuminate\Http\Request;
use Maaz\LaravelZatca\Contracts\TenantResolver;
use Maaz\LaravelZatca\DTOs\TenantContext;

class RequestTenantResolver implements TenantResolver
{
    public function __construct(
        protected Request $request
    ) {
    }

    public function resolve(): ?TenantContext
    {
        $headerName = (string) config('zatca.tenant.request.header', 'X-Tenant-Key');
        $queryKey = (string) config('zatca.tenant.request.query_parameter', 'tenant');
        $routeKey = (string) config('zatca.tenant.request.route_parameter', 'tenant');

        $value = $this->request->header($headerName)
            ?? $this->request->route($routeKey)
            ?? $this->request->query($queryKey);

        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        return new TenantContext(
            id: trim((string) $value),
            type: 'request',
            key: trim((string) $value),
            meta: [
                'source' => $this->request->header($headerName) !== null
                    ? 'header'
                    : ($this->request->route($routeKey) !== null ? 'route' : 'query'),
            ]
        );
    }
}
