@props([
    'featured',
    'items' => collect(),
])

<section class="neon-panel neon-hero mb-4">
    <div class="neon-hero-grid">
        <article class="neon-card neon-hero-feature flex flex-col justify-between p-6 md:p-8">
            @if($featured->image_url)
                <img src="{{ $featured->image_url }}" alt="{{ $featured->title }}" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
            @else
                <div class="neon-hero-image"></div>
            @endif

            <div class="neon-hero-image"></div>

            <div class="relative z-10 flex h-full max-w-3xl flex-col">
                <div>
                    <span class="neon-eyebrow">Featured News</span>
                    <h1 class="mt-4 max-w-3xl text-3xl font-bold uppercase leading-tight text-slate-50 md:text-5xl">
                        {{ $featured->title }}
                    </h1>
                    <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300 md:text-base">
                        {{ \Illuminate\Support\Str::limit($featured->summary, 180) }}
                    </p>
                </div>

                <div class="mt-auto flex flex-wrap gap-3 pt-8">
                    <a href="{{ route('news.show', $featured) }}" class="neon-btn">
                        Read Feature
                    </a>

                    <a href="{{ route('news.index') }}" class="neon-btn-ghost">
                        View All News
                    </a>
                </div>
            </div>
        </article>

        <div class="grid gap-3">
            @foreach($items as $item)
                <x-homepage.hero-news-item :item="$item" />
            @endforeach
        </div>
    </div>
</section>
