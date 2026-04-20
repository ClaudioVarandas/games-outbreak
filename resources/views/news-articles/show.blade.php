@extends('layouts.app')

@php
    use App\Enums\NewsLocaleEnum;

    $metaTitle = $localization->seo_title ?: $localization->title;
    $metaDescription = $localization->seo_description
        ?: ($localization->summary_short ?: $localization->summary_medium);
    $canonicalUrl = $locale->articleUrl($article);
    $publishedIso = $article->published_at?->toIso8601String();
    $modifiedIso = $article->updated_at?->toIso8601String();

    $articleSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonicalUrl],
        'headline' => $localization->title,
        'description' => $metaDescription,
        'image' => $article->featured_image_url ? [$article->featured_image_url] : null,
        'datePublished' => $publishedIso,
        'dateModified' => $modifiedIso,
        'inLanguage' => $locale->value,
        'url' => $canonicalUrl,
        'isBasedOn' => $article->source_url ?: null,
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'Games Outbreak',
            'url' => url('/'),
        ],
        'author' => $article->source_name ? [
            '@type' => 'Organization',
            'name' => $article->source_name,
            'url' => $article->source_url,
        ] : null,
    ]);

    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Games Outbreak', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('News'), 'item' => $locale->indexUrl()],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $localization->title, 'item' => $canonicalUrl],
        ],
    ];
@endphp

@section('body-class', 'neon-body theme-neon')

@section('html-lang', $locale->value)

@section('title', $metaTitle)

@section('meta-description', $metaDescription)

@push('seo')
    <link rel="canonical" href="{{ $canonicalUrl }}">
    @foreach (NewsLocaleEnum::cases() as $alt)
        @if ($article->{$alt->slugColumn()} && $article->localization($alt->value))
            <link rel="alternate" hreflang="{{ $alt->value }}" href="{{ $alt->articleUrl($article) }}">
        @endif
    @endforeach
    @if ($article->{NewsLocaleEnum::En->slugColumn()} && $article->localization(NewsLocaleEnum::En->value))
        <link rel="alternate" hreflang="x-default" href="{{ NewsLocaleEnum::En->articleUrl($article) }}">
    @endif

    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $metaTitle }}">
    @if ($metaDescription)
        <meta property="og:description" content="{{ $metaDescription }}">
    @endif
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="Games Outbreak">
    <meta property="og:locale" content="{{ str_replace('-', '_', $locale->value) }}">
    @foreach (NewsLocaleEnum::cases() as $alt)
        @if ($alt !== $locale && $article->{$alt->slugColumn()} && $article->localization($alt->value))
            <meta property="og:locale:alternate" content="{{ str_replace('-', '_', $alt->value) }}">
        @endif
    @endforeach
    @if ($article->featured_image_url)
        <meta property="og:image" content="{{ $article->featured_image_url }}">
        <meta property="og:image:alt" content="{{ $localization->title }}">
    @endif
    @if ($publishedIso)
        <meta property="article:published_time" content="{{ $publishedIso }}">
    @endif
    @if ($modifiedIso)
        <meta property="article:modified_time" content="{{ $modifiedIso }}">
    @endif
    @if ($article->source_name)
        <meta property="article:section" content="{{ $article->source_name }}">
    @endif

    <meta name="twitter:card" content="{{ $article->featured_image_url ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    @if ($metaDescription)
        <meta name="twitter:description" content="{{ $metaDescription }}">
    @endif
    @if ($article->featured_image_url)
        <meta name="twitter:image" content="{{ $article->featured_image_url }}">
    @endif

    <script type="application/ld+json">{!! json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<div class="theme-neon overflow-x-hidden">
    <div class="page-shell py-10">

        {{-- Back navigation --}}
        <div class="mb-6">
            <a href="{{ $locale->indexUrl() }}"
               class="neon-btn-ghost inline-flex items-center gap-2 text-xs">
                ← {{ __('News') }}
            </a>
        </div>

        {{-- Article card --}}
        <article class="neon-card neon-card--static overflow-hidden p-[9px]">

            {{-- Content — readable width, centred --}}
            <div class="mx-auto max-w-7xl px-4 pb-8 pt-6">

                {{-- Article header --}}
                <header class="mb-8">
                    <span class="neon-eyebrow mb-4 block">{{ $article->source_name }}</span>

                    <h1 class="mb-4 text-3xl font-bold uppercase leading-tight tracking-[0.03em] text-slate-50 md:text-4xl">
                        {{ $localization->title }}
                    </h1>

                    {{-- Hero image — after h1 --}}
                    @if ($article->featured_image_url)
                        <div class="relative my-6 mx-auto aspect-video max-w-3xl overflow-hidden rounded-[1.18rem]">
                            <img src="{{ $article->featured_image_url }}"
                                 alt="{{ $localization->title }}"
                                 class="absolute inset-0 h-full w-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                        </div>
                    @endif

                    @if ($article->published_at)
                        <time datetime="{{ $publishedIso }}" class="text-xs uppercase tracking-[0.08em] text-orange-400/80">
                            {{ $article->published_at->format('d M Y') }}
                        </time>
                    @endif

                    @if ($localization->summary_medium)
                        <p class="mt-5 border-l-2 border-orange-500/50 pl-4 text-base leading-relaxed text-slate-300">
                            {{ $localization->summary_medium }}
                        </p>
                    @endif
                </header>

                {{-- Body (Tiptap JSON rendered) --}}
                @if ($localization->body)
                    <div class="max-w-none">
                        @include('news-articles._tiptap-content', ['content' => $localization->body])
                    </div>
                @endif

                {{-- Source link --}}
                @if ($article->source_url)
                    <div class="mt-10 flex flex-wrap items-center gap-2 border-t border-white/10 pt-6">
                        <span class="text-sm text-slate-400">{{ __('Source:') }}</span>
                        <a href="{{ $article->source_url }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="text-sm text-orange-400 transition-colors hover:text-orange-300">
                            {{ $article->source_name }} →
                        </a>
                    </div>
                @endif

            </div>
        </article>

    </div>
</div>
@endsection
