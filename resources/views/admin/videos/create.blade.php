@extends('layouts.app')

@section('title', 'Import Video (Admin)')

@section('content')
    <div class="page-shell py-8">
        <div class="flex items-center gap-4 mb-8">
            <a href="{{ route('admin.videos.index') }}"
               class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                &larr; Videos
            </a>
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">Import YouTube Video</h1>
        </div>

        <form method="POST" action="{{ route('admin.videos.store') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-6">
            @csrf
            <div>
                <label for="url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">YouTube URL</label>
                <input type="url"
                       id="url"
                       name="url"
                       value="{{ old('url') }}"
                       placeholder="https://www.youtube.com/watch?v=..."
                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('url') border-red-500 @enderror">

                @error('url')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Accepts youtube.com/watch?v=..., youtu.be/..., /shorts/..., /embed/... — metadata is fetched via YouTube Data API v3.
                </p>
            </div>

            <div>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="should_broadcast" value="1"
                           {{ old('should_broadcast', '1') ? 'checked' : '' }}
                           class="rounded border-gray-300 dark:border-gray-600">
                    <span>Broadcast to Telegram when the video is Ready</span>
                </label>
            </div>

            <div>
                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
                    Queue Import
                </button>
            </div>
        </form>

        @if ($recentVideos->isNotEmpty())
            <div class="mt-8">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Recent Imports</h2>
                <ul class="space-y-2">
                    @foreach ($recentVideos as $video)
                        <li class="flex items-center justify-between bg-white dark:bg-gray-800 rounded p-3 shadow-sm">
                            <span class="text-sm text-gray-700 dark:text-gray-300 truncate max-w-xs">{{ $video->title ?? $video->url }}</span>
                            <span class="ml-4 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $video->status->colorClass() }}">
                                {{ $video->status->label() }}
                            </span>
                            <a href="{{ route('admin.videos.show', $video) }}"
                               class="ml-4 text-blue-600 hover:text-blue-800 text-sm shrink-0">View</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection
