<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Contracts;

use Maaz\LaravelZatca\DTOs\GeneratedCsrResult;
use Maaz\LaravelZatca\DTOs\TenantConfig;

interface CsrGenerator
{
    public function generate(array $payload, TenantConfig $tenantConfig): GeneratedCsrResult;
}
