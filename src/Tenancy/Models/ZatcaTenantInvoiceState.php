<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZatcaTenantInvoiceState extends Model
{
    protected $table = 'zatca_tenant_invoice_states';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'last_submitted_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(ZatcaTenant::class, 'tenant_id');
    }
}
