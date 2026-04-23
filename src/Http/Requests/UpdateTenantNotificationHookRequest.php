<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantNotificationHookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', 'string', 'in:webhook'],
            'target_url' => ['sometimes', 'url', 'max:2048'],
            'events' => ['sometimes', 'array'],
            'events.*' => ['string', 'in:health_alert,submission_failed'],
            'is_active' => ['sometimes', 'boolean'],
            'secret' => ['nullable', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
