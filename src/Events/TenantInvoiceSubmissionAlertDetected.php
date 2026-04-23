<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantInvoice;

class TenantInvoiceSubmissionAlertDetected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ZatcaTenant $tenant,
        public ZatcaTenantInvoice $invoice
    ) {
    }
}
