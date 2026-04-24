@extends('layouts.app')

@section('title', 'News Imports (Admin)')

@section('content')
    @php
        $pollIds = $imports->getCollection()->pluck('id')->values()->all();
    @endphp

    <div class="page-shell py-8"
         x-data="newsImportPoller(@js(['url' => route('admin.news-imports.statuses'), 'ids' => $pollIds]))"
         x-init="start()">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">News Imports</h1>
            <a href="{{ route('admin.news-imports.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
                Import URL
            </a>
        </div>

        @if (session('success'))
            <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">URL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Source</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Imported By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($imports as $import)
                        <tr data-import-id="{{ $import->id }}">
                            <td class="px-6 py-4">
                                <span data-role="status-badge"
                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $import->status->colorClass() }}">
                                    {{ $import->status->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100 max-w-xs truncate">
                                {{ $import->url }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $import->source_domain }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $import->user?->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $import->created_at->diffForHumans() }}</td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-3">
                                    <form action="{{ route('admin.news-imports.retry', $import) }}"
                                          method="POST"
                                          data-role="retry-form"
                                          @unless ($import->isFailed()) hidden @endunless>
                                        @csrf
                                        <button type="submit"
                                                class="text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300 text-sm font-medium">
                                            Retry
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.news-imports.show', $import) }}"
                                       class="text-blue-600 hover:text-blue-800 text-sm">View</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No imports yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $imports->links() }}</div>
    </div>

    <script>
        function newsImportPoller({ url, ids }) {
            return {
                ids,
                timer: null,
                start() {
                    if (!this.ids || this.ids.length === 0) {
                        return;
                    }
                    this.tick();
                    this.timer = setInterval(() => this.tick(), 3000);
                },
                async tick() {
                    const params = new URLSearchParams();
                    this.ids.forEach((id) => params.append('ids[]', id));
                    let res;
                    try {
                        res = await fetch(`${url}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                    } catch {
                        return;
                    }
                    if (!res.ok) {
                        return;
                    }
                    const data = await res.json();
                    let allFinal = true;
                    Object.entries(data).forEach(([id, row]) => {
                        const tr = this.$el.querySelector(`[data-import-id="${id}"]`);
                        if (!tr) {
                            return;
                        }
                        const badge = tr.querySelector('[data-role="status-badge"]');
                        if (badge) {
                            badge.textContent = row.label;
                            badge.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${row.color_class}`;
                        }
                        const retryForm = tr.querySelector('[data-role="retry-form"]');
                        if (retryForm) {
                            retryForm.hidden = !row.is_failed;
                        }
                        if (!row.is_final) {
                            allFinal = false;
                        }
                    });
                    if (allFinal && this.timer) {
                        clearInterval(this.timer);
                        this.timer = null;
                    }
                },
            };
        }
    </script>
@endsection
