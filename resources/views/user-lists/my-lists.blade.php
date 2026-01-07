@extends('layouts.app')

@section('title', ($canManage ? 'Manage ' : '') . $user->name . "'s Lists")

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                {{ $user->name }}'s Lists
                @if($canManage)
                    <span class="text-sm text-orange-600 dark:text-orange-400 font-normal ml-2">(Managing)</span>
                @endif
            </h1>
            @if($canManage)
                <a href="{{ route('user.lists.lists.create', $user->username) }}"
                   class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                    Create New List
                </a>
            @endif
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="p-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Delete List</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        Are you sure you want to delete "<span id="deleteListName" class="font-semibold"></span>"?
                        This action cannot be undone and will remove all games from this list.
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

        @if($regularLists->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($regularLists as $list)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 relative border border-gray-200 dark:border-gray-700">
                        <!-- Status Icons - Top Right -->
                        <div class="absolute top-4 right-4 flex gap-2">
                            <!-- Active Status -->
                            @if($list->is_active)
                                <svg class="w-5 h-5 text-green-600 dark:text-green-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Active">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            @else
                                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Inactive">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            @endif

                            <!-- Public/Private Status -->
                            @if($list->is_public)
                                <svg class="w-5 h-5 text-green-600 dark:text-green-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Public">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            @else
                                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Private">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                </svg>
                            @endif
                        </div>

                        <div class="mb-4 pr-20">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">{{ $list->name }}</h3>
                            @if($list->description)
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2 line-clamp-2">
                                    {{ Str::limit($list->description, 100) }}
                                </p>
                            @endif
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $list->games->count() }} {{ Str::plural('game', $list->games->count()) }}
                            </div>
                        </div>
                        <div class="flex gap-2 justify-end">
                            @if($canManage)
                                <a href="{{ route('user.lists.lists.show', [$user->username, $list->slug]) }}"
                                   class="p-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition"
                                   title="Manage">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </a>
                                <button onclick="openDeleteModal('{{ $list->name }}', '{{ route('user.lists.lists.destroy', [$user->username, $list->slug]) }}')"
                                        class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition"
                                        title="Delete">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            @else
                                @if($list->is_public)
                                    <a href="{{ route('lists.show', [$list->list_type->toSlug(), $list->slug]) }}"
                                       class="p-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition"
                                       title="View">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </a>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <p class="text-gray-600 dark:text-gray-400">
                    @if($canManage)
                        No regular lists yet.
                    @else
                        This user hasn't created any regular lists yet.
                    @endif
                </p>
            </div>
        @endif
    </div>

    <script>
        function openDeleteModal(listName, deleteUrl) {
            document.getElementById('deleteListName').textContent = listName;
            document.getElementById('deleteForm').action = deleteUrl;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });
    </script>
@endsection
