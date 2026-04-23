<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Contracts;

use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\DTOs\PreparedInvoiceResult;
use Maaz\LaravelZatca\DTOs\SubmissionResult;
use Maaz\LaravelZatca\DTOs\TenantConfig;

interface SubmissionPipeline
{
    public function prepare(InvoiceData $invoice, TenantConfig $tenantConfig): PreparedInvoiceResult;

    public function submit(InvoiceData $invoice, TenantConfig $tenantConfig, string $mode): SubmissionResult;
}
