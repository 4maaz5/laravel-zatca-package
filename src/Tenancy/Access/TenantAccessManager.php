<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Access;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Collection;
use Maaz\LaravelZatca\Contracts\TenantAwareUser;
use Maaz\LaravelZatca\Contracts\TenantResolver;
use Maaz\LaravelZatca\DTOs\TenantContext;

class TenantAccessManager
{
    public function __construct(
        protected TenantResolver $resolver,
        protected AuthFactory $auth
    ) {
    }

    public function context(): ?TenantContext
    {
        return $this->resolver->resolve();
    }

    public function preferredTenantKey(): ?string
    {
        $context = $this->context();

        if ($context === null) {
            return null;
        }

        return $context->key ?: $context->id;
    }

    public function canManageTenants(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return (bool) config('zatca.tenant.auth.guests_are_admin', true);
        }

        if ($user instanceof TenantAwareUser) {
            return $user->zatcaCanManageTenants();
        }

        $ability = config('zatca.tenant.auth.admin_ability');

        if (is_string($ability) && $ability !== '' && method_exists($user, 'can') && $user->can($ability)) {
            return true;
        }

        foreach ((array) config('zatca.tenant.auth.admin_method_candidates', ['isSuperAdmin', 'isAdmin']) as $method) {
            if (is_string($method) && $method !== '' && method_exists($user, $method) && $user->{$method}() === true) {
                return true;
            }
        }

        foreach ((array) config('zatca.tenant.auth.admin_property_candidates', ['is_super_admin', 'is_admin']) as $attribute) {
            if ((bool) data_get($user, (string) $attribute, false) === true) {
                return true;
            }
        }

        return false;
    }

    public function authorizeTenantAccess(string $tenant): void
    {
        if ($this->canManageTenants()) {
            return;
        }

        $context = $this->context();

        if ($context === null) {
            throw new AuthorizationException('No tenant context is available for the authenticated user.');
        }

        $allowed = array_unique(array_filter([$context->id, $context->key]));

        if (! in_array($tenant, $allowed, true)) {
            throw new AuthorizationException('The authenticated user cannot access this tenant.');
        }
    }

    public function scopeVisibleTenants(Collection $tenants): Collection
    {
        if ($this->canManageTenants()) {
            return $tenants;
        }

        $context = $this->context();

        if ($context === null) {
            return $tenants->take(0);
        }

        $allowed = array_unique(array_filter([$context->id, $context->key]));

        return $tenants
            ->filter(fn ($tenant): bool => in_array((string) $tenant->getKey(), $allowed, true) || in_array((string) $tenant->key, $allowed, true))
            ->values();
    }

    private function user(): mixed
    {
        $guard = config('zatca.tenant.auth.guard');

        if (is_string($guard) && $guard !== '') {
            return $this->auth->guard($guard)->user();
        }

        return $this->auth->user();
    }
}
