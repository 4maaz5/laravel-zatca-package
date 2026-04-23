<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Stores;

use Illuminate\Support\Facades\Schema;
use Maaz\LaravelZatca\Contracts\TenantInvoiceStateStore;
use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantInvoiceState;

class DatabaseTenantInvoiceStateStore implements TenantInvoiceStateStore
{
    public function persistSuccessfulSubmission(
        InvoiceData $invoice,
        TenantConfig $tenantConfig,
        string $invoiceHash,
        ?string $icv = null
    ): void {
        if (! class_exists(ZatcaTenantInvoiceState::class) || ! Schema::hasTable('zatca_tenant_invoice_states')) {
            return;
        }

        $tenant = ZatcaTenant::query()
            ->whereKey($tenantConfig->tenantId)
            ->orWhere('key', $tenantConfig->tenantId)
            ->first();

        if (! $tenant instanceof ZatcaTenant) {
            return;
        }

        $state = ZatcaTenantInvoiceState::query()->firstOrCreate([
            'tenant_id' => $tenant->getKey(),
            'environment' => $tenantConfig->environment,
        ], [
            'last_icv' => 0,
        ]);

        $resolvedIcv = $this->resolveIcv($invoice, $tenantConfig, $state, $icv);

        $state->fill([
            'last_icv' => $resolvedIcv,
            'previous_invoice_hash' => $invoiceHash,
            'last_invoice_uuid' => $invoice->uuid,
            'last_invoice_hash' => $invoiceHash,
            'last_submitted_at' => now(),
        ])->save();
    }

    private function resolveIcv(
        InvoiceData $invoice,
        TenantConfig $tenantConfig,
        ZatcaTenantInvoiceState $state,
        ?string $icv
    ): int {
        $candidate = $icv
            ?? $invoice->meta['icv']
            ?? $invoice->meta['invoice_counter_value']
            ?? $tenantConfig->meta['icv']
            ?? null;

        if (is_string($candidate) && preg_match('/^\d+$/', $candidate) === 1) {
            return (int) $candidate;
        }

        if (is_int($candidate)) {
            return $candidate;
        }

        return ((int) $state->last_icv) + 1;
    }
}
