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
            <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                <button @click="open = !open"
                        class="{{ in_array($active, ['releases', 'upcoming']) ? 'text-orange-400 font-semibold' : 'text-white hover:text-orange-300' }} transition flex items-center gap-1">
                    Releases
                    <svg class="w-3.5 h-3.5 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div x-show="open"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute left-0 mt-2 w-40 bg-gray-800 rounded-md shadow-lg z-50 border border-gray-600 overflow-hidden"
                     style="display: none;">
                    <a href="{{ route('releases.year', now()->year) }}"
                       class="{{ $active === 'releases' ? 'text-orange-400 bg-gray-700' : 'text-white hover:bg-gray-700' }} block px-4 py-2 text-sm transition">
                        Curated
                    </a>
                    <a href="{{ route('upcoming') }}"
                       class="{{ $active === 'upcoming' ? 'text-orange-400 bg-gray-700' : 'text-white hover:bg-gray-700' }} block px-4 py-2 text-sm transition">
                        Upcoming
                    </a>
                </div>
            </div>
            <a href="{{ route('releases.seasoned') }}"
               class="{{ $active === 'seasoned' ? 'text-orange-400 font-semibold' : 'text-white hover:text-orange-300' }} transition">
                Seasoned Lists
            </a>
        </nav>
    </div>
</div>
