@extends('layouts.app')

@section('title', 'News Import Detail (Admin)')

@section('content')
    <div class="page-shell py-8">
        <div class="flex items-center gap-4 mb-8">
            <a href="{{ route('admin.news-imports.index') }}"
               class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                &larr; Imports
            </a>
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">Import Detail</h1>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $import->status->colorClass() }}">
                    {{ $import->status->label() }}
                </span>
            </div>

            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">URL</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100 break-all">{{ $import->url }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Source</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $import->source_domain ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Imported By</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $import->user?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Created</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $import->created_at->format('Y-m-d H:i') }}</dd>
                </div>
                @if ($import->failure_reason)
                    <div class="col-span-2">
                        <dt class="font-medium text-red-500">Failure Reason</dt>
                        <dd class="mt-1 text-red-700 dark:text-red-400">{{ $import->failure_reason }}</dd>
                    </div>
                @endif
                @if ($import->raw_title)
                    <div class="col-span-2">
                        <dt class="font-medium text-gray-500 dark:text-gray-400">Extracted Title</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $import->raw_title }}</dd>
                    </div>
                @endif
            </dl>

            @if ($import->article)
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('admin.news-articles.edit', $import->article) }}"
                       class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
                        View Generated Article &rarr;
                    </a>
                </div>
            @endif
        </div>
    </div>
@endsection
