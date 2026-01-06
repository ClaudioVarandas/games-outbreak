@props(['user', 'type', 'list' => null])

@php
    // Get the list ID - either from passed list or fetch it
    if (!$list) {
        if ($type === 'backlog') {
            $list = $user->gameLists()->backlog()->first();
        } elseif ($type === 'wishlist') {
            $list = $user->gameLists()->wishlist()->first();
        } else {
            $list = $user->gameLists()->where('slug', $type)->where('list_type', \App\Enums\ListTypeEnum::REGULAR->value)->first();
        }
    }

    $listId = $list ? $list->id : null;
@endphp

<div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
    <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">Add Games</h2>

    @if($listId)
        <div
            data-vue-component="add-game-to-list"
            data-list-id="{{ $listId }}"
            data-platforms="{{ json_encode(\App\Enums\PlatformEnum::getActivePlatforms()->map(fn($enum) => ['id' => $enum->value, 'label' => $enum->label(), 'color' => $enum->color()])->values()) }}"
            data-route-prefix="{{ route('user.lists.games.add', [$user, $type]) }}"
        ></div>
    @else
        <p class="text-gray-600 dark:text-gray-400">List not found.</p>
    @endif
</div>
