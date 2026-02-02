@props(['list'])

@php
    $showGenreSelection = $list && $list->isYearly();
    $genres = $showGenreSelection
        ? \App\Models\Genre::visible()->where('is_pending_review', false)->ordered()->get(['id', 'name', 'slug'])
        : collect();
@endphp

<div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
    <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">Add Games</h2>

    @if($list)
        <div
            data-vue-component="add-game-to-list"
            data-list-id="{{ $list->id }}"
            data-platforms="{{ json_encode(\App\Enums\PlatformEnum::getActivePlatforms()->map(fn($enum) => ['id' => $enum->value, 'label' => $enum->label(), 'color' => $enum->color()])->values()) }}"
            data-route-prefix="{{ route('admin.system-lists.games.add', [$list->list_type->toSlug(), $list->slug]) }}"
            data-show-genre-selection="{{ $showGenreSelection ? 'true' : 'false' }}"
            data-available-genres="{{ $genres->toJson() }}"
        ></div>
    @else
        <p class="text-gray-600 dark:text-gray-400">List not found.</p>
    @endif
</div>
