@props(['summary'])

<div class="space-y-2">
    @php $p = $summary->primary; @endphp
    <div>
        <p class="text-xs font-bold uppercase tracking-[0.12em] {{ $p->variant->colorClass() }}">{{ $p->label }}</p>
        @if($p->description !== '')
            <p class="text-sm text-slate-200">{{ $p->description }}</p>
        @endif
    </div>

    @if($summary->secondary)
        @php $s = $summary->secondary; @endphp
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.12em] {{ $s->variant->colorClass() }}">{{ $s->label }}</p>
            @if($s->description !== '')
                <p class="text-sm text-slate-400">{{ $s->description }}</p>
            @endif
        </div>
    @endif

    @if($summary->note)
        <p class="text-[0.7rem] text-slate-500">{{ $summary->note }}</p>
    @endif
</div>
