<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGenreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $genreId = $this->route('genre')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('genres', 'name')->ignore($genreId)],
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:100', Rule::unique('genres', 'slug')->ignore($genreId)],
            'is_visible' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'A genre name is required.',
            'name.unique' => 'A genre with this name already exists.',
            'slug.unique' => 'A genre with this slug already exists.',
        ];
    }
}
