<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Http\Request;
use Maaz\LaravelZatca\Tenancy\Resolvers\RequestTenantResolver;
use Maaz\LaravelZatca\Tests\TestCase;

class RequestTenantResolverTest extends TestCase
{
    public function test_it_resolves_tenant_from_header(): void
    {
        $request = Request::create('/invoices', 'GET', [], [], [], [
            'HTTP_X_TENANT_KEY' => 'tenant-header',
        ]);

        $resolver = new RequestTenantResolver($request);
        $tenant = $resolver->resolve();

        $this->assertNotNull($tenant);
        $this->assertSame('tenant-header', $tenant->id);
        $this->assertSame('header', $tenant->meta['source']);
    }

    public function test_it_resolves_tenant_from_query_when_header_missing(): void
    {
        $request = Request::create('/invoices?tenant=tenant-query', 'GET');

        $resolver = new RequestTenantResolver($request);
        $tenant = $resolver->resolve();

        $this->assertNotNull($tenant);
        $this->assertSame('tenant-query', $tenant->id);
        $this->assertSame('query', $tenant->meta['source']);
    }
}
