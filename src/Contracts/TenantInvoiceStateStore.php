<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Contracts;

use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\DTOs\TenantConfig;

interface TenantInvoiceStateStore
{
    public function persistSuccessfulSubmission(
        InvoiceData $invoice,
        TenantConfig $tenantConfig,
        string $invoiceHash,
        ?string $icv = null
    ): void;
}
