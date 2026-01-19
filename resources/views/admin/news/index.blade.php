@extends('layouts.app')

@section('title', 'News (Admin)')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                News Management
            </h1>
            <a href="{{ route('admin.news.create') }}"
               class="px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                Create Article
            </a>
        </div>

        @if(session('success'))
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="p-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Delete Article</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        Are you sure you want to delete "<span id="deleteArticleName" class="font-semibold"></span>"?
                        This action cannot be undone.
                    </p>
                    <div class="flex gap-3">
                        <button onclick="closeDeleteModal()" class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                            Cancel
                        </button>
                        <form id="deleteForm" method="POST" class="flex-1">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if($news->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Author</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Published</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($news as $article)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-4">
                                        @if($article->image_url)
                                            <img src="{{ $article->image_url }}" alt="" class="w-16 h-10 object-cover rounded">
                                        @else
                                            <div class="w-16 h-10 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white">{{ Str::limit($article->title, 50) }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $article->slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $article->status->colorClass() }}">
                                        {{ $article->status->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $article->author?->name ?? 'Unknown' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $article->formatted_published_at ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex gap-2 justify-end">
                                        @if($article->isPublished())
                                            <a href="{{ route('news.show', $article) }}" target="_blank"
                                               class="p-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition"
                                               title="View">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </a>
                                        @endif
                                        <a href="{{ route('admin.news.edit', $article) }}"
                                           class="p-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition"
                                           title="Edit">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>
                                        <button onclick="openDeleteModal('{{ addslashes($article->title) }}', '{{ route('admin.news.destroy', $article) }}')"
                                                class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition"
                                                title="Delete">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $news->links() }}
            </div>
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                </svg>
                <p class="text-xl text-gray-600 dark:text-gray-400 mb-4">
                    No news articles yet.
                </p>
                <a href="{{ route('admin.news.create') }}" class="inline-flex items-center px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                    Create Your First Article
                </a>
            </div>
        @endif
    </div>

    <script>
        function openDeleteModal(articleName, deleteUrl) {
            document.getElementById('deleteArticleName').textContent = articleName;
            document.getElementById('deleteForm').action = deleteUrl;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });
    </script>
@endsection
