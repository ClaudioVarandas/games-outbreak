@extends('layouts.app')

@section('title', 'Discovery Sources (Admin)')

@section('content')
<div class="page-shell py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">Discovery Sources</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Registry of news / release sweep sources. Agents propose, you confirm; yield comes from your own promote/reject decisions.
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    {{-- Paste box --}}
    <div class="mb-8 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-1">Throw links here</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            One URL per line. YouTube channels and X profiles are classified instantly; anything ambiguous lands as pending
            with a <span class="font-medium">needs enrichment</span> flag — run <code class="text-xs">/add-source</code> to let the agent research it.
        </p>
        <form method="POST" action="{{ route('admin.discovery-sources.intake') }}" class="space-y-4">
            @csrf
            <textarea
                name="links"
                rows="3"
                required
                placeholder="https://www.youtube.com/@somechannel&#10;https://x.com/someaccount&#10;https://somesite.com/upcoming-games/"
                class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"></textarea>
            @error('links')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
            <div class="flex items-center gap-4">
                <select name="purpose" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                    @foreach(\App\Enums\DiscoverySourcePurposeEnum::cases() as $purpose)
                        <option value="{{ $purpose->value }}" @selected($purpose === \App\Enums\DiscoverySourcePurposeEnum::Both)>{{ $purpose->label() }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded">
                    Add as pending
                </button>
            </div>
        </form>
    </div>

    {{-- Pending block --}}
    @if($pendingSources->isNotEmpty())
        <div class="mb-8 bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden border-2 border-amber-300 dark:border-amber-500/40">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
                    Pending review
                    <span class="ml-2 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ \App\Enums\DiscoverySourceStatusEnum::Pending->badgeClass() }}">
                        {{ $pendingSources->count() }}
                    </span>
                </h2>
            </div>
            <form method="POST" action="{{ route('admin.discovery-sources.bulk') }}" x-data="{ selected: [] }">
                @csrf
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left">
                                    <input type="checkbox"
                                        class="rounded border-gray-300 dark:border-gray-600"
                                        @change="selected = $event.target.checked ? {{ $pendingSources->pluck('id') }} : []">
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Source</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Kind / Purpose</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Evidence</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($pendingSources as $source)
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <input type="checkbox" name="source_ids[]" value="{{ $source->id }}" x-model="selected"
                                            class="rounded border-gray-300 dark:border-gray-600">
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <div class="font-medium text-gray-800 dark:text-gray-100">{{ $source->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            @if($source->url)
                                                <a href="{{ $source->url }}" target="_blank" rel="noopener" class="hover:underline">{{ $source->locator }}</a>
                                            @else
                                                {{ $source->locator }}
                                            @endif
                                        </div>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $source->origin->badgeClass() }}">{{ $source->origin->label() }}</span>
                                            @if($source->confidence)
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ \App\Enums\ImportConfidenceEnum::from($source->confidence)->badgeClass() }}">{{ \App\Enums\ImportConfidenceEnum::from($source->confidence)->label() }}</span>
                                            @endif
                                            @if($source->needs_enrichment)
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/30">Needs enrichment</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <div class="flex flex-wrap gap-1">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $source->kind->badgeClass() }}">{{ $source->kind->label() }}</span>
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $source->purpose->badgeClass() }}">{{ $source->purpose->label() }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 align-top text-sm text-gray-600 dark:text-gray-300 max-w-md">
                                        {{ $source->evidence ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3 align-top text-right whitespace-nowrap">
                                        <button type="submit" form="confirm-source-{{ $source->id }}"
                                            class="text-sm font-medium text-green-600 hover:text-green-700 dark:text-green-400">Confirm</button>
                                        <button type="submit" form="reject-source-{{ $source->id }}"
                                            class="ml-3 text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400">Reject</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center gap-3"
                    x-show="selected.length > 0" x-cloak>
                    <span class="text-sm text-gray-600 dark:text-gray-300"><span x-text="selected.length"></span> selected</span>
                    <button type="submit" name="action" value="confirm"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded">Confirm selected</button>
                    <button type="submit" name="action" value="reject"
                        class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded">Reject selected</button>
                </div>
            </form>
            @foreach($pendingSources as $source)
                <form id="confirm-source-{{ $source->id }}" method="POST" action="{{ route('admin.discovery-sources.confirm', $source) }}">@csrf</form>
                <form id="reject-source-{{ $source->id }}" method="POST" action="{{ route('admin.discovery-sources.reject', $source) }}" data-confirm="Reject this source?">@csrf</form>
            @endforeach
        </div>
    @endif

    {{-- Active table --}}
    <div class="mb-8 bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Active sources ({{ $activeSources->count() }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Source</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Kind / Purpose</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Runs</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Found</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Promoted</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Rejected</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Score</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($activeSources as $source)
                        <tr>
                            <td class="px-4 py-3 align-top">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-800 dark:text-gray-100">{{ $source->name }}</span>
                                    @if($source->looksDead())
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/30">Low yield — disable?</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    @if($source->url)
                                        <a href="{{ $source->url }}" target="_blank" rel="noopener" class="hover:underline">{{ $source->locator }}</a>
                                    @else
                                        {{ $source->locator }}
                                    @endif
                                </div>
                                <details class="mt-1">
                                    <summary class="cursor-pointer text-xs text-blue-600 hover:underline dark:text-blue-400">Edit</summary>
                                    <form method="POST" action="{{ route('admin.discovery-sources.update', $source) }}" class="mt-2 flex flex-wrap items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="name" value="{{ $source->name }}" required
                                            class="rounded border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                                        <select name="purpose" class="rounded border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                                            @foreach(\App\Enums\DiscoverySourcePurposeEnum::cases() as $purpose)
                                                <option value="{{ $purpose->value }}" @selected($source->purpose === $purpose)>{{ $purpose->label() }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium px-3 py-1.5 rounded">Save</button>
                                    </form>
                                </details>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="flex flex-wrap gap-1">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $source->kind->badgeClass() }}">{{ $source->kind->label() }}</span>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $source->purpose->badgeClass() }}">{{ $source->purpose->label() }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 align-top text-right text-sm text-gray-600 dark:text-gray-300">{{ $source->runs_count }}</td>
                            <td class="px-4 py-3 align-top text-right text-sm text-gray-600 dark:text-gray-300">{{ $source->items_found_total }}</td>
                            <td class="px-4 py-3 align-top text-right text-sm text-green-600 dark:text-green-400">{{ $source->items_promoted }}</td>
                            <td class="px-4 py-3 align-top text-right text-sm text-red-600 dark:text-red-400">{{ $source->items_rejected }}</td>
                            <td class="px-4 py-3 align-top text-right text-sm font-medium text-gray-800 dark:text-gray-100">
                                {{ $source->score() !== null ? number_format($source->score() * 100).'%' : '—' }}
                            </td>
                            <td class="px-4 py-3 align-top text-right whitespace-nowrap">
                                <button type="submit" form="disable-source-{{ $source->id }}"
                                    class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400">Disable</button>
                                <button type="submit" form="delete-source-{{ $source->id }}"
                                    class="ml-3 text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No active sources yet. Seed the registry or add links above.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @foreach($activeSources as $source)
        <form id="disable-source-{{ $source->id }}" method="POST" action="{{ route('admin.discovery-sources.disable', $source) }}">@csrf</form>
        <form id="delete-source-{{ $source->id }}" method="POST" action="{{ route('admin.discovery-sources.destroy', $source) }}" data-confirm="Delete this source? Its yield history is lost.">@csrf @method('DELETE')</form>
    @endforeach

    {{-- Disabled / rejected --}}
    @if($inactiveSources->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Disabled &amp; rejected ({{ $inactiveSources->count() }})</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($inactiveSources as $source)
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-800 dark:text-gray-100">{{ $source->name }}</span>
                                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">{{ $source->locator }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $source->status->badgeClass() }}">{{ $source->status->label() }}</span>
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $source->kind->badgeClass() }}">{{ $source->kind->label() }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button type="submit" form="enable-source-{{ $source->id }}"
                                        class="text-sm font-medium text-green-600 hover:text-green-700 dark:text-green-400">
                                        {{ $source->status === \App\Enums\DiscoverySourceStatusEnum::Rejected ? 'Approve anyway' : 'Enable' }}
                                    </button>
                                    <button type="submit" form="delete-inactive-source-{{ $source->id }}"
                                        class="ml-3 text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @foreach($inactiveSources as $source)
            <form id="enable-source-{{ $source->id }}" method="POST" action="{{ route('admin.discovery-sources.enable', $source) }}">@csrf</form>
            <form id="delete-inactive-source-{{ $source->id }}" method="POST" action="{{ route('admin.discovery-sources.destroy', $source) }}" data-confirm="Delete this source?">@csrf @method('DELETE')</form>
        @endforeach
    @endif
</div>
@endsection
