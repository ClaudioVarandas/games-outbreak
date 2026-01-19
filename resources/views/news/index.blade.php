@extends('layouts.app')

@section('title', 'News')

@section('content')
    <!-- Releases Navigation Menu -->
    <x-releases-nav active="news" />

    <div class="container mx-auto px-4 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                News
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">
                Latest gaming news and updates
            </p>
        </div>

        @if($news->isNotEmpty())
            <!-- News Feed -->
            <div class="space-y-6">
                @foreach($news as $article)
                    <article class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                        <a href="{{ route('news.show', $article) }}" class="flex flex-col md:flex-row">
                            @if($article->image_url)
                                <div class="md:w-72 md:flex-shrink-0">
                                    <img
                                        src="{{ $article->image_url }}"
                                        alt="{{ $article->title }}"
                                        class="w-full h-48 md:h-full object-cover"
                                        loading="lazy"
                                    >
                                </div>
                            @endif
                            <div class="p-6 flex flex-col justify-between flex-grow">
                                <div>
                                    <div class="flex items-center gap-3 mb-3">
                                        @if($article->source_name)
                                            <span class="text-xs font-medium text-orange-500 uppercase tracking-wider">
                                                {{ $article->source_name }}
                                            </span>
                                        @endif
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $article->formatted_published_at }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $article->reading_time }} min read
                                        </span>
                                    </div>
                                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2 hover:text-orange-500 transition-colors">
                                        {{ $article->title }}
                                    </h2>
                                    <p class="text-gray-600 dark:text-gray-400 line-clamp-2">
                                        {{ $article->summary }}
                                    </p>
                                </div>
                                <div class="mt-4 flex items-center gap-2">
                                    @if($article->tags)
                                        @foreach(array_slice($article->tags, 0, 3) as $tag)
                                            <span class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded">
                                                {{ $tag }}
                                            </span>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        </a>
                    </article>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-8">
                {{ $news->links() }}
            </div>
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                </svg>
                <p class="text-xl text-gray-600 dark:text-gray-400">
                    No news articles yet.
                </p>
                <p class="text-gray-500 dark:text-gray-500 mt-2">
                    Check back soon for the latest gaming news!
                </p>
            </div>
        @endif
    </div>
@endsection
