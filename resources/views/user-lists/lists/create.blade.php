@extends('layouts.app')

@section('title', 'Create New List')

@section('content')
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <h1 class="text-4xl font-bold mb-10 text-gray-800 dark:text-gray-100">
            Create New List
        </h1>

        <form action="{{ route('user.lists.lists.store', $user->username) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
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

            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox"
                           name="is_public"
                           value="1"
                           {{ old('is_public') ? 'checked' : '' }}
                           class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Public (visible to everyone)</span>
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400 ml-6 mt-1">When unchecked, only you can see this list.</p>
            </div>

            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox"
                           name="is_active"
                           value="1"
                           {{ old('is_active', true) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400 ml-6 mt-1">Inactive lists are hidden from everyone except you.</p>
            </div>

            <div class="flex items-center justify-end gap-4 mt-8">
                <a href="{{ route('user.lists.lists', $user->username) }}"
                   class="px-6 py-2 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition">
                    Cancel
                </a>
                <button type="submit"
                        class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                    Create List
                </button>
            </div>
        </form>
    </div>
@endsection
