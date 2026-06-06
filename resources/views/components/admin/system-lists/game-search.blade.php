@props(['list'])

@php
    $showGenreSelection = $list && $list->isYearly();
    $genres = $showGenreSelection
        ? \App\Models\Genre::visible()->where('is_pending_review', false)->ordered()->get(['id', 'name', 'slug'])
        : collect();
    $hasSync = $list && $list->isEvents();
@endphp

<div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
    <div @class(['grid grid-cols-1 gap-4', 'md:grid-cols-10 md:items-end' => $hasSync])>
        {{-- Add games --}}
        <div @class(['md:col-span-7' => $hasSync])>
            <h2 class="mb-2 text-base font-semibold text-gray-900 dark:text-white">Add Games</h2>

            @if($list)
                <div
                    data-vue-component="add-game-to-list"
                    data-list-id="{{ $list->id }}"
                    data-platforms="{{ json_encode(\App\Enums\PlatformEnum::displayList()) }}"
                    data-route-prefix="{{ route('admin.system-lists.games.add', [$list->list_type->toSlug(), $list->slug]) }}"
                    data-show-genre-selection="{{ $showGenreSelection ? 'true' : 'false' }}"
                    data-available-genres="{{ $genres->toJson() }}"
                ></div>
            @else
                <p class="text-gray-600 dark:text-gray-400">List not found.</p>
            @endif
        </div>

        {{-- Sync games from IGDB --}}
        @if($hasSync)
            <div class="md:col-span-3">
                <h2 class="mb-2 text-base font-semibold text-gray-900 dark:text-white">Sync games from IGDB</h2>

                <button type="button"
                        @disabled(! $list->igdb_event_id)
                        @if($list->igdb_event_id)
                            data-sync-url="{{ route('admin.system-lists.sync-igdb', [$list->list_type->toSlug(), $list->slug]) }}"
                            title="Pull new games from the linked IGDB event"
                        @else
                            title="Set and save an IGDB Event ID first to enable"
                        @endif
                        onclick="syncEventFromIgdb(this)"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Sync from IGDB
                </button>
            </div>
        @endif
    </div>
</div>
