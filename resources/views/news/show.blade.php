@extends('layouts.app')

@section('title', $news->title)

@section('content')
    <!-- Releases Navigation Menu -->
    <x-releases-nav active="news" />

    <div class="container mx-auto px-4 py-8">
        <article class="max-w-4xl mx-auto">
            <!-- Back Link -->
            <a href="{{ route('news.index') }}" class="inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-orange-500 mb-6">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to News
            </a>

            <!-- Article Header -->
            <header class="mb-8">
                <div class="flex items-center gap-3 mb-4">
                    @if($news->source_name)
                        <span class="text-sm font-medium text-orange-500 uppercase tracking-wider">
                            {{ $news->source_name }}
                        </span>
                    @endif
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $news->formatted_published_at }}
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $news->reading_time }} min read
                    </span>
                </div>

                <h1 class="text-4xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                    {{ $news->title }}
                </h1>

                <p class="text-xl text-gray-600 dark:text-gray-400">
                    {{ $news->summary }}
                </p>

                @if($news->tags)
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($news->tags as $tag)
                            <span class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-full">
                                {{ $tag }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </header>

            <!-- Featured Image -->
            @if($news->image_url)
                <div class="mb-8 rounded-lg overflow-hidden">
                    <img
                        src="{{ $news->image_url }}"
                        alt="{{ $news->title }}"
                        class="w-full h-auto object-cover"
                    >
                </div>
            @endif

            <!-- Article Content -->
            <div class="prose prose-lg dark:prose-invert max-w-none">
                @include('news._tiptap-content', ['content' => $news->content])
            </div>

            <!-- Source Link -->
            @if($news->source_url)
                <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ $news->source_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-orange-500 hover:text-orange-400">
                        <span>Read original article at {{ $news->source_name ?? 'source' }}</span>
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                    </a>
                </div>
            @endif

            <!-- Author Info -->
            @if($news->author)
                <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-orange-500 rounded-full flex items-center justify-center text-white font-bold text-lg">
                            {{ strtoupper(substr($news->author->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $news->author->name }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Author</p>
                        </div>
                    </div>
                </div>
            @endif
        </article>

        <!-- Related News -->
        @if($relatedNews->isNotEmpty())
            <div class="max-w-4xl mx-auto mt-12 pt-8 border-t border-gray-200 dark:border-gray-700">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Related News</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($relatedNews as $related)
                        <a href="{{ route('news.show', $related) }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                            @if($related->image_url)
                                <img
                                    src="{{ $related->image_url }}"
                                    alt="{{ $related->title }}"
                                    class="w-full h-40 object-cover"
                                    loading="lazy"
                                >
                            @endif
                            <div class="p-4">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ $related->formatted_published_at }}</p>
                                <h3 class="font-bold text-gray-900 dark:text-gray-100 line-clamp-2 hover:text-orange-500 transition-colors">
                                    {{ $related->title }}
                                </h3>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
