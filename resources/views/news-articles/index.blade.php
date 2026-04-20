@extends('layouts.app')

@php
    use App\Enums\NewsLocaleEnum;

    $pageTitle = __('News');
    $pageDescription = __('Latest gaming news, curated and localized — covering releases, industry updates, and behind-the-scenes coverage.');
    $canonicalUrl = $articles->currentPage() > 1
        ? $articles->url($articles->currentPage())
        : $locale->indexUrl();

    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Games Outbreak', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $pageTitle, 'item' => $locale->indexUrl()],
        ],
    ];
@endphp

@section('body-class', 'neon-body theme-neon')

@section('html-lang', $locale->value)

@section('title', $pageTitle)

@section('meta-description', $pageDescription)

@push('seo')
    <link rel="canonical" href="{{ $canonicalUrl }}">
    @foreach (NewsLocaleEnum::cases() as $alt)
        <link rel="alternate" hreflang="{{ $alt->value }}" href="{{ $alt->indexUrl() }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ NewsLocaleEnum::En->indexUrl() }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="Games Outbreak">
    <meta property="og:locale" content="{{ str_replace('-', '_', $locale->value) }}">
    @foreach (NewsLocaleEnum::cases() as $alt)
        @if ($alt !== $locale)
            <meta property="og:locale:alternate" content="{{ str_replace('-', '_', $alt->value) }}">
        @endif
    @endforeach

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">

    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<div class="theme-neon overflow-x-hidden">
    <div class="page-shell py-10">

        <div class="mb-8">
            <x-homepage.section-heading icon="newspaper" :title="$pageTitle" />
        </div>

        {{-- Article list --}}
        @forelse ($articles as $article)
            @php $loc = $article->localizations->first(); @endphp
            @if ($loc)
                <article>
                <a href="{{ $locale->articleUrl($article) }}"
                   class="neon-card group mb-4 flex flex-col overflow-hidden p-[9px] sm:flex-row sm:items-start sm:gap-4">

                    {{-- Thumbnail --}}
                    <div class="relative [transform:translateZ(0)] shrink-0 overflow-hidden rounded-[14px] sm:w-64" style="height:160px">
                        @if ($article->featured_image_url)
                            <img src="{{ $article->featured_image_url }}"
                                 alt="{{ $loc->title }}"
                                 class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                 loading="lazy">
                        @else
                            <div class="h-full w-full bg-gradient-to-br from-orange-500/20 to-violet-500/20"></div>
                        @endif
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                    </div>

                    {{-- Content --}}
                    <div class="relative mt-3 flex flex-1 flex-col justify-between sm:mt-0 sm:py-1">
                        <div>
                            <p class="mb-2 flex flex-wrap items-center gap-2 text-[0.7rem] font-semibold uppercase tracking-[0.08em]">
                                @if ($article->source_name)
                                    <span class="text-orange-400">{{ $article->source_name }}</span>
                                    <span class="text-white/20">·</span>
                                @endif
                                @if ($article->published_at)
                                    <time datetime="{{ $article->published_at->toIso8601String() }}" class="text-cyan-400">
                                        {{ $article->published_at->diffForHumans() }}
                                    </time>
                                @endif
                            </p>

                            <h2 class="text-[0.95rem] font-bold uppercase leading-snug tracking-[0.04em] text-slate-100 transition-colors group-hover:text-cyan-300">
                                {{ $loc->title }}
                            </h2>

                            @if ($loc->summary_short)
                                <p class="mt-2 text-[0.8rem] leading-relaxed text-slate-400">
                                    {{ $loc->summary_short }}
                                </p>
                            @endif

                            @if ($loc->summary_medium)
                                <p class="mt-2 text-[0.8rem] leading-relaxed text-slate-300">
                                    {{ $loc->summary_medium }}
                                </p>
                            @endif
                        </div>
                    </div>
                </a>
                </article>
            @endif
        @empty
            <div class="neon-panel px-6 py-14 text-center">
                <x-heroicon-o-newspaper class="mx-auto mb-4 h-10 w-10 text-slate-600" />
                <p class="text-sm uppercase tracking-[0.08em] text-slate-400">No articles available.</p>
                <p class="mt-1 text-xs uppercase tracking-[0.08em] text-slate-600">Check back soon for the latest gaming news.</p>
            </div>
        @endforelse

        {{-- Pagination --}}
        @if ($articles->hasPages())
            <div class="mt-8">
                {{ $articles->links() }}
            </div>
        @endif

    </div>
</div>
@endsection