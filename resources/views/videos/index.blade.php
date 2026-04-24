@extends('layouts.app')

@php
    $pageTitle = __('Game Videos');
    $pageDescription = __('Curated game videos — trailers, reveals, and gameplay coverage, hand-picked by the Games Outbreak crew.');
    $canonicalUrl = $videos->currentPage() > 1
        ? $videos->url($videos->currentPage())
        : route('videos.index');

    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Games Outbreak', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $pageTitle, 'item' => route('videos.index')],
        ],
    ];
@endphp

@section('body-class', 'neon-body theme-neon')

@section('title', $pageTitle)

@section('meta-description', $pageDescription)

@push('seo')
    <link rel="canonical" href="{{ $canonicalUrl }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="Games Outbreak">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">

    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<div class="theme-neon overflow-x-hidden">
    <div class="page-shell py-10">

        <div class="mb-8">
            <x-homepage.section-heading icon="video" :title="$pageTitle" />
        </div>

        @forelse ($videos as $video)
            <article>
                <button type="button"
                        class="neon-card group mb-4 flex w-full flex-col overflow-hidden p-[9px] text-left sm:flex-row sm:items-start sm:gap-4"
                        data-video-id="{{ $video->youtube_id }}"
                        data-video-title="{{ $video->title }}">

                    <div class="relative [transform:translateZ(0)] shrink-0 overflow-hidden rounded-[14px] sm:w-64" style="height:160px">
                        <img src="{{ $video->thumbnail_url ?? $video->thumbnailHq() }}"
                             onerror="this.onerror=null;this.src='{{ $video->thumbnailHq() }}'"
                             alt="{{ $video->title }}"
                             class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                             loading="lazy">
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>

                        <span class="absolute inset-0 flex items-center justify-center">
                            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-[var(--neon-orange)] to-[var(--neon-purple)] shadow-[0_0_20px_rgba(124,58,237,0.5)] transition-transform duration-300 group-hover:scale-110">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="#ffffff" aria-hidden="true">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </span>
                        </span>

                        @if ($video->durationFormatted())
                            <span class="absolute bottom-2 right-2 rounded bg-black/70 px-1.5 py-0.5 text-[10px] font-mono font-semibold text-white">
                                {{ $video->durationFormatted() }}
                            </span>
                        @endif
                    </div>

                    <div class="relative mt-3 flex flex-1 flex-col justify-between sm:mt-0 sm:py-1">
                        <div>
                            <p class="mb-2 flex flex-wrap items-center gap-2 text-[0.7rem] font-semibold uppercase tracking-[0.08em]">
                                @if ($video->channel_name)
                                    <span class="text-orange-400">{{ $video->channel_name }}</span>
                                    <span class="text-white/20">·</span>
                                @endif
                                @if ($video->published_at)
                                    <time datetime="{{ $video->published_at->toIso8601String() }}" class="text-cyan-400">
                                        {{ $video->published_at->diffForHumans() }}
                                    </time>
                                @endif
                            </p>

                            <h2 class="text-[0.95rem] font-bold uppercase leading-snug tracking-[0.04em] text-slate-100 transition-colors group-hover:text-cyan-300">
                                {{ $video->title }}
                            </h2>

                            @if ($video->description)
                                <p class="mt-2 text-[0.8rem] leading-relaxed text-slate-400 line-clamp-2">
                                    {{ $video->description }}
                                </p>
                            @endif
                        </div>
                    </div>
                </button>
            </article>
        @empty
            <div class="neon-panel px-6 py-14 text-center">
                <x-heroicon-o-video-camera class="mx-auto mb-4 h-10 w-10 text-slate-600" />
                <p class="text-sm uppercase tracking-[0.08em] text-slate-400">{{ __('No videos yet.') }}</p>
                <p class="mt-1 text-xs uppercase tracking-[0.08em] text-slate-600">{{ __('Check back soon for trailers and gameplay coverage.') }}</p>
            </div>
        @endforelse

        @if ($videos->hasPages())
            <div class="mt-8">
                {{ $videos->links() }}
            </div>
        @endif

    </div>
</div>
@endsection
