<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ZatcaTenant extends Model
{
    protected $table = 'zatca_tenants';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'bool',
    ];

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereTenantIdentifier(Builder $query, int|string $identifier): Builder
    {
        $identifier = trim((string) $identifier);

        return $query->where(function (Builder $query) use ($identifier): void {
            if (preg_match('/^\d+$/', $identifier) === 1) {
                $query->whereKey($identifier)
                    ->orWhere('key', $identifier);

                return;
            }

            $query->where('key', $identifier);
        });
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ZatcaTenantCredential::class, 'tenant_id');
    }

    public function invoiceStates(): HasMany
    {
        return $this->hasMany(ZatcaTenantInvoiceState::class, 'tenant_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ZatcaTenantInvoice::class, 'tenant_id');
    }

    public function notificationHooks(): HasMany
    {
        return $this->hasMany(ZatcaTenantNotificationHook::class, 'tenant_id');
    }

    public function sandboxCredentials(): HasOne
    {
        return $this->hasOne(ZatcaTenantCredential::class, 'tenant_id')->where('environment', 'sandbox');
    }

    public function productionCredentials(): HasOne
    {
        return $this->hasOne(ZatcaTenantCredential::class, 'tenant_id')->where('environment', 'production');
    }
}
