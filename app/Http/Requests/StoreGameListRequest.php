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
        ];

        // Prevent list_type from being set via form
        // Backlog and wishlist lists are auto-created only
        if ($this->has('list_type')) {
            $rules['list_type'] = ['prohibited'];
        }

        // Only admins can create system lists
        if ($this->user() && $this->user()->isAdmin()) {
            $rules['is_system'] = ['boolean'];
            // Slug is nullable - will be auto-generated from name if empty and is_system is true
            // If provided, it must be unique and valid format
            $rules['slug'] = ['nullable', 'string', 'alpha_dash', 'unique:game_lists,slug'];
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
