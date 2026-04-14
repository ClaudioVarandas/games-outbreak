@php
    $newsVisible = \App\Http\Middleware\EnsureNewsFeatureEnabled::isVisibleTo(auth()->user());
    $headerNewsLocale = $currentNewsLocale ?? null;
    if (! $headerNewsLocale && session('locale')) {
        try {
            $headerNewsLocale = \App\Enums\NewsLocaleEnum::fromPrefix(session('locale'));
        } catch (\Throwable) {}
    }
    $headerNewsLocale ??= \App\Enums\NewsLocaleEnum::fromAppLocale();
@endphp

<header class="site-header text-white" x-data="{ mobileSearchOpen: false }">
    <div class="site-header__shell">
        <div class="site-header__bar">
            <div class="flex flex-col gap-3 xl:grid xl:grid-cols-[auto_minmax(320px,1fr)_auto] xl:items-center xl:gap-4">

                {{-- Row 1 (mobile) / Col 1 (desktop): logo + mobile controls --}}
                <div class="flex items-center justify-between gap-3">
                    <a href="{{ route('homepage') }}" class="flex min-w-0 flex-1 items-center gap-3 transition hover:opacity-85">
                        <div class="site-header__brand-mark flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl">
                            <img
                                src="{{ asset('images/games-outbreak-logo.png') }}"
                                alt="Games Outbreak"
                                class="h-full w-full object-cover"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <span class="hidden h-full w-full items-center justify-center text-base font-black tracking-[0.18em] text-white">
                                GO
                            </span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold uppercase tracking-[0.08em] text-slate-100">Games Outbreak</p>
                            <p class="hidden truncate text-xs uppercase tracking-[0.08em] text-slate-400 sm:block">{{ __('News-first release radar') }}</p>
                        </div>
                    </a>

                    {{-- Mobile-only: locale + auth + search (hidden on desktop — desktop uses Col 3) --}}
                    <div class="flex shrink-0 items-center gap-2 xl:hidden">

                        {{-- Locale switcher --}}
                        <div x-data="{ open: false }" class="relative shrink-0" @click.outside="open = false">
                            <button
                                type="button"
                                class="inline-flex min-h-9 items-center rounded-full border border-slate-600/60 px-3 text-[0.7rem] font-bold uppercase tracking-[0.1em] text-slate-400 transition hover:border-slate-400 hover:text-slate-200"
                                @click="open = !open"
                                aria-label="Switch language">
                                {{ $headerNewsLocale->shortLabel() }}
                            </button>

                            <div
                                x-show="open"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="absolute right-0 mt-2 w-36 overflow-hidden rounded-2xl border border-cyan-300/20 bg-slate-900/95 text-sm text-slate-100 shadow-2xl"
                                style="display: none;">
                                @foreach (\App\Enums\NewsLocaleEnum::cases() as $l)
                                    <a href="{{ route('locale.switch', $l->slugPrefix()) }}"
                                       class="flex items-center gap-2 px-4 py-3 text-xs font-bold uppercase tracking-widest transition hover:bg-white/5 {{ $headerNewsLocale === $l ? 'text-orange-300' : 'text-slate-400' }}">
                                        {{ $l->shortLabel() }}
                                        @if ($headerNewsLocale === $l)
                                            <span class="ml-auto text-orange-400">✓</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        {{-- Auth --}}
                        @auth
                            <div x-data="{ open: false }" class="relative shrink-0" @click.outside="open = false">
                                <button
                                    type="button"
                                    class="site-header__icon-button inline-flex h-11 w-11 items-center justify-center rounded-full text-sm font-bold uppercase"
                                    @click="open = !open"
                                    aria-label="Open account menu">
                                    {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                                </button>

                                <div
                                    x-show="open"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border border-cyan-300/20 bg-slate-900/95 text-sm text-slate-100 shadow-2xl"
                                    style="display: none;">
                                    <a href="{{ route('user.games', ['user' => auth()->user()->username]) }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-white/5">
                                        <span>{{ __('My Games') }}</span>
                                    </a>

                                    @if(auth()->user()->isAdmin())
                                        <div class="border-t border-cyan-300/10 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-400">
                                            Admin
                                        </div>
                                        <a href="{{ route('admin.system-lists') }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-white/5">
                                            <span>System Lists</span>
                                        </a>
                                        <a href="{{ route('admin.user-lists') }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-white/5">
                                            <span>User Lists</span>
                                        </a>
                                        @if($newsVisible)
                                            <a href="{{ route('admin.news-imports.index') }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-white/5">
                                                <span>News Imports</span>
                                            </a>
                                            <a href="{{ route('admin.news-articles.index') }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-white/5">
                                                <span>News Articles</span>
                                            </a>
                                        @endif
                                    @endif

                                    <div class="border-t border-cyan-300/10">
                                        <form action="{{ route('logout') }}" method="POST">
                                            @csrf
                                            <button type="submit" class="w-full px-4 py-3 text-left transition hover:bg-white/5">
                                                Logout
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div x-data class="shrink-0">
                                <button
                                    type="button"
                                    class="site-header__icon-button inline-flex h-11 w-11 items-center justify-center rounded-full"
                                    @click.prevent="$dispatch('open-modal', 'login-modal')"
                                    aria-label="Open login modal">
                                    <x-heroicon-o-user-circle class="h-5 w-5" />
                                </button>
                            </div>
                        @endauth

                        {{-- Search open button --}}
                        <button
                            type="button"
                            class="site-header__icon-button inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                            @click="mobileSearchOpen = true"
                            aria-label="Open search">
                            <x-heroicon-o-magnifying-glass class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                {{-- Col 2 (desktop only): search bar --}}
                <div id="app-search" class="hidden xl:block w-full max-w-md mx-auto">
                    <global-search
                        :placeholder="'{{ __('Search games...') }}'"
                        :searching="'{{ __('Searching...') }}'"
                        :show-more="'{{ __('Show more') }}'"
                        :no-results="'{{ __('No games found for') }}'"
                        :tba="'{{ __('TBA') }}'"></global-search>
                </div>

                {{-- Row 2 (mobile) / Col 3 (desktop): nav chips + desktop locale+auth --}}
                <div class="flex items-center justify-center gap-2 xl:justify-end">
                    <div class="flex min-w-0 flex-nowrap items-center gap-2 overflow-x-auto">
                        @if($newsVisible)
                            <a href="{{ route('news-articles.default') }}" class="site-header__chip inline-flex min-h-10 items-center rounded-full px-3 text-xs font-semibold uppercase tracking-[0.08em] text-slate-100 transition xl:px-4">
                                {{ __('News') }}
                            </a>
                        @endif

                        <a href="{{ route('releases.year', ['year' => now()->year]) }}" class="site-header__chip inline-flex min-h-10 items-center rounded-full px-3 text-xs font-semibold uppercase tracking-[0.08em] text-slate-100 transition xl:px-4">
                            {{ __('Curated Lists') }}
                        </a>

                        <a href="{{ route('events') }}" class="site-header__chip inline-flex min-h-10 items-center rounded-full px-3 text-xs font-semibold uppercase tracking-[0.08em] text-slate-100 transition xl:px-4">
                            {{ __('Events') }}
                        </a>
                    </div>

                    {{-- Locale + auth: desktop only (mobile version is in Row 1) --}}
                    <div class="hidden xl:flex shrink-0 items-center gap-3 border-l border-white/10 pl-4">
                        <div x-data="{ open: false }" class="relative shrink-0" @click.outside="open = false">
                            <button
                                type="button"
                                class="inline-flex min-h-9 items-center rounded-full border border-slate-600/60 px-3 text-[0.7rem] font-bold uppercase tracking-[0.1em] text-slate-400 transition hover:border-slate-400 hover:text-slate-200"
                                @click="open = !open"
                                aria-label="Switch language">
                                {{ $headerNewsLocale->shortLabel() }}
                            </button>

                            <div
                                x-show="open"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="absolute right-0 mt-2 w-36 overflow-hidden rounded-2xl border border-cyan-300/20 bg-slate-900/95 text-sm text-slate-100 shadow-2xl"
                                style="display: none;">
                                @foreach (\App\Enums\NewsLocaleEnum::cases() as $l)
                                    <a href="{{ route('locale.switch', $l->slugPrefix()) }}"
                                       class="flex items-center gap-2 px-4 py-3 text-xs font-bold uppercase tracking-widest transition hover:bg-white/5 {{ $headerNewsLocale === $l ? 'text-orange-300' : 'text-slate-400' }}">
                                        {{ $l->shortLabel() }}
                                        @if ($headerNewsLocale === $l)
                                            <span class="ml-auto text-orange-400">✓</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        @auth
                            <div x-data="{ open: false }" class="relative shrink-0" @click.outside="open = false">
                                <button
                                    type="button"
                                    class="site-header__icon-button inline-flex h-11 w-11 items-center justify-center rounded-full text-sm font-bold uppercase"
                                    @click="open = !open"
                                    aria-label="Open account menu">
                                    {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                                </button>

                                <div
                                    x-show="open"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border border-cyan-300/20 bg-slate-900/95 text-sm text-slate-100 shadow-2xl"
                                    style="display: none;">
                                    <a href="{{ route('user.games', ['user' => auth()->user()->username]) }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-white/5">
                                        <span>{{ __('My Games') }}</span>
                                    </a>

                                    @if(auth()->user()->isAdmin())
                                        <div class="border-t border-cyan-300/10 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-400">
                                            Admin
                                        </div>
                                        <a href="{{ route('admin.system-lists') }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-white/5">
                                            <span>System Lists</span>
                                        </a>
                                        <a href="{{ route('admin.user-lists') }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-white/5">
                                            <span>User Lists</span>
                                        </a>
                                        @if($newsVisible)
                                            <a href="{{ route('admin.news-imports.index') }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-white/5">
                                                <span>News Imports</span>
                                            </a>
                                            <a href="{{ route('admin.news-articles.index') }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-white/5">
                                                <span>News Articles</span>
                                            </a>
                                        @endif
                                    @endif

                                    <div class="border-t border-cyan-300/10">
                                        <form action="{{ route('logout') }}" method="POST">
                                            @csrf
                                            <button type="submit" class="w-full px-4 py-3 text-left transition hover:bg-white/5">
                                                Logout
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div x-data class="shrink-0">
                                <button
                                    type="button"
                                    class="site-header__icon-button inline-flex h-11 w-11 items-center justify-center rounded-full"
                                    @click.prevent="$dispatch('open-modal', 'login-modal')"
                                    aria-label="Open login modal">
                                    <x-heroicon-o-user-circle class="h-5 w-5" />
                                </button>
                            </div>
                        @endauth
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div
        x-show="mobileSearchOpen"
        x-cloak
        @keydown.escape.window="mobileSearchOpen = false"
        class="xl:hidden"
        style="display: none;">
        <div
            x-show="mobileSearchOpen"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-[60] bg-black/60"
            @click="mobileSearchOpen = false"></div>

        <div
            x-show="mobileSearchOpen"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="-translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="-translate-y-full"
            class="fixed inset-x-0 top-0 z-[70] px-3 pt-3">
            <div class="site-header__bar rounded-[1.4rem]">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-sm font-semibold uppercase tracking-[0.08em] text-slate-200">Search Games</p>
                    <button type="button" class="site-header__icon-button inline-flex h-10 w-10 items-center justify-center rounded-full" @click="mobileSearchOpen = false" aria-label="Close search">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <div id="app-search-mobile">
                    <global-search
                        :placeholder="'{{ __('Search games...') }}'"
                        :searching="'{{ __('Searching...') }}'"
                        :show-more="'{{ __('Show more') }}'"
                        :no-results="'{{ __('No games found for') }}'"
                        :tba="'{{ __('TBA') }}'"></global-search>
                </div>
            </div>
        </div>
    </div>

</header>
