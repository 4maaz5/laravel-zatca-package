<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:100'],
            'legal_name' => ['required', 'string', 'max:255'],
            'legal_name_ar' => ['nullable', 'string', 'max:255'],
            'seller_name' => ['nullable', 'string', 'max:255'],
            'seller_name_ar' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['required', 'string', 'size:15'],
            'crn' => ['nullable', 'string', 'max:50'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'branch_name_ar' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'building_number' => ['nullable', 'string', 'max:50'],
            'additional_number' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'in:en,ar'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'default_environment' => ['nullable', 'string', 'in:sandbox,production'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
