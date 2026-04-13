@extends('layouts.app')

@section('title', 'News')

@section('body-class', 'neon-body')

@section('content')
<div class="theme-neon overflow-x-hidden">
    <div class="page-shell py-10">

        {{-- Page Heading --}}
        <div class="mb-8">
            <x-homepage.section-heading icon="newspaper" title="News" />
        </div>

        @if($news->isNotEmpty())
            <div class="flex flex-col gap-4">
                @foreach($news as $article)
                    <a href="{{ route('news.show', $article) }}"
                       class="neon-card group flex flex-col overflow-hidden p-[9px] sm:flex-row sm:items-start sm:gap-4">

                        {{-- Image --}}
                        <div class="relative [transform:translateZ(0)] shrink-0 overflow-hidden rounded-[14px] sm:w-64"
                             style="height:160px">
                            @if($article->image_url)
                                <img src="{{ $article->image_url }}"
                                     alt="{{ $article->title }}"
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
                                {{-- Meta --}}
                                <p class="mb-2 flex flex-wrap items-center gap-2 text-[0.7rem] font-semibold uppercase tracking-[0.08em]">
                                    @if($article->source_name)
                                        <span class="text-orange-400">{{ $article->source_name }}</span>
                                        <span class="text-white/20">·</span>
                                    @endif
                                    <span class="text-slate-500">{{ $article->formatted_published_at }}</span>
                                    <span class="text-white/20">·</span>
                                    <span class="text-slate-500">{{ $article->reading_time }} min read</span>
                                </p>

                                <h2 class="text-[0.95rem] font-bold uppercase leading-snug tracking-[0.04em] text-slate-100 transition-colors group-hover:text-cyan-300">
                                    {{ $article->title }}
                                </h2>

                                @if($article->summary)
                                    <p class="mt-2 line-clamp-2 text-[0.8rem] leading-relaxed text-slate-400">
                                        {{ $article->summary }}
                                    </p>
                                @endif
                            </div>

                            @if($article->tags)
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    @foreach(array_slice($article->tags, 0, 3) as $tag)
                                        <span class="neon-platform-pill">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-8">
                {{ $news->links() }}
            </div>
        @else
            <div class="neon-panel px-6 py-14 text-center">
                <x-heroicon-o-newspaper class="mx-auto mb-4 h-10 w-10 text-slate-600" />
                <p class="text-sm uppercase tracking-[0.08em] text-slate-400">No news articles yet.</p>
                <p class="mt-1 text-xs uppercase tracking-[0.08em] text-slate-600">Check back soon for the latest gaming news.</p>
            </div>
        @endif

    </div>
</div>
@endsection
