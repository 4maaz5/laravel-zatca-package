<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantInvoice;

/** @mixin ZatcaTenantInvoice */
class TenantInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'environment' => $this->environment,
            'invoice_number' => $this->invoice_number,
            'uuid' => $this->uuid,
            'mode' => $this->mode,
            'invoice_type' => $this->invoice_type,
            'status' => $this->status,
            'reporting_status' => $this->reporting_status,
            'clearance_status' => $this->clearance_status,
            'invoice_hash' => $this->invoice_hash,
            'qr_code' => $this->qr_code,
            'seller' => $this->seller ?? [],
            'buyer' => $this->buyer ?? [],
            'items' => $this->items ?? [],
            'issued_at' => optional($this->issued_at)?->toIso8601String(),
            'submitted_at' => optional($this->submitted_at)?->toIso8601String(),
            'last_error' => $this->last_error,
            'api_response' => $this->api_response ?? [],
            'metadata' => $this->metadata ?? [],
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
