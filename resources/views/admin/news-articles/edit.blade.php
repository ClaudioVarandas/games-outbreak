@extends('layouts.app')

@section('title', 'Edit News Article (Admin)')

@section('content')
    <div class="container mx-auto px-4 py-8 max-w-5xl">
        <div class="flex items-center gap-4 mb-8">
            <a href="{{ route('admin.news-articles.index') }}"
               class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                &larr; Articles
            </a>
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">Edit Article</h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $article->status->colorClass() }}">
                {{ $article->status->label() }}
            </span>
        </div>

        @if (session('success'))
            <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Left: Source info --}}
            <div class="space-y-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-sm space-y-3">
                    <h2 class="font-semibold text-gray-800 dark:text-gray-100">Source Info</h2>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Source:</span>
                        <span class="ml-1 text-gray-900 dark:text-gray-100">{{ $article->source_name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Original Title:</span>
                        <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $article->original_title }}</p>
                    </div>
                    @if ($article->source_url)
                        <div>
                            <a href="{{ $article->source_url }}" target="_blank" rel="noopener"
                               class="text-blue-600 hover:text-blue-800 break-all">View Source &rarr;</a>
                        </div>
                    @endif
                </div>

                {{-- Featured image --}}
                <form method="POST" action="{{ route('admin.news-articles.update', $article) }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 space-y-3">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="localizations" value="">
                    <h2 class="font-semibold text-gray-800 dark:text-gray-100 text-sm">Featured Image</h2>
                    @if ($article->featured_image_url)
                        <img src="{{ $article->featured_image_url }}" alt="" class="w-full rounded">
                    @endif
                    <input type="url" name="featured_image_url" value="{{ old('featured_image_url', $article->featured_image_url) }}"
                           placeholder="https://…"
                           class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm">
                </form>

                {{-- Publish / Schedule actions --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 space-y-3">
                    <h2 class="font-semibold text-gray-800 dark:text-gray-100 text-sm">Actions</h2>

                    <form method="POST" action="{{ route('admin.news-articles.publish', $article) }}">
                        @csrf
                        <button type="submit"
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded text-sm">
                            Publish Now
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.news-articles.schedule', $article) }}" class="space-y-2">
                        @csrf
                        <input type="datetime-local" name="scheduled_at"
                               class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm">
                        <button type="submit"
                                class="w-full bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-4 rounded text-sm">
                            Schedule
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.news-articles.destroy', $article) }}"
                          onsubmit="return confirm('Delete this article?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded text-sm">
                            Delete
                        </button>
                    </form>
                </div>
            </div>

            {{-- Right: Localization tabs --}}
            <div class="lg:col-span-2">
                <form method="POST" action="{{ route('admin.news-articles.update', $article) }}">
                    @csrf
                    @method('PATCH')

                    {{-- Tab nav --}}
                    <div class="flex border-b border-gray-200 dark:border-gray-700 mb-4" x-data="{ tab: '{{ $locales[0]->value }}' }">
                        @foreach ($locales as $locale)
                            <button type="button"
                                    @click="tab = '{{ $locale->value }}'"
                                    :class="tab === '{{ $locale->value }}' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                                    class="py-2 px-4 border-b-2 font-medium text-sm transition-colors">
                                {{ $locale->label() }}
                            </button>
                        @endforeach

                        <div class="ml-auto">
                            <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded text-sm">
                                Save
                            </button>
                        </div>
                    </div>

                    @foreach ($locales as $i => $locale)
                        @php $loc = $article->localization($locale->value); @endphp
                        <div x-show="tab === '{{ $locale->value }}'" x-data="{ tab: '{{ $locales[0]->value }}' }" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                                <input type="text"
                                       name="localizations[{{ $i }}][locale]"
                                       value="{{ $locale->value }}"
                                       hidden>
                                <input type="text"
                                       name="localizations[{{ $i }}][title]"
                                       value="{{ old("localizations.{$i}.title", $loc?->title) }}"
                                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Summary Short (max 160)</label>
                                <textarea name="localizations[{{ $i }}][summary_short]"
                                          rows="2"
                                          maxlength="160"
                                          class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm">{{ old("localizations.{$i}.summary_short", $loc?->summary_short) }}</textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Summary Medium (max 400)</label>
                                <textarea name="localizations[{{ $i }}][summary_medium]"
                                          rows="4"
                                          maxlength="400"
                                          class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm">{{ old("localizations.{$i}.summary_medium", $loc?->summary_medium) }}</textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SEO Title (max 70)</label>
                                <input type="text"
                                       name="localizations[{{ $i }}][seo_title]"
                                       value="{{ old("localizations.{$i}.seo_title", $loc?->seo_title) }}"
                                       maxlength="70"
                                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SEO Description (max 160)</label>
                                <textarea name="localizations[{{ $i }}][seo_description]"
                                          rows="2"
                                          maxlength="160"
                                          class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm">{{ old("localizations.{$i}.seo_description", $loc?->seo_description) }}</textarea>
                            </div>
                        </div>
                    @endforeach
                </form>
            </div>
        </div>
    </div>
@endsection
