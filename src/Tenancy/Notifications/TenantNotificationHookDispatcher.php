<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Notifications;

use Illuminate\Http\Client\Factory as HttpFactory;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantNotificationHook;

class TenantNotificationHookDispatcher
{
    public function __construct(
        protected HttpFactory $http
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(ZatcaTenantNotificationHook $hook, array $payload): void
    {
        if (! $hook->is_active || $hook->channel !== 'webhook') {
            return;
        }

        try {
            $response = $this->http
                ->acceptJson()
                ->withHeaders(array_filter([
                    'X-Zatca-Hook-Secret' => $hook->secret,
                ]))
                ->post($hook->target_url, $payload);

            $hook->forceFill([
                'last_notified_at' => now(),
                'last_error' => $response->successful() ? null : $response->body(),
            ])->saveQuietly();
        } catch (\Throwable $exception) {
            $hook->forceFill([
                'last_error' => $exception->getMessage(),
            ])->saveQuietly();
        }
    }
}
