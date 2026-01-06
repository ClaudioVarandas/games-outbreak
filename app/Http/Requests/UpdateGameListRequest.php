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
        // Route now uses type and slug parameters instead of gameList model binding
        $type = $this->route('type');
        $slug = $this->route('slug');
        $user = $this->user();

        if (!$type || !$slug || !$user) {
            return false;
        }

        // Load the game list by type and slug
        $listType = \App\Enums\ListTypeEnum::fromSlug($type);
        if ($listType === null) {
            return false;
        }

        $gameList = \App\Models\GameList::where('slug', $slug)
            ->where('list_type', $listType->value)
            ->first();

        if (!$gameList) {
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
        // Load game list by type and slug parameters
        $type = $this->route('type');
        $slug = $this->route('slug');

        $gameList = null;
        if ($type && $slug) {
            $listTypeEnum = \App\Enums\ListTypeEnum::fromSlug($type);
            if ($listTypeEnum) {
                $gameList = \App\Models\GameList::where('slug', $slug)
                    ->where('list_type', $listTypeEnum->value)
                    ->first();
            }
        }

        $gameListId = $gameList ? $gameList->id : null;
        $listType = $gameList ? $gameList->list_type->value : 'regular';

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_public' => ['boolean'],
            'slug' => [
                'nullable',
                'string',
                'alpha_dash',
                // Slug must be unique per list_type
                \Illuminate\Validation\Rule::unique('game_lists', 'slug')
                    ->where('list_type', $listType)
                    ->ignore($gameListId)
            ],
        ];

        // Allow list_type field but prevent it from being changed
        // This allows the field to be present in the form but ensures it matches the current value
        if ($gameList) {
            $rules['list_type'] = ['nullable', 'string', 'in:' . $gameList->list_type->value];
        }

        // Prevent renaming backlog/wishlist lists
        if ($gameList && $gameList->isSpecialList()) {
            $rules['name'] = ['prohibited'];
        }

        // Only admins can modify system list properties
        if ($this->user() && $this->user()->isAdmin()) {
            $rules['is_system'] = ['boolean'];
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
