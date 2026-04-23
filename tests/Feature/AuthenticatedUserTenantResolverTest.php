<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Maaz\LaravelZatca\Contracts\TenantResolver;
use Maaz\LaravelZatca\Tests\TestCase;

class AuthenticatedUserTenantResolverTest extends TestCase
{
    public function test_it_resolves_the_tenant_from_the_authenticated_user(): void
    {
        $user = new class () extends Authenticatable {
            public string $tenant_key = 'bi-tech';
        };

        $this->actingAs($user);

        $tenant = $this->app->make(TenantResolver::class)->resolve();

        $this->assertNotNull($tenant);
        $this->assertSame('bi-tech', $tenant->id);
        $this->assertSame('bi-tech', $tenant->key);
        $this->assertSame('auth_attribute', $tenant->meta['source']);
    }
}
