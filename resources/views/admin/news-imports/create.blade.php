@extends('layouts.app')

@section('title', 'Import News URL (Admin)')

@section('content')
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <div class="flex items-center gap-4 mb-8">
            <a href="{{ route('admin.news-imports.index') }}"
               class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                &larr; Imports
            </a>
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">Import News URL</h1>
        </div>

        <form method="POST" action="{{ route('admin.news-imports.store') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-6">
            @csrf
            <div>
                <label for="url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Article URL</label>
                <input type="url"
                       id="url"
                       name="url"
                       value="{{ old('url') }}"
                       placeholder="https://ign.com/articles/..."
                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('url') border-red-500 @enderror">

                @error('url')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
                    Queue Import
                </button>
            </div>
        </form>

        @if ($recentImports->isNotEmpty())
            <div class="mt-8">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Recent Imports</h2>
                <ul class="space-y-2">
                    @foreach ($recentImports as $import)
                        <li class="flex items-center justify-between bg-white dark:bg-gray-800 rounded p-3 shadow-sm">
                            <span class="text-sm text-gray-700 dark:text-gray-300 truncate max-w-xs">{{ $import->url }}</span>
                            <span class="ml-4 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $import->status->colorClass() }}">
                                {{ $import->status->label() }}
                            </span>
                            <a href="{{ route('admin.news-imports.show', $import) }}"
                               class="ml-4 text-blue-600 hover:text-blue-800 text-sm shrink-0">View</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection
