<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTenantCsrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'environment' => ['nullable', 'string', 'in:sandbox,simulation,production'],
            'common_name' => ['required', 'string', 'max:255'],
            'serial_number' => ['required', 'string', 'max:255'],
            'organization_identifier' => ['nullable', 'string', 'max:50'],
            'organization_unit_name' => ['nullable', 'string', 'max:255'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'country_name' => ['nullable', 'string', 'size:2'],
            'invoice_type' => ['nullable', 'string', 'max:20'],
            'location_address' => ['required', 'string', 'max:100'],
            'industry_business_category' => ['required', 'string', 'max:255'],
            'simulation' => ['nullable', 'boolean'],
            'non_production' => ['nullable', 'boolean'],
        ];
    }
}
