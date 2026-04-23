<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZatcaTenantCredential extends Model
{
    public const ENCRYPTED_ATTRIBUTES = [
        'csr_base64',
        'csr_pem',
        'private_key',
        'private_key_secret',
        'compliance_binary_security_token',
        'compliance_secret',
        'production_binary_security_token',
        'production_secret',
    ];

    protected $table = 'zatca_tenant_credentials';

    protected $guarded = [];

    protected $hidden = self::ENCRYPTED_ATTRIBUTES;

    protected $casts = [
        'csr_base64' => 'encrypted',
        'csr_pem' => 'encrypted',
        'private_key' => 'encrypted',
        'private_key_secret' => 'encrypted',
        'compliance_binary_security_token' => 'encrypted',
        'compliance_secret' => 'encrypted',
        'production_binary_security_token' => 'encrypted',
        'production_secret' => 'encrypted',
        'metadata' => 'array',
        'compliance_issued_at' => 'datetime',
        'production_issued_at' => 'datetime',
        'last_validated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(ZatcaTenant::class, 'tenant_id');
    }

    /**
     * @return list<string>
     */
    public static function encryptedAttributes(): array
    {
        return self::ENCRYPTED_ATTRIBUTES;
    }
}
