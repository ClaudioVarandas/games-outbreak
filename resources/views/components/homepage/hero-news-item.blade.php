@props([
    'item',
])

<a href="{{ route('news.show', $item) }}" class="neon-card grid grid-cols-[96px_minmax(0,1fr)] gap-3 p-3">
    <div class="aspect-square overflow-hidden rounded-2xl bg-slate-900/60">
        @if($item->image_url)
            <img src="{{ $item->image_url }}" alt="{{ $item->title }}" class="h-full w-full object-cover" loading="lazy">
        @else
            <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-orange-500/20 to-violet-500/20 text-xs font-semibold uppercase tracking-[0.08em] text-slate-300">
                News
            </div>
        @endif
    </div>

    <div class="flex min-w-0 flex-col justify-center">
        <span class="neon-eyebrow">News</span>
        <h3 class="mt-2 line-clamp-2 text-sm font-semibold uppercase tracking-[0.04em] text-slate-100">
            {{ $item->title }}
        </h3>
        <p class="mt-2 text-xs uppercase tracking-[0.08em] text-slate-400">
            {{ $item->formatted_published_at }}
        </p>
    </div>
</a>
