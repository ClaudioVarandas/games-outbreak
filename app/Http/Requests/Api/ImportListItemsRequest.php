<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\ImportConfidenceEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportListItemsRequest extends FormRequest
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
            'list_slug' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.igdb_id' => ['required', 'integer', 'min:1'],
            'items.*.release_date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.is_tba' => ['nullable', 'boolean'],
            'items.*.release_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'items.*.platforms' => ['nullable', 'array'],
            'items.*.platforms.*' => ['integer', 'min:1'],
            'items.*.confidence' => ['nullable', Rule::enum(ImportConfidenceEnum::class)],
            'items.*.sources' => ['nullable', 'array'],
            'items.*.sources.*' => ['string', 'max:100'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.max' => 'Send at most 10 items per request; batch larger imports.',
            'items.*.igdb_id.required' => 'Every item needs an igdb_id.',
        ];
    }
}
