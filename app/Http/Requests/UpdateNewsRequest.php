<?php

namespace App\Http\Requests;

use App\Enums\NewsStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $content = $this->input('content');
        if (is_string($content)) {
            $this->merge([
                'content' => json_decode($content, true) ?? [],
            ]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $newsId = $this->route('news')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:255', Rule::unique('news', 'slug')->ignore($newsId)],
            'image_path' => ['nullable', 'string', 'max:500'],
            'summary' => ['required', 'string', 'max:280'],
            'content' => ['required', 'array'],
            'status' => ['required', Rule::enum(NewsStatusEnum::class)],
            'source_url' => ['nullable', 'url', 'max:500'],
            'source_name' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'A title is required.',
            'summary.required' => 'A summary is required.',
            'summary.max' => 'The summary must not exceed 280 characters.',
            'content.required' => 'Article content is required.',
            'status.required' => 'Please select a status.',
        ];
    }
}
