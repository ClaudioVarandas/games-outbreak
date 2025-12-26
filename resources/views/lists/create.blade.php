@extends('layouts.app')

@section('title', 'Create List')

@section('content')
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <h1 class="text-4xl font-bold mb-10 text-gray-800 dark:text-gray-100">
            Create New List
        </h1>

        <form action="{{ route('lists.store') }}" method="POST" class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            @csrf

            <div class="mb-6">
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    List Name <span class="text-red-500">*</span>
                </label>
                <input type="text" 
                       name="name" 
                       id="name" 
                       value="{{ old('name') }}"
                       required
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Description
                </label>
                <textarea name="description" 
                          id="description" 
                          rows="4"
                          class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    URL Slug (auto-generated if left empty)
                </label>
                <input type="text" 
                       name="slug" 
                       id="slug" 
                       value="{{ old('slug') }}"
                       pattern="[a-z0-9-]+"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                <p class="mt-1 text-xs text-gray-500">Only lowercase letters, numbers, and hyphens. Used to create a shareable URL for your list. Auto-generated from list name if left blank.</p>
                @error('slug')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" 
                           name="is_public" 
                           value="1"
                           id="is_public_checkbox"
                           {{ old('is_public') ? 'checked' : '' }}
                           class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Make this list public</span>
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400 ml-6 mt-1">Public lists can be accessed by anyone via the URL slug.</p>
            </div>

            @if($canCreateSystem)
                <div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                    <label class="flex items-center mb-2">
                        <input type="checkbox" 
                               name="is_system" 
                               value="1"
                               {{ old('is_system') ? 'checked' : '' }}
                               id="is_system_checkbox"
                               class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                        <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Create as System List</span>
                    </label>
                    <p class="text-xs text-gray-600 dark:text-gray-400 ml-6">System lists are featured lists that can be accessed via a public URL.</p>
                    
                    <div id="system_fields" class="mt-4 {{ old('is_system') ? '' : 'hidden' }}">
                        <div class="mb-4">
                            <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                URL Slug (auto-generated if left empty)
                            </label>
                            <input type="text" 
                                   name="slug" 
                                   id="slug" 
                                   value="{{ old('slug') }}"
                                   pattern="[a-z0-9-]+"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <p class="mt-1 text-xs text-gray-500">Only lowercase letters, numbers, and hyphens</p>
                            @error('slug')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       name="is_active" 
                                       value="1"
                                       {{ old('is_active', true) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                            </label>
                        </div>
                        <div>
                            <label for="start_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Start Date (optional)
                            </label>
                            <input type="date" 
                                   name="start_at" 
                                   id="start_at" 
                                   value="{{ old('start_at') }}"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <p class="mt-1 text-xs text-gray-500">List will be active starting from this date.</p>
                            @error('start_at')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <label for="end_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 mt-4">
                                End Date (optional)
                            </label>
                            <input type="date" 
                                   name="end_at" 
                                   id="end_at" 
                                   value="{{ old('end_at') }}"
                                   min="{{ old('start_at', date('Y-m-d', strtotime('+1 day'))) }}"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <p class="mt-1 text-xs text-gray-500">List will be automatically deactivated after this date.</p>
                            @error('end_at')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex gap-4">
                <button type="submit" class="flex-1 bg-orange-600 hover:bg-orange-700 text-white px-6 py-3 rounded-lg transition">
                    Create List
                </button>
                <a href="{{ route('lists.index') }}" class="flex-1 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-6 py-3 rounded-lg text-center transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        // Auto-generate slug from name when name changes (if slug is empty)
        const nameInput = document.getElementById('name');
        const slugInput = document.getElementById('slug');
        
        nameInput.addEventListener('input', function() {
            if (!slugInput.value) {
                slugInput.value = this.value.toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
            }
        });

        @if($canCreateSystem)
        document.getElementById('is_system_checkbox').addEventListener('change', function() {
            document.getElementById('system_fields').classList.toggle('hidden', !this.checked);
        });
        @endif
    </script>
@endsection

