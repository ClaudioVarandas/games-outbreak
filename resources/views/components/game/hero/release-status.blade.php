@props(['summary'])

@php
    $lines = collect([$summary->primary, $summary->secondary])->filter();
@endphp

<div class="space-y-3">
    @foreach($lines as $line)
        <div class="flex items-center gap-3">
            @svg('heroicon-o-'.$line->variant->icon(), 'h-10 w-10 shrink-0 '.$line->variant->colorClass())
            <span class="h-9 w-[3px] shrink-0 rounded-full {{ $line->variant->barClass() }}"></span>
            <div class="min-w-0">
                <p class="text-base font-bold {{ $line->variant->colorClass() }}">{{ $line->label }}</p>
                @if($line->description !== '')
                    <p class="text-sm text-slate-200">{{ $line->description }}</p>
                @endif
            </div>
        </div>
    @endforeach

    @if($summary->note)
        <p class="text-[0.7rem] text-slate-500">{{ $summary->note }}</p>
    @endif
</div>
