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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Sidebar: List Settings -->
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">System List Settings</h2>

                    <form action="{{ route('admin.system-lists.update', [$list->list_type->toSlug(), $list->slug]) }}" method="POST">
                        @csrf
                        @method('PATCH')

                        <div class="mb-4">
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

                        <div class="mb-4">
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Description
                            </label>
                            <textarea name="description"
                                      id="description"
                                      rows="3"
                                      class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">{{ old('description', $list->description) }}</textarea>
                        </div>

                        <div class="mb-4">
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

                        <div class="mb-4">
                            <label class="flex items-center mb-2">
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

                        <button type="submit"
                                class="w-full px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                            Save Changes
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Content: Game Management -->
            <div class="lg:col-span-2">
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
