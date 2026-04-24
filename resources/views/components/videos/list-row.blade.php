@props(['video'])

<button type="button"
        class="neon-card group grid w-full cursor-pointer grid-cols-[118px_1fr] items-center gap-3 p-2.5 text-left"
        data-video-id="{{ $video->youtube_id }}"
        data-video-title="{{ $video->title }}"
        aria-label="{{ __('Play video') }}: {{ $video->title }}">
    <div class="relative aspect-video overflow-hidden rounded-[10px] bg-black">
        <img src="{{ $video->thumbnail_url ?? $video->thumbnailHq() }}"
             onerror="this.onerror=null;this.src='{{ $video->thumbnailHq() }}'"
             alt=""
             class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.04]"
             loading="lazy">

        <span class="absolute inset-0 flex items-center justify-center opacity-0 transition-opacity duration-200 group-hover:opacity-100">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-black/70">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="#ffffff" aria-hidden="true">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </span>
        </span>

        @if ($video->durationFormatted())
            <span class="absolute bottom-0.5 right-0.5 rounded bg-black/80 px-1 text-[10px] font-mono font-semibold text-cyan-300">
                {{ $video->durationFormatted() }}
            </span>
        @endif
    </div>

    <div class="min-w-0">
        @if ($video->channel_name || $video->published_at)
            <p class="mb-1 flex flex-wrap items-center gap-1.5 text-[0.6rem] font-semibold uppercase tracking-[0.08em]">
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

        <h4 class="m-0 text-sm font-semibold leading-snug text-white line-clamp-2 group-hover:text-cyan-300">
            {{ $video->title }}
        </h4>

        @if ($video->category)
            <div class="mt-1.5">
                <x-videos.category-badge :video="$video" variant="inline" />
            </div>
        @endif
    </div>
</button>
