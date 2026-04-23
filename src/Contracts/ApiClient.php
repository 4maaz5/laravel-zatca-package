<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Contracts;

use Maaz\LaravelZatca\DTOs\TenantConfig;

interface ApiClient
{
    public function submit(array $payload, TenantConfig $tenantConfig, string $mode): array;
}
