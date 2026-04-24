@extends('layouts.app')

@section('title', 'Video Categories (Admin)')

@section('content')
    <div class="page-shell py-8">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">Video Categories</h1>
            <button type="button" onclick="openCreateModal()"
                    class="bg-orange-600 hover:bg-orange-700 text-white font-semibold py-2 px-4 rounded">
                New Category
            </button>
        </div>

        @if (session('success'))
            <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded">
                <ul class="list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Preview</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Slug</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Color</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Icon</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Videos</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Active</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($categories as $category)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="neon-category-pill neon-category-pill--inline"
                                      style="--c: {{ $category->color ?? '#b581ff' }}">
                                    {{ strtoupper($category->name) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $category->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 font-mono">{{ $category->slug }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block h-5 w-5 rounded border border-gray-300" style="background: {{ $category->color ?? '#b581ff' }}"></span>
                                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $category->color ?? '—' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 font-mono">{{ $category->icon ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $category->videos_count }}</td>
                            <td class="px-4 py-3">
                                @if ($category->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Hidden</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex gap-2 justify-end">
                                    <button type="button"
                                            onclick='openEditModal(@json($category))'
                                            class="p-2 bg-orange-600 text-white rounded hover:bg-orange-700 transition"
                                            title="Edit">
                                        Edit
                                    </button>
                                    <button type="button"
                                            onclick="openDeleteModal('{{ addslashes($category->name) }}', {{ $category->videos_count }}, '{{ route('admin.video-categories.destroy', $category) }}')"
                                            class="p-2 bg-red-600 text-white rounded hover:bg-red-700 transition"
                                            title="Delete">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No categories yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Create/Edit Modal --}}
    <div id="categoryModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
            <form id="categoryForm" method="POST">
                @csrf
                <div id="methodField"></div>
                <div class="p-6">
                    <h3 id="modalTitle" class="text-xl font-bold text-gray-900 dark:text-white mb-4">New Category</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                            <input type="text" name="name" id="catName" required maxlength="60"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Slug</label>
                            <input type="text" name="slug" id="catSlug" required maxlength="60" pattern="^[a-z0-9\-]+$"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono text-sm">
                            <p class="text-xs text-gray-500 mt-1">Lowercase letters, digits and hyphens only.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" id="catColorPicker" value="#b581ff"
                                       class="h-9 w-12 rounded border border-gray-300 dark:border-gray-600">
                                <input type="text" name="color" id="catColorHex" value="#b581ff" maxlength="7" pattern="^#[0-9a-fA-F]{6}$"
                                       class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Icon</label>
                            <input type="text" name="icon" id="catIcon" maxlength="60"
                                   placeholder="heroicon slug (e.g. film, play, star)"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono text-sm">
                        </div>
                        <div>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_active" id="catActive" value="1" checked
                                       class="rounded border-gray-300 dark:border-gray-600">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Active (visible on the site)</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 p-6 bg-gray-50 dark:bg-gray-700 rounded-b-lg">
                    <button type="button" onclick="closeCategoryModal()" class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded hover:bg-gray-300 dark:hover:bg-gray-500 transition">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700 transition">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Delete Modal --}}
    <div id="deleteCategoryModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Delete Category</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-2">
                    Delete "<span id="delCatName" class="font-semibold"></span>"?
                </p>
                <p id="delCatUsage" class="text-sm text-amber-600 dark:text-amber-400 mb-6 hidden"></p>
                <div class="flex gap-3">
                    <button type="button" onclick="closeDeleteCategoryModal()" class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                        Cancel
                    </button>
                    <form id="deleteCategoryForm" method="POST" class="flex-1">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const storeUrl = '{{ route('admin.video-categories.store') }}';
        const updateUrlBase = '{{ url('admin/video-categories') }}/';

        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'New Category';
            document.getElementById('categoryForm').action = storeUrl;
            document.getElementById('methodField').innerHTML = '';
            document.getElementById('catName').value = '';
            document.getElementById('catSlug').value = '';
            document.getElementById('catColorHex').value = '#b581ff';
            document.getElementById('catColorPicker').value = '#b581ff';
            document.getElementById('catIcon').value = '';
            document.getElementById('catActive').checked = true;
            document.getElementById('categoryModal').classList.remove('hidden');
        }

        function openEditModal(category) {
            document.getElementById('modalTitle').textContent = 'Edit Category';
            document.getElementById('categoryForm').action = updateUrlBase + category.id;
            document.getElementById('methodField').innerHTML = '@method('PATCH')';
            document.getElementById('catName').value = category.name || '';
            document.getElementById('catSlug').value = category.slug || '';
            const col = category.color || '#b581ff';
            document.getElementById('catColorHex').value = col;
            document.getElementById('catColorPicker').value = col;
            document.getElementById('catIcon').value = category.icon || '';
            document.getElementById('catActive').checked = !!category.is_active;
            document.getElementById('categoryModal').classList.remove('hidden');
        }

        function closeCategoryModal() {
            document.getElementById('categoryModal').classList.add('hidden');
        }

        function openDeleteModal(name, usage, url) {
            document.getElementById('delCatName').textContent = name;
            const usageEl = document.getElementById('delCatUsage');
            if (usage > 0) {
                usageEl.textContent = usage + ' video(s) will be left without a category.';
                usageEl.classList.remove('hidden');
            } else {
                usageEl.classList.add('hidden');
            }
            document.getElementById('deleteCategoryForm').action = url;
            document.getElementById('deleteCategoryModal').classList.remove('hidden');
        }

        function closeDeleteCategoryModal() {
            document.getElementById('deleteCategoryModal').classList.add('hidden');
        }

        // sync color picker and hex text input
        document.getElementById('catColorPicker').addEventListener('input', (e) => {
            document.getElementById('catColorHex').value = e.target.value;
        });
        document.getElementById('catColorHex').addEventListener('input', (e) => {
            const v = e.target.value.trim();
            if (/^#[0-9a-fA-F]{6}$/.test(v)) {
                document.getElementById('catColorPicker').value = v;
            }
        });

        // close on backdrop click + ESC
        ['categoryModal', 'deleteCategoryModal'].forEach(id => {
            document.getElementById(id).addEventListener('click', function (e) {
                if (e.target === this) this.classList.add('hidden');
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeCategoryModal();
                closeDeleteCategoryModal();
            }
        });
    </script>
@endsection
