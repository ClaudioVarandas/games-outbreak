<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\News;

use App\Enums\NewsArticleStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateNewsArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', new Enum(NewsArticleStatusEnum::class)],
            'featured_image_url' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'localizations' => ['required', 'array', 'min:1'],
            'localizations.*.locale' => ['required', 'string'],
            'localizations.*.title' => ['required', 'string', 'max:255'],
            'localizations.*.summary_short' => ['nullable', 'string', 'max:160'],
            'localizations.*.summary_medium' => ['nullable', 'string', 'max:400'],
            'localizations.*.slug' => ['nullable', 'string', 'max:255'],
            'localizations.*.body' => ['nullable', 'array'],
            'localizations.*.seo_title' => ['nullable', 'string', 'max:70'],
            'localizations.*.seo_description' => ['nullable', 'string', 'max:160'],
        ];
    }
}
