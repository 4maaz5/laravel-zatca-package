<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Contracts;

use Maaz\LaravelZatca\DTOs\TenantContext;

interface TenantResolver
{
    public function resolve(): ?TenantContext;
}
