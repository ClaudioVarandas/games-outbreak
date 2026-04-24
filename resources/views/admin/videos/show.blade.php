@extends('layouts.app')

@section('title', 'Video Detail (Admin)')

@section('content')
    <div class="page-shell py-8">
        <div class="flex items-center gap-4 mb-8">
            <a href="{{ route('admin.videos.index') }}"
               class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                &larr; Videos
            </a>
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">Video Detail</h1>
        </div>

        @if (session('success'))
            <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-6">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $video->status->colorClass() }}">
                    {{ $video->status->label() }}
                </span>
                @if ($video->is_featured)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">★ Featured</span>
                @endif
                @if (! $video->is_active)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Hidden</span>
                @endif
                @if ($video->category)
                    <x-videos.category-badge :video="$video" variant="inline" />
                @endif
            </div>

            @if ($video->thumbnail_url)
                <div class="max-w-xl">
                    <img src="{{ $video->thumbnail_url }}" alt="" class="w-full rounded-lg shadow">
                </div>
            @endif

            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div class="col-span-2">
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Title</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $video->title ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">YouTube ID</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100 font-mono">{{ $video->youtube_id ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Channel</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $video->channel_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Duration</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $video->durationFormatted() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Published</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $video->published_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="font-medium text-gray-500 dark:text-gray-400">URL</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100 break-all">
                        <a href="{{ $video->url }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">{{ $video->url }}</a>
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Imported By</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $video->user?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Created</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $video->created_at->format('Y-m-d H:i') }}</dd>
                </div>
                @if ($video->failure_reason)
                    <div class="col-span-2">
                        <dt class="font-medium text-red-500">Failure Reason</dt>
                        <dd class="mt-1 text-red-700 dark:text-red-400">{{ $video->failure_reason }}</dd>
                    </div>
                @endif
                @if ($video->description)
                    <div class="col-span-2">
                        <dt class="font-medium text-gray-500 dark:text-gray-400">Description</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100 whitespace-pre-line line-clamp-6">{{ $video->description }}</dd>
                    </div>
                @endif
            </dl>

            <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                <form method="POST" action="{{ route('admin.videos.update-category', $video) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PATCH')
                    <div class="flex-1 min-w-64">
                        <label for="video_category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                        <select name="video_category_id" id="video_category_id"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">— none —</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" @selected($video->video_category_id === $cat->id)>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit"
                            class="inline-flex items-center bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-4 rounded">
                        Save Category
                    </button>
                </form>
            </div>

            <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <form method="POST" action="{{ route('admin.videos.toggle-featured', $video) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="inline-flex items-center bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded">
                        {{ $video->is_featured ? 'Unmark Featured' : 'Mark as Featured' }}
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.videos.toggle-active', $video) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="inline-flex items-center bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded">
                        {{ $video->is_active ? 'Hide' : 'Show' }}
                    </button>
                </form>

                @if ($video->watchUrl())
                    <a href="{{ $video->watchUrl() }}" target="_blank" rel="noopener"
                       class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
                        Open on YouTube &rarr;
                    </a>
                @endif

                <form method="POST" action="{{ route('admin.videos.destroy', $video) }}" onsubmit="return confirm('Delete this video?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
