<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Listeners;

use Maaz\LaravelZatca\Events\TenantInvoiceSubmissionAlertDetected;
use Maaz\LaravelZatca\Tenancy\Notifications\TenantNotificationHookDispatcher;

class DispatchTenantInvoiceAlertToHooks
{
    public function __construct(
        protected TenantNotificationHookDispatcher $dispatcher
    ) {
    }

    public function handle(TenantInvoiceSubmissionAlertDetected $event): void
    {
        $event->tenant->loadMissing('notificationHooks');

        foreach ($event->tenant->notificationHooks as $hook) {
            $events = $hook->events ?? ['health_alert'];

            if (! in_array('submission_failed', $events, true)) {
                continue;
            }

            $this->dispatcher->dispatch($hook, [
                'event' => 'submission_failed',
                'tenant' => [
                    'id' => (string) $event->tenant->getKey(),
                    'key' => $event->tenant->key,
                    'seller_name' => $event->tenant->seller_name,
                    'vat_number' => $event->tenant->vat_number,
                ],
                'invoice' => [
                    'id' => (string) $event->invoice->getKey(),
                    'invoice_number' => $event->invoice->invoice_number,
                    'uuid' => $event->invoice->uuid,
                    'environment' => $event->invoice->environment,
                    'mode' => $event->invoice->mode,
                    'status' => $event->invoice->status,
                    'reporting_status' => $event->invoice->reporting_status,
                    'clearance_status' => $event->invoice->clearance_status,
                    'last_error' => $event->invoice->last_error,
                ],
                'sent_at' => now()->toIso8601String(),
            ]);
        }
    }
}
