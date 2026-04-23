<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Listeners;

use Maaz\LaravelZatca\Events\TenantCredentialHealthAlertDetected;
use Maaz\LaravelZatca\Tenancy\Notifications\TenantNotificationHookDispatcher;

class DispatchTenantHealthAlertToHooks
{
    public function __construct(
        protected TenantNotificationHookDispatcher $dispatcher
    ) {
    }

    public function handle(TenantCredentialHealthAlertDetected $event): void
    {
        $event->tenant->loadMissing('notificationHooks');

        foreach ($event->tenant->notificationHooks as $hook) {
            $events = $hook->events ?? ['health_alert'];

            if (! in_array('health_alert', $events, true)) {
                continue;
            }

            $this->dispatcher->dispatch($hook, [
                'event' => 'health_alert',
                'tenant' => [
                    'id' => (string) $event->tenant->getKey(),
                    'key' => $event->tenant->key,
                    'seller_name' => $event->tenant->seller_name,
                    'vat_number' => $event->tenant->vat_number,
                ],
                'credential' => [
                    'environment' => $event->credential->environment,
                    'status' => $event->credential->status,
                ],
                'health' => $event->health,
                'sent_at' => now()->toIso8601String(),
            ]);
        }
    }
}
