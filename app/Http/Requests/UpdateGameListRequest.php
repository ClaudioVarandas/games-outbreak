<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGameListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $gameList = $this->route('gameList');
        $user = $this->user();
        
        if (!$gameList || !$user) {
            return false;
        }
        
        return $gameList->canBeEditedBy($user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $gameList = $this->route('gameList');
        $gameListId = $gameList ? $gameList->id : null;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_public' => ['boolean'],
        ];

        // Only admins can modify system list properties
        if ($this->user() && $this->user()->isAdmin()) {
            $rules['is_system'] = ['boolean'];
            $rules['slug'] = ['nullable', 'string', 'alpha_dash', 'unique:game_lists,slug,' . $gameListId];
            $rules['start_at'] = ['nullable', 'date'];
            $rules['end_at'] = ['nullable', 'date'];
            $rules['is_active'] = ['boolean'];
            
            // If both dates are provided, end_at must be after start_at
            if ($this->filled('start_at') && $this->filled('end_at')) {
                $rules['end_at'][] = 'after:start_at';
            }
        }

        return $rules;
    }
}
