<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Stores;

use Maaz\LaravelZatca\Contracts\TenantInvoiceStateStore;
use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\DTOs\TenantConfig;

class NullTenantInvoiceStateStore implements TenantInvoiceStateStore
{
    public function persistSuccessfulSubmission(
        InvoiceData $invoice,
        TenantConfig $tenantConfig,
        string $invoiceHash,
        ?string $icv = null
    ): void {
    }
}
