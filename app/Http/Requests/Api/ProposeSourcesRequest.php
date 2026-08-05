<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\DiscoverySourceKindEnum;
use App\Enums\DiscoverySourcePurposeEnum;
use App\Enums\ImportConfidenceEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProposeSourcesRequest extends FormRequest
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
            'sources' => ['required', 'array', 'min:1', 'max:20'],
            'sources.*.url' => ['nullable', 'string', 'url', 'max:500'],
            'sources.*.kind' => ['nullable', Rule::enum(DiscoverySourceKindEnum::class)],
            'sources.*.purpose' => ['nullable', Rule::enum(DiscoverySourcePurposeEnum::class)],
            'sources.*.name' => ['nullable', 'string', 'max:255'],
            'sources.*.confidence' => ['nullable', Rule::enum(ImportConfidenceEnum::class)],
            'sources.*.evidence' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'sources.max' => 'Send at most 20 sources per request.',
            'sources.*.url.url' => 'Every source url must be a valid absolute URL.',
        ];
    }
}
