@props(['video'])

<button type="button"
        class="neon-card group relative flex w-full flex-col overflow-hidden p-[9px] text-left"
        data-video-id="{{ $video->youtube_id }}"
        data-video-title="{{ $video->title }}"
        aria-label="{{ __('Play video') }}: {{ $video->title }}">
    <div class="relative aspect-video w-full overflow-hidden rounded-[14px] bg-black">
        <img src="{{ $video->thumbnail_url ?? $video->thumbnailMaxRes() }}"
             onerror="this.onerror=null;this.src='{{ $video->thumbnailHq() }}'"
             alt="{{ $video->title }}"
             class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
             loading="lazy">

        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/55 via-black/10 to-transparent"></div>

        <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-black/60 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-orange-400 backdrop-blur-sm">
            <span class="h-1.5 w-1.5 rounded-full bg-orange-400 shadow-[0_0_8px_var(--neon-orange)]"></span>
            {{ __('Featured') }}
        </span>

        <x-videos.category-badge :video="$video" variant="corner" />

        <span class="absolute inset-0 flex items-center justify-center">
            <span class="flex h-[72px] w-[72px] items-center justify-center rounded-full bg-gradient-to-br from-[var(--neon-orange)] to-[var(--neon-purple)] shadow-[0_0_30px_rgba(124,58,237,0.55)] transition-transform duration-300 group-hover:scale-110">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="#ffffff" aria-hidden="true">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </span>
        </span>

        @if ($video->durationFormatted())
            <span class="absolute bottom-3 right-3 rounded bg-black/80 px-2 py-1 font-mono text-xs font-semibold text-white">
                {{ $video->durationFormatted() }}
            </span>
        @endif
    </div>

    <div class="flex items-end justify-between gap-4 px-2 pb-1 pt-3.5">
        <div class="min-w-0 flex-1">
            @if ($video->channel_name || $video->published_at)
                <p class="mb-1.5 flex flex-wrap items-center gap-2 text-[0.7rem] font-semibold uppercase tracking-[0.08em]">
                    @if ($video->channel_name)
                        <span class="text-orange-400">{{ $video->channel_name }}</span>
                    @endif
                    @if ($video->channel_name && $video->published_at)
                        <span class="text-white/20">·</span>
                    @endif
                    @if ($video->published_at)
                        <time datetime="{{ $video->published_at->toIso8601String() }}" class="text-cyan-400">
                            {{ $video->published_at->diffForHumans() }}
                        </time>
                    @endif
                </p>
            @endif

            <h3 class="text-lg font-bold leading-tight tracking-tight text-white line-clamp-2 group-hover:text-cyan-300">
                {{ $video->title }}
            </h3>
        </div>
    </div>
</button>
