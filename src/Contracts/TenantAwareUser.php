<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Contracts;

interface TenantAwareUser
{
    public function zatcaTenantKey(): ?string;

    public function zatcaCanManageTenants(): bool;
}
