<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGameListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Prevent creating backlog/wishlist via form (only auto-created)
        if ($this->has('list_type') && in_array($this->input('list_type'), ['backlog', 'wishlist'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Determine the list_type for validation
        $listType = $this->input('list_type', 'regular');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_public' => ['boolean'],
            'list_type' => ['nullable', 'string', 'in:regular,monthly,indie-games,seasoned'],
            'slug' => [
                'nullable',
                'string',
                'alpha_dash',
                // Slug must be unique per list_type
                \Illuminate\Validation\Rule::unique('game_lists', 'slug')->where('list_type', $listType)
            ],
        ];

        // Only admins can create system lists
        if ($this->user() && $this->user()->isAdmin()) {
            $rules['is_system'] = ['boolean'];
            $rules['is_active'] = ['boolean'];
            $rules['start_at'] = ['nullable', 'date'];
            $rules['end_at'] = ['nullable', 'date'];

            // If both dates are provided, end_at must be after start_at
            if ($this->filled('start_at') && $this->filled('end_at')) {
                $rules['end_at'][] = 'after:start_at';
            }
        }

        return $rules;
    }
}
