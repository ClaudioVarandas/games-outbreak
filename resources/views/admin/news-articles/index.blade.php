@extends('layouts.app')

@section('title', 'News Articles (Admin)')

@section('content')
    <div class="page-shell py-8">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">News Articles</h1>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.news-articles.index') }}" class="flex gap-4 mb-6">
            <select name="status" class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm text-sm">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
            <input type="text" name="source" value="{{ request('source') }}"
                   placeholder="Filter by source…"
                   class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm text-sm">
            <button type="submit" class="bg-gray-700 hover:bg-gray-900 text-white text-sm py-1.5 px-4 rounded">Filter</button>
            <a href="{{ route('admin.news-articles.index') }}" class="text-gray-500 hover:text-gray-700 text-sm py-1.5 px-2">Reset</a>
        </form>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-3 py-3 w-20"></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Original Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Source</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($articles as $article)
                        <tr>
                            <td class="px-3 py-2 w-20">
                                @if ($article->featured_image_url)
                                    <img src="{{ $article->featured_image_url }}" alt=""
                                         class="h-14 w-20 rounded object-cover">
                                @else
                                    <div class="h-14 w-20 rounded bg-gray-100 dark:bg-gray-700"></div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100 max-w-xs truncate">{{ $article->original_title }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $article->source_name }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $article->status->colorClass() }}">
                                    {{ $article->status->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $article->created_at->diffForHumans() }}</td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.news-articles.edit', $article) }}"
                                   class="text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No articles yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $articles->links() }}</div>
    </div>
@endsection
