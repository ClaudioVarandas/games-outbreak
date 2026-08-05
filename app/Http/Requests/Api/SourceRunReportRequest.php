<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SourceRunReportRequest extends FormRequest
{
    /**
     * Authorization is handled by the EnsureImportToken middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'window' => ['nullable', 'string', 'max:50'],
            'sources' => ['required', 'array', 'min:1'],
            'sources.*.id' => ['required', 'integer', 'min:1'],
            'sources.*.items_found' => ['required', 'integer', 'min:0'],
            'sources.*.fetch_ok' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sources.*.id.required' => 'Every run-report row needs a source id.',
        ];
    }
}
