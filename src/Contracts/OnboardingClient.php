<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Contracts;

use Maaz\LaravelZatca\DTOs\TenantConfig;

interface OnboardingClient
{
    public function requestComplianceCsid(array $payload, TenantConfig $tenantConfig): array;

    public function requestProductionCsid(array $payload, TenantConfig $tenantConfig): array;

    public function complianceCheck(array $payload, TenantConfig $tenantConfig): array;
}
