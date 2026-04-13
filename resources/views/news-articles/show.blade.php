@extends('layouts.app')

@section('title', $localization->seo_title ?: $localization->title)

@section('meta-description', $localization->seo_description)

@section('content')
    <div class="container mx-auto px-4 py-8 max-w-3xl">
        <div class="mb-6">
            <a href="{{ route('news-articles.index', $locale->slugPrefix()) }}"
               class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                &larr; Notícias
            </a>
        </div>

        <article>
            @if ($article->featured_image_url)
                <img src="{{ $article->featured_image_url }}"
                     alt="{{ $localization->title }}"
                     class="w-full rounded-lg mb-6 object-cover max-h-96">
            @endif

            <header class="mb-8">
                <h1 class="text-4xl font-bold text-gray-900 dark:text-gray-100 mb-3">{{ $localization->title }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $article->source_name }}
                    @if ($article->published_at)
                        &middot; {{ $article->published_at->format('d M Y') }}
                    @endif
                </p>
                @if ($localization->summary_medium)
                    <p class="mt-4 text-lg text-gray-600 dark:text-gray-300 leading-relaxed">{{ $localization->summary_medium }}</p>
                @endif
            </header>

            @if ($localization->body)
                <div class="prose prose-gray dark:prose-invert max-w-none">
                    @include('news._tiptap-content', ['content' => $localization->body])
                </div>
            @endif

            @if ($article->source_url)
                <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ $article->source_url }}" target="_blank" rel="noopener"
                       class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                        Fonte original: {{ $article->source_name }} &rarr;
                    </a>
                </div>
            @endif
        </article>
    </div>
@endsection
