<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueComplianceCsidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'environment' => ['nullable', 'string', 'in:sandbox,simulation,production'],
            'otp' => ['required', 'string', 'max:50'],
            'csr' => ['nullable', 'string'],
        ];
    }
}
