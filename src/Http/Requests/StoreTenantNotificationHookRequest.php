<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantNotificationHookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'in:webhook'],
            'target_url' => ['required', 'url', 'max:2048'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string', 'in:health_alert,submission_failed'],
            'is_active' => ['nullable', 'boolean'],
            'secret' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
