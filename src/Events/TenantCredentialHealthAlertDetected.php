<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;

class TenantCredentialHealthAlertDetected
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $health
     */
    public function __construct(
        public ZatcaTenant $tenant,
        public ZatcaTenantCredential $credential,
        public array $health
    ) {
    }
}
