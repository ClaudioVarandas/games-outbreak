@props(['active' => ''])

<div class="bg-gray-700 border-b border-gray-600">
    <div class="container mx-auto px-4">
        <nav class="flex gap-6 py-3">
            @if(\App\Http\Middleware\EnsureNewsFeatureEnabled::isVisibleTo(auth()->user()))
                <a href="{{ route('news.index') }}"
                   class="{{ $active === 'news' ? 'text-orange-400 font-semibold' : 'text-white hover:text-orange-300' }} transition">
                    News
                </a>
            @endif
            <a href="{{ route('releases.year', now()->year) }}"
               class="{{ $active === 'releases' ? 'text-orange-400 font-semibold' : 'text-white hover:text-orange-300' }} transition">
                Releases
            </a>
            <a href="{{ route('releases.seasoned') }}"
               class="{{ $active === 'seasoned' ? 'text-orange-400 font-semibold' : 'text-white hover:text-orange-300' }} transition">
                Seasoned Lists
            </a>
        </nav>
    </div>
</div>
