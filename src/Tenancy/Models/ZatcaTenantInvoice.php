<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZatcaTenantInvoice extends Model
{
    protected $table = 'zatca_tenant_invoices';

    protected $guarded = [];

    protected $casts = [
        'seller' => 'array',
        'buyer' => 'array',
        'items' => 'array',
        'invoice_payload' => 'array',
        'api_response' => 'array',
        'metadata' => 'array',
        'issued_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(ZatcaTenant::class, 'tenant_id');
    }
}
