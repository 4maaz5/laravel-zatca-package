<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Resolvers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Maaz\LaravelZatca\Contracts\TenantAwareUser;
use Maaz\LaravelZatca\Contracts\TenantResolver;
use Maaz\LaravelZatca\DTOs\TenantContext;

class AuthenticatedUserTenantResolver implements TenantResolver
{
    public function __construct(
        protected AuthFactory $auth
    ) {
    }

    public function resolve(): ?TenantContext
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        if ($user instanceof TenantAwareUser) {
            $key = $user->zatcaTenantKey();

            if (is_string($key) && trim($key) !== '') {
                return new TenantContext(
                    id: trim($key),
                    type: 'auth',
                    key: trim($key),
                    meta: ['source' => 'tenant_aware_user']
                );
            }
        }

        foreach ((array) config('zatca.tenant.auth.user_method_candidates', ['zatcaTenantKey', 'tenantKey', 'tenantId']) as $method) {
            if (is_string($method) && $method !== '' && method_exists($user, $method)) {
                $value = $user->{$method}();

                if (is_scalar($value) && trim((string) $value) !== '') {
                    return new TenantContext(
                        id: trim((string) $value),
                        type: 'auth',
                        key: trim((string) $value),
                        meta: ['source' => 'auth_method', 'method' => $method]
                    );
                }
            }
        }

        foreach ((array) config('zatca.tenant.auth.user_key_candidates', ['zatca_tenant_key', 'tenant_key', 'tenant_id']) as $attribute) {
            $value = data_get($user, (string) $attribute);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return new TenantContext(
                    id: trim((string) $value),
                    type: 'auth',
                    key: trim((string) $value),
                    meta: ['source' => 'auth_attribute', 'attribute' => (string) $attribute]
                );
            }
        }

        return null;
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
