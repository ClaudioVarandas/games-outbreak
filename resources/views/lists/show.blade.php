@extends('layouts.app')

@section('title', $gameList->name)

@section('content')
    @php
        $currentUser = auth()->user();
        $canEdit = false;
        if ($currentUser) {
            $canEdit = $gameList->canBeEditedBy($currentUser);
            // Debug: Uncomment to see values
            // \Log::info('Edit check', ['user_id' => $currentUser->id, 'is_admin' => $currentUser->is_admin, 'isAdmin()' => $currentUser->isAdmin(), 'list_is_system' => $gameList->is_system, 'canEdit' => $canEdit]);
        }
    @endphp
    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100 mb-2">
                        {{ $gameList->name }}
                    </h1>
                    @if($gameList->is_system)
                        <span class="inline-block px-3 py-1 bg-orange-600 text-white rounded text-sm font-bold">System List</span>
                    @endif
                    @if($gameList->is_public)
                        <span class="inline-block px-3 py-1 bg-green-600 text-white rounded text-sm ml-2">Public</span>
                    @else
                        <span class="inline-block px-3 py-1 bg-gray-600 text-white rounded text-sm ml-2">Private</span>
                    @endif
                </div>
                @auth
                    @if(!isset($readOnly) || !$readOnly)
                        @php
                            // Re-check for admin users viewing system lists
                            $authUser = auth()->user();
                            $canEditList = false;
                            if ($authUser) {
                                // Direct check: admins can always edit
                                if ($authUser->isAdmin()) {
                                    $canEditList = true;
                                } else {
                                    // Non-admins can only edit their own non-system lists
                                    if (!$gameList->is_system && $gameList->user_id === $authUser->id) {
                                        $canEditList = true;
                                    }
                                }
                            }
                        @endphp
                        @if($canEditList)
                            <div class="flex gap-2">
                                <a href="{{ route('lists.edit', [$gameList->list_type->toSlug(), $gameList->slug]) }}" class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-lg transition">
                                    Edit
                                </a>
                                @if($gameList->canBeDeleted())
                                    <form action="{{ route('lists.destroy', [$gameList->list_type->toSlug(), $gameList->slug]) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this list?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition">
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    @endif
                @endauth
            </div>
            @if($gameList->description)
                <p class="text-lg text-gray-600 dark:text-gray-400 mb-4">
                    {{ $gameList->description }}
                </p>
            @endif
            @if($gameList->user)
                <p class="text-sm text-gray-500 dark:text-gray-500">
                    Created by {{ $gameList->user->name }}
                </p>
            @endif
            @if($gameList->end_at)
                <p class="text-sm text-gray-500 dark:text-gray-500">
                    Expires {{ $gameList->end_at->format('d/m/Y') }}
                </p>
            @endif
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif

        <div class="mb-6">
            <p class="text-lg text-gray-700 dark:text-gray-300 mb-4">
                {{ $gameList->games->count() }} {{ Str::plural('game', $gameList->games->count()) }} in this list
            </p>
            @auth
                @if($canEdit && (!isset($readOnly) || !$readOnly))
                    <!-- Add Games Section -->
                    <div class="mb-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                        <h2 class="text-2xl font-bold mb-4 text-gray-800 dark:text-gray-100">Add Games to List</h2>
                        <div
                            data-vue-component="add-game-to-list"
                            data-list-id="{{ $gameList->id }}"
                            data-platforms="{{ json_encode(\App\Enums\PlatformEnum::getActivePlatforms()->map(fn($enum) => ['id' => $enum->value, 'label' => $enum->label(), 'color' => $enum->color()])->values()) }}"
                        ></div>
                    </div>
                @endif
            @endauth
        </div>

        @if($gameList->games->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach($gameList->games as $game)
                    @php
                        // Use pivot release_date if available, otherwise fall back to game's first_release_date
                        // Convert pivot release_date to Carbon if it's a string (from database)
                        $pivotReleaseDate = $game->pivot->release_date ?? null;
                        if ($pivotReleaseDate && is_string($pivotReleaseDate)) {
                            $pivotReleaseDate = \Carbon\Carbon::parse($pivotReleaseDate);
                        }
                        $displayDate = $pivotReleaseDate ?? $game->first_release_date;
                    @endphp
                    <x-game-card
                        :game="$game"
                        :displayReleaseDate="$displayDate"
                        variant="glassmorphism"
                        layout="overlay"
                        aspectRatio="3/4"
                        :showRemoveButton="($canEdit && (!isset($readOnly) || !$readOnly))"
                        :removeRoute="(!isset($readOnly) || !$readOnly) ? route('lists.games.remove', [$gameList->list_type->toSlug(), $gameList->slug, $game]) : null"
                        :platformEnums="$platformEnums" />
                @endforeach
            </div>
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <p class="text-xl text-gray-600 dark:text-gray-400 mb-4">
                    This list is empty.
                </p>
                @auth
                    @if($canEdit && (!isset($readOnly) || !$readOnly))
                        <p class="text-gray-500 dark:text-gray-500">
                            Browse games and add them to this list.
                        </p>
                    @endif
                @endauth
            </div>
        @endif
    </div>

@endsection

