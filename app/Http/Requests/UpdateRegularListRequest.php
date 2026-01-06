<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRegularListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by user.ownership middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $slug = $this->route('slug');
        $user = $this->route('user');

        $gameList = null;
        if ($slug && $user) {
            $gameList = \App\Models\GameList::where('slug', $slug)
                ->where('list_type', \App\Enums\ListTypeEnum::REGULAR->value)
                ->where('user_id', $user->id)
                ->first();
        }

        $gameListId = $gameList ? $gameList->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_public' => ['boolean'],
            'slug' => [
                'nullable',
                'string',
                'alpha_dash',
                // Slug must be unique per list_type
                \Illuminate\Validation\Rule::unique('game_lists', 'slug')
                    ->where('list_type', \App\Enums\ListTypeEnum::REGULAR->value)
                    ->ignore($gameListId)
            ],
        ];
    }
}
