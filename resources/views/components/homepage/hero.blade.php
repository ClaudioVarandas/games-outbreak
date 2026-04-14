@props([
    'featured',
    'items' => collect(),
    'newsLocale' => \App\Enums\NewsLocaleEnum::fromAppLocale(),
])

<section class="neon-panel neon-hero mb-4">
    <div class="neon-hero-grid">
        <article class="neon-card neon-hero-feature flex flex-col justify-between p-6 md:p-8">
            @if($featured->featured_image_url)
                <img src="{{ $featured->featured_image_url }}" alt="{{ $featured->localizations->first()?->title }}" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
            @else
                <div class="neon-hero-image"></div>
            @endif

            <div class="neon-hero-image"></div>

            <div class="relative z-10 flex h-full max-w-3xl flex-col">
                <div>
                    <span class="neon-eyebrow">{{ __('Featured News') }}</span>
                    <h1 class="mt-4 max-w-3xl text-3xl font-bold uppercase leading-tight text-slate-50 md:text-5xl">
                        {{ $featured->localizations->first()?->title }}
                    </h1>
                    <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300 md:text-base">
                        {{ \Illuminate\Support\Str::limit($featured->localizations->first()?->summary_short, 180) }}
                    </p>
                </div>

                <div class="mt-auto flex flex-wrap gap-3 pt-8">
                    <a href="{{ $newsLocale->articleUrl($featured) }}" class="neon-btn">
                        {{ __('Read') }}
                    </a>

                    <a href="{{ $newsLocale->indexUrl() }}" class="neon-btn-ghost">
                        {{ __('View All News') }}
                    </a>
                </div>
            </div>
        </article>

        <div class="grid gap-3">
            @foreach($items as $item)
                <x-homepage.hero-news-item :item="$item" :newsLocale="$newsLocale" />
            @endforeach
        </div>
    </div>
</section>
