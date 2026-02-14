@extends('layouts.app')

@section('title', 'Edit ' . $list->name)

@section('content')
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                Edit {{ $list->name }}
            </h1>
        </div>

        {{-- List Settings Header --}}
        <div class="mb-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden" x-data="{ settingsOpen: false }">
            <button @click="settingsOpen = !settingsOpen"
                    class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">List Settings</h2>
                <svg class="w-5 h-5 text-gray-500 dark:text-gray-400 transition-transform duration-200"
                     :class="{ 'rotate-90': settingsOpen }"
                     fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                </svg>
            </button>

            <form x-show="settingsOpen" x-collapse action="{{ route('admin.system-lists.update', [$list->list_type->toSlug(), $list->slug]) }}" method="POST" class="px-6 pb-6">
                @csrf
                @method('PATCH')

                {{-- Basic Settings Row --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            List Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               id="name"
                               value="{{ old('name', $list->name) }}"
                               required
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            URL Slug
                        </label>
                        <input type="text"
                               name="slug"
                               id="slug"
                               value="{{ old('slug', $list->slug) }}"
                               pattern="[a-z0-9-]+"
                               required
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="start_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Start Date
                        </label>
                        <input type="datetime-local"
                               name="start_at"
                               id="start_at"
                               value="{{ old('start_at', $list->start_at?->format('Y-m-d\TH:i')) }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="end_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            End Date
                        </label>
                        <input type="datetime-local"
                               name="end_at"
                               id="end_at"
                               value="{{ old('end_at', $list->end_at?->format('Y-m-d\TH:i')) }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                    </div>
                </div>

                {{-- Description & OG Image Row --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Description
                        </label>
                        <textarea name="description"
                                  id="description"
                                  rows="2"
                                  class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">{{ old('description', $list->description) }}</textarea>
                    </div>

                    <div>
                        <label for="og_image_path" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            OG Image Path
                        </label>
                        <input type="text"
                               name="og_image_path"
                               id="og_image_path"
                               value="{{ old('og_image_path', $list->og_image_path) }}"
                               placeholder="/images/banner.webp"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Path for social sharing image</p>
                    </div>
                </div>

                {{-- Visibility Options --}}
                <div class="flex items-center gap-6 mt-6">
                    <label class="flex items-center">
                        <input type="checkbox"
                               name="is_public"
                               value="1"
                               {{ old('is_public', $list->is_public) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Public</span>
                    </label>

                    <label class="flex items-center">
                        <input type="checkbox"
                               name="is_active"
                               value="1"
                               {{ old('is_active', $list->is_active) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                    </label>
                </div>

                {{-- Event-specific fields (only for event-type lists) --}}
                @if($list->isEvents())
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Event Details
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label for="event_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Event Time
                                </label>
                                <input type="datetime-local"
                                       name="event_time"
                                       id="event_time"
                                       value="{{ old('event_time', $list->event_data['event_time'] ?? '') }}"
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            </div>

                            <div>
                                <label for="event_timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Timezone
                                </label>
                                <select name="event_timezone"
                                        id="event_timezone"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                                    <option value="">Select timezone</option>
                                    @php
                                        $timezones = [
                                            'America/New_York' => 'Eastern Time (ET)',
                                            'America/Chicago' => 'Central Time (CT)',
                                            'America/Denver' => 'Mountain Time (MT)',
                                            'America/Los_Angeles' => 'Pacific Time (PT)',
                                            'America/Sao_Paulo' => 'Brasilia Time (BRT)',
                                            'Europe/London' => 'London (GMT/BST)',
                                            'Europe/Paris' => 'Central European (CET)',
                                            'Europe/Lisbon' => 'Lisbon (WET)',
                                            'Asia/Tokyo' => 'Japan (JST)',
                                            'Asia/Seoul' => 'Korea (KST)',
                                            'Australia/Sydney' => 'Sydney (AEST)',
                                            'UTC' => 'UTC',
                                        ];
                                        $selectedTimezone = old('event_timezone', $list->event_data['event_timezone'] ?? '');
                                    @endphp
                                    @foreach($timezones as $tz => $label)
                                        <option value="{{ $tz }}" {{ $selectedTimezone === $tz ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="md:col-span-2">
                                <label for="video_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Video URL
                                </label>
                                <input type="url"
                                       name="video_url"
                                       id="video_url"
                                       value="{{ old('video_url', $list->event_data['video_url'] ?? '') }}"
                                       placeholder="https://www.youtube.com/watch?v=... or https://twitch.tv/..."
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        <div class="mt-6">
                            <label for="event_about" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                About
                            </label>
                            <textarea name="event_about"
                                      id="event_about"
                                      rows="3"
                                      placeholder="Extended details about the event..."
                                      class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">{{ old('event_about', $list->event_data['about'] ?? '') }}</textarea>
                        </div>

                        <div class="mt-6">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Social Links</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label for="social_twitter" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        Twitter / X
                                    </label>
                                    <input type="url"
                                           name="social_twitter"
                                           id="social_twitter"
                                           value="{{ old('social_twitter', $list->event_data['social_links']['twitter'] ?? '') }}"
                                           placeholder="https://x.com/..."
                                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                                </div>

                                <div>
                                    <label for="social_youtube" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        YouTube
                                    </label>
                                    <input type="url"
                                           name="social_youtube"
                                           id="social_youtube"
                                           value="{{ old('social_youtube', $list->event_data['social_links']['youtube'] ?? '') }}"
                                           placeholder="https://youtube.com/..."
                                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                                </div>

                                <div>
                                    <label for="social_twitch" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        Twitch
                                    </label>
                                    <input type="url"
                                           name="social_twitch"
                                           id="social_twitch"
                                           value="{{ old('social_twitch', $list->event_data['social_links']['twitch'] ?? '') }}"
                                           placeholder="https://twitch.tv/..."
                                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                                </div>

                                <div>
                                    <label for="social_discord" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        Discord
                                    </label>
                                    <input type="url"
                                           name="social_discord"
                                           id="social_discord"
                                           value="{{ old('social_discord', $list->event_data['social_links']['discord'] ?? '') }}"
                                           placeholder="https://discord.gg/..."
                                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="mt-6 flex justify-end">
                    <button type="submit"
                            class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>

        {{-- Game Management Section --}}
        <div>
            <!-- Game Search -->
            <x-admin.system-lists.game-search :list="$list" />

            <!-- Games in List -->
            <div class="mt-6">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-4">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Games ({{ $list->games->count() }})
                    </h2>

                    <div class="flex items-center gap-6 flex-wrap">
                        {{-- Filter --}}
                        <div class="relative">
                            <input type="text"
                                   id="game-filter"
                                   placeholder="Filter games..."
                                   oninput="filterGames(this.value)"
                                   class="w-64 pl-3 pr-9 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <svg class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        {{-- Sort Toggle --}}
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Sort:</span>
                            <a href="{{ route('admin.system-lists.edit', [$list->list_type->toSlug(), $list->slug, 'sort' => 'order']) }}"
                               class="px-3 py-1.5 rounded-lg transition text-sm {{ ($sortBy ?? 'order') === 'order' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                                Manual
                            </a>
                            <a href="{{ route('admin.system-lists.edit', [$list->list_type->toSlug(), $list->slug, 'sort' => 'release_date']) }}"
                               class="px-3 py-1.5 rounded-lg transition text-sm {{ ($sortBy ?? 'order') === 'release_date' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                                Release Date
                            </a>
                        </div>

                        {{-- View Toggle --}}
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600 dark:text-gray-400">View:</span>
                            <button
                                onclick="toggleViewMode('grid')"
                                class="px-3 py-1.5 rounded-lg transition text-sm {{ $viewMode === 'grid' ? 'bg-orange-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}"
                            >
                                Grid
                            </button>
                            <button
                                onclick="toggleViewMode('list')"
                                class="px-3 py-1.5 rounded-lg transition text-sm {{ $viewMode === 'list' ? 'bg-orange-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}"
                            >
                                List
                            </button>
                        </div>

                        @if(!empty($gamesByMonth))
                            {{-- Expand / Collapse All --}}
                            <div class="flex items-center gap-2">
                                <button onclick="window.dispatchEvent(new CustomEvent('sections-expand'))"
                                        class="px-3 py-1.5 text-sm rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                                    Expand All
                                </button>
                                <button onclick="window.dispatchEvent(new CustomEvent('sections-collapse'))"
                                        class="px-3 py-1.5 text-sm rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                                    Collapse All
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

                @if($list->games->count() > 0)
                    @if(!empty($gamesByMonth))
                        {{-- Sectioned view: Vue mount point + shared data rendered once --}}
                        @php
                            $activePlatforms = \App\Enums\PlatformEnum::getActivePlatforms()->map(fn($enum) => ['id' => $enum->value, 'label' => $enum->label(), 'color' => $enum->color()])->values();
                            $systemGenres = \App\Models\Genre::visible()->where('is_pending_review', false)->ordered()->get(['id', 'name', 'slug']);
                        @endphp
                        <div id="game-edit-modals"
                             data-list-type="{{ $list->list_type->toSlug() }}"
                             data-list-slug="{{ $list->slug }}"
                             data-list-name="{{ $list->name }}"
                             data-toggle-highlight-url="{{ route('admin.system-lists.games.toggle-highlight', [$list->list_type->toSlug(), $list->slug, '__GAME_ID__']) }}"
                             data-toggle-indie-url="{{ route('admin.system-lists.games.toggle-indie', [$list->list_type->toSlug(), $list->slug, '__GAME_ID__']) }}"
                             data-get-genres-url="{{ route('admin.system-lists.games.genres', [$list->list_type->toSlug(), $list->slug, '__GAME_ID__']) }}"
                             data-update-pivot-url="{{ route('admin.system-lists.games.update-pivot', [$list->list_type->toSlug(), $list->slug, '__GAME_ID__']) }}"
                             data-csrf-token="{{ csrf_token() }}"
                             data-platforms="{{ $activePlatforms->toJson() }}"
                             data-system-genres="{{ $systemGenres->toJson() }}">
                        </div>

                        <div class="space-y-4">
                            @foreach($gamesByMonth as $monthKey => $section)
                                <div x-data="{ open: true }"
                                     @sections-expand.window="open = true"
                                     @sections-collapse.window="open = false"
                                     data-section-wrapper
                                     class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                                    {{-- Section Header --}}
                                    <button @click="open = !open"
                                            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                                        <div class="flex items-center gap-3">
                                            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400 transition-transform duration-200"
                                                 :class="{ 'rotate-90': open }"
                                                 fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                                                {{ $section['label'] }}
                                            </h3>
                                            <span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300">
                                                {{ count($section['games']) }}
                                            </span>
                                        </div>
                                    </button>

                                    {{-- Section Content --}}
                                    <div x-show="open" x-collapse>
                                        <div class="px-6 pb-6">
                                            <x-admin.system-lists.game-grid
                                                :games="collect($section['games'])"
                                                :list="$list"
                                                :viewMode="$viewMode"
                                                :sectionKey="$monthKey"
                                            />
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <x-admin.system-lists.game-grid :games="$list->games" :list="$list" :viewMode="$viewMode" />
                    @endif
                @else
                    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                        <p class="text-lg text-gray-600 dark:text-gray-400">
                            No games in this list yet.
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-500 mt-2">
                            Use the search above to add games.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        function filterGames(query) {
            const term = query.toLowerCase().trim();
            const cards = document.querySelectorAll('.game-card');

            cards.forEach(card => {
                const name = card.dataset.gameName || '';
                card.style.display = (!term || name.includes(term)) ? '' : 'none';
            });

            // Hide sections that have no visible cards
            document.querySelectorAll('[data-section-wrapper]').forEach(section => {
                const visibleCards = section.querySelectorAll('.game-card:not([style*="display: none"])');
                section.style.display = visibleCards.length > 0 ? '' : 'none';
            });
        }

        function toggleViewMode(mode) {
            fetch('{{ route('user.lists.toggle-view') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ mode: mode })
            }).then(() => {
                window.location.reload();
            });
        }
    </script>
@endsection
