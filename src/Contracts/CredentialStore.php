<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Contracts;

use Maaz\LaravelZatca\DTOs\TenantContext;

interface CredentialStore
{
    public function get(string $key, ?TenantContext $tenant = null, mixed $default = null): mixed;
}
