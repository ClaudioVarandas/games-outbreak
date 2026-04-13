@extends('layouts.app')

@section('body-class', 'neon-body theme-neon')

@section('title', $localization->seo_title ?: $localization->title)

@section('meta-description', $localization->seo_description)

@section('content')
<div class="theme-neon overflow-x-hidden">
    <div class="page-shell py-10" style="max-width: min(860px, calc(100% - 2rem))">

        {{-- Back navigation --}}
        <div class="mb-8">
            <a href="{{ $locale->indexUrl() }}"
               class="neon-btn-ghost inline-flex items-center gap-2 text-xs">
                ← {{ $locale === \App\Enums\NewsLocaleEnum::En ? 'News' : 'Notícias' }}
            </a>
        </div>

        <article>
            {{-- Hero image --}}
            @if ($article->featured_image_url)
                <div class="relative mb-8 aspect-video w-full overflow-hidden rounded-[1.25rem]">
                    <img src="{{ $article->featured_image_url }}"
                         alt="{{ $localization->title }}"
                         class="absolute inset-0 h-full w-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                </div>
            @endif

            {{-- Article header --}}
            <header class="mb-8">
                <span class="neon-eyebrow mb-4 block">{{ $article->source_name }}</span>

                <h1 class="mb-4 text-3xl font-bold uppercase leading-tight tracking-[0.03em] text-slate-50 md:text-4xl">
                    {{ $localization->title }}
                </h1>

                <p class="text-xs uppercase tracking-[0.08em] text-slate-500">
                    {{ $locale->label() }}
                    @if ($article->published_at)
                        · {{ $article->published_at->format('d M Y') }}
                    @endif
                </p>

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
                <div class="mt-10 border-t border-white/10 pt-6">
                    <a href="{{ $article->source_url }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="text-sm text-orange-400 transition-colors hover:text-orange-300">
                        Fonte original: {{ $article->source_name }} →
                    </a>
                </div>
            @endif
        </article>

    </div>
</div>
@endsection
