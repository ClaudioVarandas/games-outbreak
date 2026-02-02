@extends('layouts.app')

@section('title', 'System Lists (Admin)')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                System Lists
            </h1>
            <a href="{{ route('admin.system-lists.create') }}"
               class="px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                Create System List
            </a>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="p-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Delete System List</h3>
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

        <!-- Yearly Lists -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">
                Yearly Lists
                @if($yearlyLists->count() > 0)
                    <span class="text-lg font-normal text-gray-600 dark:text-gray-400">({{ $yearlyLists->count() }} total)</span>
                @endif
            </h2>
            @if($yearlyLists->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($yearlyLists as $list)
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 relative border border-gray-200 dark:border-gray-700">
                            <!-- Status Icons - Top Right -->
                            <div class="absolute top-4 right-4 flex gap-2">
                                @if($list->is_active)
                                    <svg class="w-5 h-5 text-green-600 dark:text-green-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Active">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-red-600 dark:text-red-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Inactive">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @endif
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
                                <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                                    {{ $list->games_count }} {{ str()->plural('game', $list->games_count) }}
                                </div>
                                <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                        </svg>
                                        {{ $list->highlights_count }} highlights
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"></path>
                                        </svg>
                                        {{ $list->indie_count }} indies
                                    </span>
                                </div>
                                @if($list->start_at || $list->end_at)
                                    <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-500 mt-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <span>
                                            @if($list->start_at && $list->end_at)
                                                {{ $list->start_at->format('Y') }}
                                            @elseif($list->start_at)
                                                {{ $list->start_at->locale('pt_PT')->translatedFormat('d M, Y') }}
                                            @else
                                                {{ $list->end_at->locale('pt_PT')->translatedFormat('d M, Y') }}
                                            @endif
                                        </span>
                                    </div>
                                @endif
                            </div>
                            <div class="flex gap-2 justify-end">
                                <a href="{{ route('admin.system-lists.edit', [$list->list_type->toSlug(), $list->slug]) }}"
                                   class="p-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition"
                                   title="Edit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </a>
                                <button onclick="openDeleteModal('{{ $list->name }}', '{{ route('admin.system-lists.destroy', [$list->list_type->toSlug(), $list->slug]) }}')"
                                        class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition"
                                        title="Delete">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-600 dark:text-gray-400">No yearly lists.</p>
            @endif
        </div>

        <!-- Seasoned Lists -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">
                Seasoned Lists
                @if($seasonedLists->count() > 0)
                    <span class="text-lg font-normal text-gray-600 dark:text-gray-400">({{ $seasonedLists->count() }} active)</span>
                @endif
            </h2>
            @if($seasonedLists->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($seasonedLists as $list)
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 relative">
                            <!-- Status Icons - Top Right -->
                            <div class="absolute top-4 right-4 flex gap-2">
                                @if($list->is_active)
                                    <svg class="w-5 h-5 text-green-600 dark:text-green-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Active">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-red-600 dark:text-red-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Inactive">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @endif
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
                                <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                                    {{ $list->games_count }} {{ str()->plural('game', $list->games_count) }}
                                </div>
                                @if($list->start_at || $list->end_at)
                                    <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-500">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <span>
                                            @if($list->start_at && $list->end_at)
                                                {{ $list->start_at->locale('pt_PT')->translatedFormat('d M, Y') }} - {{ $list->end_at->locale('pt_PT')->translatedFormat('d M, Y') }}
                                            @elseif($list->start_at)
                                                {{ $list->start_at->locale('pt_PT')->translatedFormat('d M, Y') }}
                                            @else
                                                {{ $list->end_at->locale('pt_PT')->translatedFormat('d M, Y') }}
                                            @endif
                                        </span>
                                    </div>
                                @endif
                            </div>
                            <div class="flex gap-2 justify-end">
                                <a href="{{ route('admin.system-lists.edit', [$list->list_type->toSlug(), $list->slug]) }}"
                                   class="p-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition"
                                   title="Edit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </a>
                                <button onclick="openDeleteModal('{{ $list->name }}', '{{ route('admin.system-lists.destroy', [$list->list_type->toSlug(), $list->slug]) }}')"
                                        class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition"
                                        title="Delete">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-600 dark:text-gray-400">No active seasoned lists.</p>
            @endif
        </div>

        <!-- Events Lists -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">
                Events Lists
                @if($eventsLists->count() > 0)
                    <span class="text-lg font-normal text-gray-600 dark:text-gray-400">({{ $eventsLists->count() }} total)</span>
                @endif
            </h2>
            @if($eventsLists->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($eventsLists as $list)
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 relative">
                            <!-- Status Icons - Top Right -->
                            <div class="absolute top-4 right-4 flex gap-2">
                                @if($list->is_active)
                                    <svg class="w-5 h-5 text-green-600 dark:text-green-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Active">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-red-600 dark:text-red-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Inactive">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @endif
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
                                <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                                    {{ $list->games_count }} {{ str()->plural('game', $list->games_count) }}
                                </div>
                                @if($list->start_at || $list->end_at)
                                    <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-500">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <span>
                                            @if($list->start_at && $list->end_at)
                                                {{ $list->start_at->locale('pt_PT')->translatedFormat('d M, Y') }} - {{ $list->end_at->locale('pt_PT')->translatedFormat('d M, Y') }}
                                            @elseif($list->start_at)
                                                {{ $list->start_at->locale('pt_PT')->translatedFormat('d M, Y') }}
                                            @else
                                                {{ $list->end_at->locale('pt_PT')->translatedFormat('d M, Y') }}
                                            @endif
                                        </span>
                                    </div>
                                @endif
                            </div>
                            <div class="flex gap-2 justify-end">
                                <a href="{{ route('admin.system-lists.edit', [$list->list_type->toSlug(), $list->slug]) }}"
                                   class="p-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition"
                                   title="Edit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </a>
                                <button onclick="openDeleteModal('{{ $list->name }}', '{{ route('admin.system-lists.destroy', [$list->list_type->toSlug(), $list->slug]) }}')"
                                        class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition"
                                        title="Delete">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-600 dark:text-gray-400">No events lists.</p>
            @endif
        </div>
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
