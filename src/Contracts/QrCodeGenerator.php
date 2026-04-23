<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Contracts;

use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\DTOs\TenantConfig;

interface QrCodeGenerator
{
    public function generate(InvoiceData $invoice, TenantConfig $tenantConfig): string;
}
