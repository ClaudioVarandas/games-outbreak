@props(['summary'])

@php
    $lines = collect([$summary->primary, $summary->secondary])->filter();
@endphp

<div class="space-y-3">
    @foreach($lines as $line)
        <div class="flex items-center gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 {{ $line->variant->ringClass() }}">
                @svg('heroicon-o-'.$line->variant->icon(), 'h-5 w-5 '.$line->variant->colorClass())
            </span>
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
