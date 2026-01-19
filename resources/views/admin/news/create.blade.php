@extends('layouts.app')

@section('title', 'Create Article')

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
                        Create Article
                    </h1>
                </div>
            </div>

            @if($errors->any())
                <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- URL Import Section -->
            @if(config('features.news_url_import'))
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6" x-data="urlImporter()">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Import from URL</h2>
                    <div class="flex gap-4">
                        <input type="url"
                               x-model="url"
                               placeholder="https://example.com/article..."
                               class="flex-1 px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        <button type="button"
                                @click="importUrl"
                                :disabled="loading"
                                class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition disabled:opacity-50">
                            <span x-show="!loading">Import</span>
                            <span x-show="loading">Importing...</span>
                        </button>
                    </div>
                    <p x-show="error" x-text="error" class="mt-2 text-red-500 text-sm"></p>
                </div>
            @endif

            <!-- Article Form -->
            <form action="{{ route('admin.news.store') }}" method="POST" class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6" id="articleForm">
                @csrf

                <div class="space-y-6">
                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title</label>
                        <input type="text"
                               id="title"
                               name="title"
                               value="{{ old('title') }}"
                               required
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
                                  class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">{{ old('summary') }}</textarea>
                        <p class="mt-1 text-sm text-gray-500" x-data="{ count: 0 }" x-init="count = $el.previousElementSibling.value.length; $el.previousElementSibling.addEventListener('input', e => count = e.target.value.length)">
                            <span x-text="count"></span>/280
                        </p>
                    </div>

                    <!-- Content (Tiptap Editor) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Content</label>
                        <div data-vue-component="tiptap-editor"
                             data-name="content"
                             data-placeholder="Write your article content here..."
                             data-initial-content="{{ old('content', json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph']]])) }}">
                        </div>
                    </div>

                    <!-- Image URL -->
                    <div>
                        <label for="image_path" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Image URL</label>
                        <input type="text"
                               id="image_path"
                               name="image_path"
                               value="{{ old('image_path') }}"
                               placeholder="https://example.com/image.jpg"
                               class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
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
                                    <option value="{{ $status->value }}" {{ old('status', 'draft') === $status->value ? 'selected' : '' }}>
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
                                   value="{{ old('published_at') }}"
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
                                   value="{{ old('source_url') }}"
                                   placeholder="https://example.com/original-article"
                                   class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="source_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Source Name</label>
                            <input type="text"
                                   id="source_name"
                                   name="source_name"
                                   value="{{ old('source_name') }}"
                                   placeholder="e.g., IGN, Kotaku"
                                   class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Tags -->
                    <div>
                        <label for="tags" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tags (comma separated)</label>
                        <input type="text"
                               id="tags_input"
                               value="{{ old('tags') ? implode(', ', old('tags')) : '' }}"
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
                            Create Article
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

        @if(config('features.news_url_import'))
        function urlImporter() {
            return {
                url: '',
                loading: false,
                error: '',
                async importUrl() {
                    if (!this.url) return;

                    this.loading = true;
                    this.error = '';

                    try {
                        const response = await fetch('{{ route('admin.news.import-url') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ url: this.url }),
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            this.error = data.error || 'Failed to import content';
                            return;
                        }

                        // Fill form fields
                        if (data.data.title) {
                            document.getElementById('title').value = data.data.title;
                        }
                        if (data.data.summary) {
                            document.getElementById('summary').value = data.data.summary;
                            // Trigger input event for character counter
                            document.getElementById('summary').dispatchEvent(new Event('input'));
                        }
                        if (data.data.image_path) {
                            document.getElementById('image_path').value = data.data.image_path;
                        }
                        if (data.data.source_url) {
                            document.getElementById('source_url').value = data.data.source_url;
                        }
                        if (data.data.source_name) {
                            document.getElementById('source_name').value = data.data.source_name;
                        }

                        // Update Tiptap editor content via custom event
                        if (data.data.content) {
                            window.dispatchEvent(new CustomEvent('tiptap-set-content', {
                                detail: { content: data.data.content }
                            }));
                        }

                        // Show success message
                        this.url = '';
                        alert('Content imported successfully! Review and edit as needed.');
                    } catch (err) {
                        this.error = 'An error occurred while importing';
                        console.error(err);
                    } finally {
                        this.loading = false;
                    }
                }
            };
        }
        @endif
    </script>
@endsection
