<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantNotificationHookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'name' => $this->name,
            'channel' => $this->channel,
            'target_url' => $this->target_url,
            'events' => $this->events ?? [],
            'is_active' => (bool) $this->is_active,
            'last_notified_at' => optional($this->last_notified_at)?->toIso8601String(),
            'last_error' => $this->last_error,
            'metadata' => $this->metadata ?? [],
        ];
    }
}
