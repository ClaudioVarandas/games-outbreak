@extends('layouts.app')

@section('title', 'Edit News Article (Admin)')

@section('content')
    <div class="page-shell py-8">

        {{-- Header --}}
        <div class="mb-6">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Edit Article</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $article->status->colorClass() }}">
                    {{ $article->status->label() }}
                </span>
            </div>
            <a href="{{ route('admin.news-articles.index') }}"
               class="mt-1 inline-block text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                &larr; Articles
            </a>
        </div>

        @if (session('success'))
            <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded">
                {{ session('success') }}
            </div>
        @endif

        {{-- Shared Alpine scope: image uploader state + tab state --}}
        <div x-data="{
            tab: '{{ $locales[0]->value }}',
            savedUrl: {{ Js::from($article->featured_image_url) }},
            localPreview: null,
            uploading: false,
            removing: false,
            uploadError: null,
            dragOver: false,
            get previewUrl() { return this.localPreview ?? this.savedUrl; },
            csrfToken() { return document.querySelector('meta[name=csrf-token]').content; },
            async uploadFile(file) {
                if (!file) return;
                if (!file.type.startsWith('image/')) {
                    this.uploadError = 'Only image files are allowed.';
                    return;
                }
                this.uploadError = null;
                this.localPreview = URL.createObjectURL(file);
                this.uploading = true;
                const data = new FormData();
                data.append('image', file);
                try {
                    const res = await fetch('{{ route('admin.news-articles.upload-image', $article) }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.csrfToken() },
                        body: data,
                    });
                    if (!res.ok) throw new Error();
                    const json = await res.json();
                    this.savedUrl = json.url;
                    this.localPreview = null;
                } catch {
                    this.localPreview = null;
                    this.uploadError = 'Upload failed. Try again.';
                } finally {
                    this.uploading = false;
                }
            },
            async removeImage() {
                this.removing = true;
                try {
                    await fetch('{{ route('admin.news-articles.featured-image.destroy', $article) }}', {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': this.csrfToken() },
                    });
                    this.savedUrl = null;
                    this.localPreview = null;
                } catch {
                    // silent — image still shows
                } finally {
                    this.removing = false;
                }
            }
        }">

        {{-- Row 1: two side-by-side columns --}}
        <div class="flex gap-4 mb-8">

            {{-- Col 1 (wider): source info + featured image --}}
            <div class="flex-1 min-w-0">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 space-y-4 text-sm h-full">

                    <div class="space-y-2">
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Source:</span>
                            @if ($article->source_url)
                                <a href="{{ $article->source_url }}" target="_blank" rel="noopener"
                                   class="ml-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                    {{ $article->source_name }} &rarr;
                                </a>
                            @else
                                <span class="ml-1 text-gray-900 dark:text-gray-100">{{ $article->source_name }}</span>
                            @endif
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Original Title:</span>
                            <p class="mt-0.5 text-gray-900 dark:text-gray-100">{{ $article->original_title }}</p>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 dark:border-gray-700 pt-4 space-y-3">
                        <h2 class="font-semibold text-gray-800 dark:text-gray-100">Featured Image</h2>

                        {{-- Drop zone --}}
                        <div
                            @dragover.prevent="dragOver = true"
                            @dragleave.prevent="dragOver = false"
                            @drop.prevent="dragOver = false; uploadFile($event.dataTransfer.files[0])"
                            :class="dragOver ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-600'"
                            class="relative rounded-lg border-2 border-dashed transition-colors cursor-pointer overflow-hidden min-h-[80px]"
                            @click="$refs.fileInput.click()">

                            <img x-show="previewUrl" :src="previewUrl" alt=""
                                 class="w-full rounded-lg object-cover max-h-48">

                            <div x-show="!previewUrl"
                                 class="flex flex-col items-center justify-center py-8 text-gray-400 dark:text-gray-500 text-sm gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                                </svg>
                                <span>Drop image here or click to upload</span>
                            </div>

                            <div x-show="uploading"
                                 class="absolute inset-0 flex items-center justify-center bg-white/80 dark:bg-gray-800/80 pointer-events-none">
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Uploading…</span>
                            </div>

                            <input type="file" accept="image/*" x-ref="fileInput" class="hidden"
                                   @click.stop
                                   @change="uploadFile($event.target.files[0]); $event.target.value = ''">
                        </div>

                        <p x-show="uploadError" x-text="uploadError" class="text-xs text-red-500 dark:text-red-400"></p>

                        <div x-show="savedUrl" class="flex justify-end">
                            <button type="button" @click="removeImage()"
                                    :disabled="removing"
                                    class="text-xs text-red-500 hover:text-red-700 disabled:opacity-50">
                                <span x-text="removing ? 'Removing…' : 'Remove image'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Col 2 (narrower): actions --}}
            <div class="w-72 shrink-0">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 space-y-3">
                    <h2 class="font-semibold text-gray-800 dark:text-gray-100 text-sm">Actions</h2>

                    <form method="POST" action="{{ route('admin.news-articles.publish', $article) }}" class="space-y-2">
                        @csrf
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="should_broadcast" value="1"
                                   {{ old('should_broadcast', $article->should_broadcast) ? 'checked' : '' }}
                                   class="rounded border-gray-300 dark:border-gray-600">
                            <span>Broadcast to Telegram</span>
                        </label>
                        @if ($article->broadcasted_at)
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Already broadcast {{ $article->broadcasted_at->diffForHumans() }}.
                            </p>
                        @endif
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

        </div>

        {{-- Row 2: Localization tabs --}}
        <form method="POST" action="{{ route('admin.news-articles.update', $article) }}">
            @csrf
            @method('PATCH')

            {{-- Tab nav --}}
            <div class="flex border-b border-gray-200 dark:border-gray-700 mb-4">
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
                @php
                    $loc = $article->localization($locale->value);
                    $currentSlug = $article->{$locale->slugColumn()};
                @endphp
                <div x-show="tab === '{{ $locale->value }}'" x-cloak class="space-y-4">
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
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Slug
                            @if ($currentSlug)
                                <span class="text-xs font-normal text-gray-400 ml-1">(leave blank to keep current)</span>
                            @else
                                <span class="text-xs font-normal text-orange-500 ml-1">(auto-generated on save if blank)</span>
                            @endif
                        </label>
                        <input type="text"
                               name="localizations[{{ $i }}][slug]"
                               value="{{ old("localizations.{$i}.slug", $currentSlug) }}"
                               placeholder="{{ $currentSlug ?? 'e.g. game-title-announcement' }}"
                               class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm font-mono text-sm">
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

        </div>{{-- end shared Alpine scope --}}

    </div>
@endsection