@extends('layouts.app')

@section('title', 'Genres (Admin)')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                Genre Management
            </h1>
            <button onclick="openCreateModal()"
                    class="px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                Add Genre
            </button>
        </div>

        @if(session('success'))
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        {{-- Pending Review Section --}}
        @if($pendingGenres->isNotEmpty())
            <div class="mb-8 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-6">
                <h2 class="text-lg font-semibold text-yellow-800 dark:text-yellow-200 mb-4">
                    Pending Review ({{ $pendingGenres->count() }})
                </h2>
                <div class="space-y-2">
                    @foreach($pendingGenres as $genre)
                        <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg">
                            <span class="font-medium text-gray-900 dark:text-white">{{ $genre->name }}</span>
                            <div class="flex gap-2">
                                <form action="{{ route('admin.genres.approve', $genre) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition">
                                        Approve
                                    </button>
                                </form>
                                <form action="{{ route('admin.genres.reject', $genre) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Active Genres Table --}}
        @if($genres->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden mb-8">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-8"></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Indie Uses</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Monthly Uses</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Visible</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700" id="genreTableBody">
                        @foreach($genres as $genre)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-750" data-genre-id="{{ $genre->id }}">
                                <td class="px-6 py-4 cursor-move drag-handle">
                                    @unless($genre->is_system)
                                        <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"></path>
                                        </svg>
                                    @endunless
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $genre->name }}</span>
                                        @if($genre->is_system)
                                            <span class="px-2 py-0.5 text-xs bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300 rounded">System</span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $genre->slug }}</div>
                                </td>
                                <td class="px-6 py-4 text-center text-gray-600 dark:text-gray-400">
                                    {{ $genre->indie_list_count }}
                                </td>
                                <td class="px-6 py-4 text-center text-gray-600 dark:text-gray-400">
                                    {{ $genre->monthly_list_count }}
                                </td>
                                <td class="px-6 py-4 text-center text-gray-600 dark:text-gray-400">
                                    {{ $genre->usage_count }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if($genre->is_system)
                                        <span class="text-green-600 dark:text-green-400">Always</span>
                                    @else
                                        <form action="{{ route('admin.genres.toggle-visibility', $genre) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="p-1 rounded transition {{ $genre->is_visible ? 'text-green-600 hover:text-green-700' : 'text-gray-400 hover:text-gray-500' }}">
                                                @if($genre->is_visible)
                                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                                                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                                                    </svg>
                                                @else
                                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd"></path>
                                                        <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"></path>
                                                    </svg>
                                                @endif
                                            </button>
                                        </form>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex gap-2 justify-end">
                                        <button onclick="openEditModal({{ json_encode($genre) }})"
                                                class="p-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition"
                                                title="Edit">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        @unless($genre->is_system || $genre->usage_count > 0)
                                            <button onclick="openDeleteModal('{{ addslashes($genre->name) }}', '{{ route('admin.genres.destroy', $genre) }}')"
                                                    class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition"
                                                    title="Delete">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg mb-8">
                <p class="text-xl text-gray-600 dark:text-gray-400 mb-4">
                    No genres yet.
                </p>
                <button onclick="openCreateModal()" class="inline-flex items-center px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                    Create Your First Genre
                </button>
            </div>
        @endif

        {{-- Merge Genres Section --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Merge Genres</h2>
            <form action="{{ route('admin.genres.merge') }}" method="POST" class="flex flex-wrap items-end gap-4">
                @csrf
                <div class="flex-1 min-w-48">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Source Genre (will be removed)</label>
                    <select name="source_genre_id" required class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">Select source...</option>
                        @foreach($genres->where('is_system', false) as $genre)
                            <option value="{{ $genre->id }}">{{ $genre->name }} ({{ $genre->usage_count }} uses)</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center text-gray-500 dark:text-gray-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                    </svg>
                </div>
                <div class="flex-1 min-w-48">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Target Genre (will receive games)</label>
                    <select name="target_genre_id" required class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">Select target...</option>
                        @foreach($genres as $genre)
                            <option value="{{ $genre->id }}">{{ $genre->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    Merge
                </button>
            </form>
        </div>
    </div>

    {{-- Create/Edit Modal --}}
    <div id="genreModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
            <form id="genreForm" method="POST">
                @csrf
                <div id="methodField"></div>
                <div class="p-6">
                    <h3 id="modalTitle" class="text-xl font-bold text-gray-900 dark:text-white mb-4">Add Genre</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                            <input type="text" name="name" id="genreName" required
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Slug (optional)</label>
                            <input type="text" name="slug" id="genreSlug"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <p class="text-xs text-gray-500 mt-1">Leave empty to auto-generate from name</p>
                        </div>
                        <div>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_visible" id="genreVisible" value="1" checked
                                       class="rounded border-gray-300 dark:border-gray-600">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Visible in selection</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 p-6 bg-gray-50 dark:bg-gray-700 rounded-b-lg">
                    <button type="button" onclick="closeGenreModal()" class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Delete Modal --}}
    <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Delete Genre</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    Are you sure you want to delete "<span id="deleteGenreName" class="font-semibold"></span>"?
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

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        // Create/Edit Modal
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Add Genre';
            document.getElementById('genreForm').action = '{{ route('admin.genres.store') }}';
            document.getElementById('methodField').innerHTML = '';
            document.getElementById('genreName').value = '';
            document.getElementById('genreSlug').value = '';
            document.getElementById('genreVisible').checked = true;
            document.getElementById('genreModal').classList.remove('hidden');
        }

        function openEditModal(genre) {
            document.getElementById('modalTitle').textContent = 'Edit Genre';
            document.getElementById('genreForm').action = '{{ url('admin/genres') }}/' + genre.id;
            document.getElementById('methodField').innerHTML = '@method('PATCH')';
            document.getElementById('genreName').value = genre.name;
            document.getElementById('genreSlug').value = genre.slug;
            document.getElementById('genreVisible').checked = genre.is_visible;
            document.getElementById('genreModal').classList.remove('hidden');
        }

        function closeGenreModal() {
            document.getElementById('genreModal').classList.add('hidden');
        }

        // Delete Modal
        function openDeleteModal(genreName, deleteUrl) {
            document.getElementById('deleteGenreName').textContent = genreName;
            document.getElementById('deleteForm').action = deleteUrl;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modals on backdrop click or escape
        ['genreModal', 'deleteModal'].forEach(modalId => {
            document.getElementById(modalId).addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeGenreModal();
                closeDeleteModal();
            }
        });

        // Drag and drop reordering
        const tableBody = document.getElementById('genreTableBody');
        if (tableBody) {
            new Sortable(tableBody, {
                handle: '.drag-handle',
                animation: 150,
                onEnd: function() {
                    const order = Array.from(tableBody.querySelectorAll('tr[data-genre-id]'))
                        .map(row => row.dataset.genreId);

                    fetch('{{ route('admin.genres.reorder') }}', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ order })
                    });
                }
            });
        }
    </script>
@endsection
