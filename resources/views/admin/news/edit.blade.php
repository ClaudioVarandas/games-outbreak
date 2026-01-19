@extends('layouts.app')

@section('title', 'Edit Article - ' . $news->title)

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <a href="{{ route('admin.news.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-orange-500 mb-2 inline-block">
                        &larr; Back to News
                    </a>
                    <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                        Edit Article
                    </h1>
                </div>
                @if($news->isPublished())
                    <a href="{{ route('news.show', $news) }}" target="_blank"
                       class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                        View Article
                    </a>
                @endif
            </div>

            @if(session('success'))
                <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Article Form -->
            <form action="{{ route('admin.news.update', $news) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6" id="articleForm">
                @csrf
                @method('PATCH')

                <div class="space-y-6">
                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title</label>
                        <input type="text"
                               id="title"
                               name="title"
                               value="{{ old('title', $news->title) }}"
                               required
                               class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>

                    <!-- Slug -->
                    <div>
                        <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Slug</label>
                        <input type="text"
                               id="slug"
                               name="slug"
                               value="{{ old('slug', $news->slug) }}"
                               class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>

                    <!-- Summary -->
                    <div>
                        <label for="summary" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Summary <span class="text-gray-500">(max 280 characters)</span>
                        </label>
                        <textarea id="summary"
                                  name="summary"
                                  rows="2"
                                  maxlength="280"
                                  required
                                  class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">{{ old('summary', $news->summary) }}</textarea>
                        <p class="mt-1 text-sm text-gray-500" x-data="{ count: {{ strlen(old('summary', $news->summary)) }} }" x-init="$el.previousElementSibling.addEventListener('input', e => count = e.target.value.length)">
                            <span x-text="count"></span>/280
                        </p>
                    </div>

                    <!-- Content (Tiptap Editor) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Content</label>
                        <div data-vue-component="tiptap-editor"
                             data-name="content"
                             data-placeholder="Write your article content here..."
                             data-initial-content="{{ json_encode(old('content', $news->content)) }}">
                        </div>
                    </div>

                    <!-- Image URL -->
                    <div>
                        <label for="image_path" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Image URL</label>
                        <input type="text"
                               id="image_path"
                               name="image_path"
                               value="{{ old('image_path', $news->image_path) }}"
                               placeholder="https://example.com/image.jpg"
                               class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        @if($news->image_url)
                            <img src="{{ $news->image_url }}" alt="Preview" class="mt-2 max-w-xs rounded-lg">
                        @endif
                    </div>

                    <!-- Status and Published Date -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                            <select id="status"
                                    name="status"
                                    required
                                    class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                @foreach($statuses as $status)
                                    <option value="{{ $status->value }}" {{ old('status', $news->status->value) === $status->value ? 'selected' : '' }}>
                                        {{ $status->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="published_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Publish Date</label>
                            <input type="datetime-local"
                                   id="published_at"
                                   name="published_at"
                                   value="{{ old('published_at', $news->published_at?->format('Y-m-d\TH:i')) }}"
                                   class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Source Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="source_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Source URL</label>
                            <input type="url"
                                   id="source_url"
                                   name="source_url"
                                   value="{{ old('source_url', $news->source_url) }}"
                                   placeholder="https://example.com/original-article"
                                   class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="source_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Source Name</label>
                            <input type="text"
                                   id="source_name"
                                   name="source_name"
                                   value="{{ old('source_name', $news->source_name) }}"
                                   placeholder="e.g., IGN, Kotaku"
                                   class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Tags -->
                    <div>
                        <label for="tags" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tags (comma separated)</label>
                        <input type="text"
                               id="tags_input"
                               value="{{ old('tags') ? implode(', ', old('tags')) : ($news->tags ? implode(', ', $news->tags) : '') }}"
                               placeholder="gaming, news, update"
                               class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        <div id="tags_container"></div>
                    </div>

                    <!-- Submit -->
                    <div class="flex justify-end gap-4">
                        <a href="{{ route('admin.news.index') }}" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                            Update Article
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Handle tags input
        document.getElementById('articleForm').addEventListener('submit', function(e) {
            const tagsInput = document.getElementById('tags_input');
            const tagsContainer = document.getElementById('tags_container');

            // Clear existing hidden inputs
            tagsContainer.innerHTML = '';

            // Parse tags and create hidden inputs
            const tags = tagsInput.value.split(',').map(t => t.trim()).filter(t => t);
            tags.forEach((tag, index) => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = `tags[${index}]`;
                hidden.value = tag;
                tagsContainer.appendChild(hidden);
            });
        });
    </script>
@endsection
