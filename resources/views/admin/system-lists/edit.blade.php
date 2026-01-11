@extends('layouts.app')

@section('title', 'Edit ' . $list->name)

@section('content')
    <div class="container mx-auto px-4 py-8">
        <!-- Header with View Toggle -->
        <div class="mb-8 flex items-center justify-between">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                Edit {{ $list->name }}
            </h1>

            <div class="flex items-center gap-2">
                <button
                    onclick="toggleViewMode('grid')"
                    class="px-4 py-2 rounded-lg transition {{ $viewMode === 'grid' ? 'bg-orange-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}"
                >
                    <svg class="w-5 h-5 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                    </svg>
                    Grid
                </button>
                <button
                    onclick="toggleViewMode('list')"
                    class="px-4 py-2 rounded-lg transition {{ $viewMode === 'list' ? 'bg-orange-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}"
                >
                    <svg class="w-5 h-5 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                    </svg>
                    List
                </button>
            </div>
        </div>

        {{-- List Settings Header --}}
        <div class="mb-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-bold mb-6 text-gray-900 dark:text-white">List Settings</h2>

            <form action="{{ route('admin.system-lists.update', [$list->list_type->toSlug(), $list->slug]) }}" method="POST">
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
                <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">
                    Games ({{ $list->games->count() }})
                </h2>

                @if($list->games->count() > 0)
                    <x-admin.system-lists.game-grid :games="$list->games" :list="$list" :viewMode="$viewMode" />
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
