<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Contracts;

use Maaz\LaravelZatca\DTOs\TenantConfig;

interface InvoiceSigner
{
    public function sign(string $xml, TenantConfig $tenantConfig): string;
}
