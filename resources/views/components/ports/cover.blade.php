@props(['compact' => false])

{{-- Expects an Alpine `port` in scope (a PcPort::toTrackerArray() entry). --}}
<div {{ $attributes->class('relative overflow-hidden bg-slate-900') }}>
    <template x-if="!port.cover">
        <div @class([
            'absolute inset-0 flex flex-col bg-gradient-to-br from-slate-700 via-slate-800 to-violet-950 text-left text-slate-100',
            'items-center justify-center p-2' => $compact,
            'justify-between p-3' => ! $compact,
        ])>
            @if ($compact)
                <img src="{{ asset('images/game-controller.svg') }}" alt="" class="h-8 w-8 opacity-60">
            @else
                <span class="font-mono text-[0.6rem] uppercase tracking-[0.12em] text-cyan-200/80" x-text="port.platform"></span>
                <span class="line-clamp-4 break-words text-lg font-extrabold uppercase leading-none" x-text="port.title"></span>
                <span class="truncate font-mono text-[0.6rem] text-slate-400" x-text="port.project || @js(__('No repository'))"></span>
            @endif
        </div>
    </template>

    <template x-if="port.cover">
        <img :src="port.cover" :alt="port.title" class="absolute inset-0 h-full w-full object-cover" loading="lazy" decoding="async" x-on:error="port.cover = null">
    </template>

    @unless ($compact)
        <template x-if="port.flag">
            <span class="neon-category-pill neon-category-pill--corner" x-text="port.flag"></span>
        </template>
    @endunless
</div>
