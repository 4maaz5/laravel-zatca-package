<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZatcaTenantNotificationHook extends Model
{
    protected $table = 'zatca_tenant_notification_hooks';

    protected $guarded = [];

    protected $casts = [
        'events' => 'array',
        'metadata' => 'array',
        'is_active' => 'bool',
        'last_notified_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(ZatcaTenant::class, 'tenant_id');
    }
}
