<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'legal_name' => ['sometimes', 'string', 'max:255'],
            'legal_name_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seller_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seller_name_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'vat_number' => ['sometimes', 'string', 'size:15'],
            'crn' => ['sometimes', 'nullable', 'string', 'max:50'],
            'branch_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'branch_name_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country_code' => ['sometimes', 'string', 'size:2'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'district' => ['sometimes', 'nullable', 'string', 'max:255'],
            'street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'building_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'additional_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'locale' => ['sometimes', 'string', 'in:en,ar'],
            'timezone' => ['sometimes', 'string', 'max:100'],
            'default_environment' => ['sometimes', 'string', 'in:sandbox,simulation,production'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
