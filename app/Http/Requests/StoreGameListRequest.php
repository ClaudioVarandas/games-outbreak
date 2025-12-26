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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_public' => ['boolean'],
            'slug' => ['nullable', 'string', 'alpha_dash', 'unique:game_lists,slug'],
        ];

        // Only admins can create system lists
        if ($this->user() && $this->user()->isAdmin()) {
            $rules['is_system'] = ['boolean'];
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
