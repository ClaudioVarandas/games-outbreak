@php
    $percentage = ($max > 0 && isset($showBar) && $showBar) ? round(($value / $max) * 100) : null;
@endphp
<div class="bg-black/40 backdrop-blur-sm rounded-lg px-3 py-2 min-w-[4.5rem]">
    <div class="flex items-center gap-1.5 mb-0.5">
        <svg class="w-3.5 h-3.5 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/>
        </svg>
        <span class="text-sm font-bold text-white">{{ $value }}</span>
    </div>
    <span class="text-[10px] text-gray-400 uppercase tracking-wide">{{ $label }}</span>
    @if($percentage !== null)
        <div class="mt-1 h-1 bg-white/10 rounded-full overflow-hidden">
            <div class="h-full rounded-full {{ $barColor }} transition-all duration-500 ease-out" style="width: 0%" data-target-width="{{ $percentage }}%"></div>
        </div>
        <span class="text-[9px] text-gray-500 mt-0.5 block">{{ $percentage }}%</span>
    @endif
</div>
