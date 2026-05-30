<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTenantInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'environment' => ['nullable', 'string', 'in:sandbox,simulation,production'],
            'mode' => ['required', 'string', 'in:reporting,clearance'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'issued_at' => ['nullable', 'date'],
            'type' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'meta' => ['nullable', 'array'],
            'buyer' => ['nullable', 'array'],
            'buyer.name' => ['nullable', 'string', 'max:255'],
            'buyer.vat_number' => ['nullable', 'string', 'max:20'],
            'buyer.street' => ['nullable', 'string', 'max:255'],
            'buyer.city' => ['nullable', 'string', 'max:255'],
            'buyer.postal_code' => ['nullable', 'string', 'max:50'],
            'buyer.country_code' => ['nullable', 'string', 'size:2'],
            'buyer.building_number' => ['nullable', 'string', 'max:50'],
            'buyer.additional_number' => ['nullable', 'string', 'max:50'],
            'buyer.district' => ['nullable', 'string', 'max:255'],
            'buyer.crn' => ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.tax_percent' => ['required', 'numeric', 'gte:0'],
            'items.*.discount' => ['nullable', 'numeric', 'gte:0'],
            'items.*.unit_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}
