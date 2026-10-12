@extends('layouts.app')

@section('title', __('Unofficial PC Ports'))

@section('meta-description', __('Community static recompilations, decompilations and reimplementations of console games for PC.'))

@section('body-class', 'neon-body')

@section('content')
<div class="theme-neon overflow-x-hidden" x-data="portsTracker()" x-init="init()">
    <div class="page-shell py-10">

        {{-- Heading + metrics --}}
        <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
            <div class="min-w-0">
                <x-homepage.section-heading icon="chip" :title="__('Ports')" />
                <h1 class="mt-4 text-3xl font-extrabold uppercase tracking-wide text-slate-100 sm:text-4xl">
                    {{ __('Unofficial') }} <span class="text-orange-400">{{ __('PC ports') }}</span>
                </h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-400">
                    {{ __('Community static recompilations, decompilations and reimplementations. None of these projects distribute the games: you always need your own original copy.') }}
                </p>
            </div>

            <div class="flex flex-col gap-3">
                @if ($lastChecked)
                    <span class="self-start rounded-full border border-orange-400/35 bg-orange-400/15 px-3 py-1 font-mono text-xs text-orange-300 lg:self-end">
                        {{ __('Checked :date', ['date' => $lastChecked->format('M d, Y')]) }}
                    </span>
                @endif

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach ($stats as $stat)
                        <div @class(['flex min-w-0 flex-col gap-1 rounded-xl border px-4 py-3 font-mono text-[0.7rem] text-slate-400', $stat['metric']->tileClasses()])>
                            <b @class(['text-2xl font-semibold leading-tight tabular-nums', $stat['metric']->valueClasses()])>{{ $stat['value'] }}</b>
                            {{ __($stat['metric']->label()) }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Filter bar --}}
        <div class="neon-section-frame mb-6">
            <form class="relative flex flex-wrap items-center gap-2" role="search" @submit.prevent>
                <label class="relative min-w-0 flex-[1_1_16rem]">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                    <input type="search" x-model="q" autocomplete="off"
                           placeholder="{{ __('Search game, project or framework') }}"
                           aria-label="{{ __('Search') }}"
                           class="w-full rounded-full border border-white/10 bg-slate-900/60 py-2 pl-9 pr-4 text-xs text-slate-200 placeholder-slate-500 focus:border-cyan-400/50 focus:outline-none focus:ring-0">
                </label>

                <select x-model="platform" aria-label="{{ __('Original platform') }}"
                        class="flex-[1_1_10rem] rounded-full border border-white/10 bg-slate-900/60 py-2 pl-4 pr-9 text-xs text-slate-200 focus:border-cyan-400/50 focus:outline-none focus:ring-0 sm:flex-none">
                    <option value="">{{ __('All platforms') }}</option>
                    @foreach ($platforms as $platform)
                        <option value="{{ $platform }}">{{ $platform }}</option>
                    @endforeach
                </select>

                <select x-model="status" aria-label="{{ __('Status') }}"
                        class="flex-[1_1_10rem] rounded-full border border-white/10 bg-slate-900/60 py-2 pl-4 pr-9 text-xs text-slate-200 focus:border-cyan-400/50 focus:outline-none focus:ring-0 sm:flex-none">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}">{{ __($status->label()) }}</option>
                    @endforeach
                </select>

                <select x-model="os" aria-label="{{ __('Operating system') }}"
                        class="flex-[1_1_10rem] rounded-full border border-white/10 bg-slate-900/60 py-2 pl-4 pr-9 text-xs text-slate-200 focus:border-cyan-400/50 focus:outline-none focus:ring-0 sm:flex-none">
                    <option value="">{{ __('Any system') }}</option>
                    @foreach (\App\Models\PcPort::OPERATING_SYSTEMS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>

                <div class="flex flex-[1_1_100%] items-center gap-2 sm:flex-none">
                    <button type="button" @click="cycleSort()"
                            class="neon-btn-ghost flex-1 whitespace-nowrap rounded-full px-4 py-2 font-mono text-[0.68rem] text-slate-300 transition hover:text-slate-100 sm:flex-none"
                            title="{{ __('Change sort order') }}"
                            x-text="i18n.sort + ' · ' + i18n.sortModes[sortMode]"></button>

                    <div class="flex items-center gap-1">
                        <button type="button" @click="viewMode = 'grid'" :aria-pressed="viewMode === 'grid'"
                                :class="viewMode === 'grid' ? 'border-cyan-400/60 text-cyan-300 bg-cyan-400/10' : 'text-slate-400 hover:text-slate-200'"
                                class="neon-btn-ghost rounded-lg p-2 transition" title="{{ __('Grid') }}">
                            <x-heroicon-o-squares-2x2 class="h-4 w-4" />
                        </button>
                        <button type="button" @click="viewMode = 'list'" :aria-pressed="viewMode === 'list'"
                                :class="viewMode === 'list' ? 'border-cyan-400/60 text-cyan-300 bg-cyan-400/10' : 'text-slate-400 hover:text-slate-200'"
                                class="neon-btn-ghost rounded-lg p-2 transition" title="{{ __('List') }}">
                            <x-heroicon-o-bars-3 class="h-4 w-4" />
                        </button>
                    </div>

                    <button type="button" @click="reset()"
                            class="neon-btn-ghost rounded-lg p-2 text-slate-400 transition hover:text-slate-100"
                            title="{{ __('Clear filters') }}" aria-label="{{ __('Clear filters') }}">
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                    </button>
                </div>
            </form>
        </div>

        <p class="mb-4 font-mono text-xs font-semibold text-slate-400" aria-live="polite" x-text="countText()"></p>

        {{-- Sections --}}
        <div x-show="filtered.length > 0" class="flex flex-col gap-10">
            @foreach ($sections as $section)
                <section x-data="{ section: @js($section->value) }" aria-label="{{ __($section->label()) }}">
                    @if ($section !== \App\Enums\PcPortSectionEnum::NativePorts)
                        <h2 class="neon-section-heading__title">
                            {{ __($section->label()) }}
                            <span class="font-mono text-sm font-normal text-slate-400" x-text="inSection(section).length"></span>
                        </h2>
                        <p class="mb-4 mt-1 text-sm text-slate-400">{{ __($section->description()) }}</p>
                    @endif

                    <template x-if="inSection(section).length === 0">
                        <p class="font-mono text-xs text-slate-500">{{ __('No entries for these filters.') }}</p>
                    </template>

                    {{-- Grid view --}}
                    <div x-show="viewMode === 'grid'" class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                        <template x-for="port in inSection(section)" :key="port.slug">
                            <button type="button" @click="open(port)" class="neon-card group flex flex-col p-[7px] text-left">
                                <x-ports.cover class="aspect-[2/3] w-full rounded-[14px]" />
                                <div class="flex flex-1 flex-col gap-2 px-1 pb-1 pt-3">
                                    <h3 class="text-sm font-semibold leading-tight text-slate-100" x-text="port.title"></h3>
                                    <p class="-mt-1 break-words text-xs text-slate-400" x-text="port.project || @js(__('No public repository'))"></p>
                                    <p class="font-mono text-[0.68rem] tabular-nums text-slate-400" x-text="port.version || '—'"></p>
                                    <div class="mt-auto flex flex-wrap items-center gap-1.5">
                                        <span class="neon-platform-pill" x-text="port.platform"></span>
                                        <span class="rounded-full border px-2 py-0.5 font-mono text-[0.62rem] font-semibold" :class="port.statusClasses" x-text="port.statusLabel"></span>
                                    </div>
                                </div>
                            </button>
                        </template>
                    </div>

                    {{-- List view --}}
                    <div x-show="viewMode === 'list'" class="flex flex-col gap-1.5">
                        <template x-for="port in inSection(section)" :key="port.slug">
                            <button type="button" @click="open(port)" class="neon-card group flex items-center gap-4 p-1.5 pr-4 text-left">
                                <x-ports.cover compact class="aspect-[2/3] w-14 shrink-0 rounded-[10px]" />
                                <div class="grid min-w-0 flex-1 grid-cols-1 items-center gap-x-4 gap-y-1 md:grid-cols-[minmax(0,2fr)_minmax(0,0.7fr)_minmax(0,1fr)_minmax(0,1fr)]">
                                    <div class="min-w-0">
                                        <h3 class="truncate text-sm font-semibold text-slate-100" x-text="port.title"></h3>
                                        <p class="truncate text-xs text-slate-400" x-text="port.project || @js(__('No public repository'))"></p>
                                    </div>
                                    <p class="font-mono text-[0.68rem] tabular-nums text-slate-400" x-text="port.version || '—'"></p>
                                    <div><span class="neon-platform-pill" x-text="port.platform"></span></div>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="rounded-full border px-2 py-0.5 font-mono text-[0.62rem] font-semibold" :class="port.statusClasses" x-text="port.statusLabel"></span>
                                        <template x-if="port.flag">
                                            <span class="neon-category-pill neon-category-pill--inline" x-text="port.flag"></span>
                                        </template>
                                    </div>
                                </div>
                                <x-heroicon-o-chevron-right class="h-5 w-5 shrink-0 text-slate-500 transition group-hover:translate-x-0.5 group-hover:text-cyan-300" />
                            </button>
                        </template>
                    </div>
                </section>
            @endforeach
        </div>

        {{-- Empty state --}}
        <div x-show="filtered.length === 0" x-cloak class="rounded-2xl border border-dashed border-white/15 px-6 py-14 text-center text-sm text-slate-400">
            <b class="mb-1 block text-base text-slate-100">{{ __('No ports found') }}</b>
            {{ __('Try another search or clear the filters.') }}
        </div>

        <p class="mt-10 text-xs text-slate-500">
            {{ __('Curated list of unofficial ports. Covers via SteamGridDB; a typographic cover is shown when no image is available.') }}
        </p>
    </div>

    {{-- Detail modal --}}
    <x-modal name="port-detail" maxWidth="2xl">
        <template x-if="selected">
            <div x-data="{ get port() { return selected } }" class="relative max-h-[80vh] overflow-y-auto bg-[#151a2b] p-5 text-slate-100 sm:p-6">
                <button type="button" @click="$dispatch('close-modal', 'port-detail')"
                        class="neon-btn-ghost absolute right-3 top-3 z-10 rounded-lg p-2 text-slate-300 hover:text-white"
                        aria-label="{{ __('Close') }}">
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                </button>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-[170px_minmax(0,1fr)]">
                    <x-ports.cover class="aspect-[2/3] w-40 rounded-[14px] sm:w-full" />

                    <div class="min-w-0">
                        <h2 class="pr-10 text-2xl font-extrabold uppercase leading-none" x-text="port.title"></h2>

                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <span class="rounded-full border px-2 py-0.5 font-mono text-[0.62rem] font-semibold" :class="port.statusClasses" x-text="port.statusLabel"></span>
                            <template x-if="port.technique">
                                <span class="rounded-md border border-white/10 px-2 py-0.5 font-mono text-[0.62rem] text-slate-400" x-text="port.technique"></span>
                            </template>
                            <template x-if="port.flag">
                                <span class="rounded-md border border-white/10 px-2 py-0.5 font-mono text-[0.62rem] text-slate-400" x-text="port.flag + ' · ' + i18n.notNative"></span>
                            </template>
                        </div>

                        <p class="my-3 text-sm text-slate-300" x-text="port.notes"></p>

                        <dl class="grid grid-cols-[max-content_minmax(0,1fr)] gap-x-4 gap-y-1.5 text-sm">
                            <dt class="pt-0.5 font-mono text-[0.65rem] uppercase tracking-[0.06em] text-slate-400">{{ __('Systems') }}</dt>
                            <dd class="flex flex-wrap gap-1.5">
                                <template x-for="system in port.os" :key="system.label">
                                    <span class="rounded-full border px-2 py-0.5 font-mono text-[0.62rem] font-semibold" :class="system.classes" :title="system.stateLabel"
                                          x-text="system.state === 'experimental' ? system.label + ' · ' + system.stateLabel.toLowerCase() : system.label"></span>
                                </template>
                            </dd>
                            <template x-for="row in port.rows" :key="row.label">
                                <div class="contents">
                                    <dt class="pt-0.5 font-mono text-[0.65rem] uppercase tracking-[0.06em] text-slate-400" x-text="row.label"></dt>
                                    <dd class="break-words" x-text="row.value"></dd>
                                </div>
                            </template>
                        </dl>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <template x-for="link in port.links" :key="link.url">
                                <a :href="link.url" target="_blank" rel="noopener" :class="link.primary ? 'neon-btn' : 'neon-btn-ghost'" class="!py-2" x-text="link.label"></a>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </x-modal>
</div>

@push('scripts')
<script>
    function portsTracker() {
        const storageKey = 'ports_tracker_filters';
        const sortModes = ['recent', 'az', 'za'];

        return {
            ports: @js($ports),
            i18n: @js([
                'sort' => __('Sort'),
                'sortModes' => ['recent' => __('Recent'), 'az' => 'A–Z', 'za' => 'Z–A'],
                'count' => __(':shown of :total entries · sorted by :order'),
                'orders' => ['recent' => __('recent activity'), 'az' => 'A–Z', 'za' => 'Z–A'],
                'notNative' => __('not a native port'),
            ]),
            q: '',
            platform: '',
            status: '',
            os: '',
            sortMode: 'recent',
            viewMode: 'grid',
            selected: null,

            init() {
                try {
                    const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
                    if (saved) {
                        this.q = saved.q || '';
                        this.platform = saved.platform || '';
                        this.status = saved.status || '';
                        this.os = saved.os || '';
                        this.sortMode = sortModes.includes(saved.sortMode) ? saved.sortMode : 'recent';
                        this.viewMode = ['grid', 'list'].includes(saved.viewMode) ? saved.viewMode : 'grid';
                    }
                } catch (e) {}

                ['q', 'platform', 'status', 'os', 'sortMode', 'viewMode'].forEach(key => this.$watch(key, () => this.persist()));
            },

            persist() {
                try {
                    localStorage.setItem(storageKey, JSON.stringify({
                        q: this.q, platform: this.platform, status: this.status, os: this.os, sortMode: this.sortMode, viewMode: this.viewMode,
                    }));
                } catch (e) {}
            },

            get filtered() {
                const q = this.q.trim().toLowerCase();
                const byTitle = (a, b) => a.title.localeCompare(b.title, undefined, { sensitivity: 'base' });
                const compare = {
                    az: byTitle,
                    za: (a, b) => byTitle(b, a),
                    recent: (a, b) => b.activity.localeCompare(a.activity) || byTitle(a, b),
                }[this.sortMode];

                return this.ports
                    .filter(port => (!q || port.haystack.includes(q))
                        && (!this.platform || port.platforms.includes(this.platform))
                        && (!this.status || port.status === this.status)
                        && (!this.os || port.supports.includes(this.os)))
                    .sort(compare);
            },

            inSection(section) {
                return this.filtered.filter(port => port.section === section);
            },

            countText() {
                return this.i18n.count
                    .replace(':shown', this.filtered.length)
                    .replace(':total', this.ports.length)
                    .replace(':order', this.i18n.orders[this.sortMode]);
            },

            cycleSort() {
                this.sortMode = sortModes[(sortModes.indexOf(this.sortMode) + 1) % sortModes.length];
            },

            reset() {
                this.q = '';
                this.platform = '';
                this.status = '';
                this.os = '';
            },

            open(port) {
                this.selected = port;
                this.$dispatch('open-modal', 'port-detail');
            },
        };
    }
</script>
@endpush
@endsection
