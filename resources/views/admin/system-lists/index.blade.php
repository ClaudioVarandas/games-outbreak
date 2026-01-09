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

        <!-- Monthly Lists (Grouped by Year) -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">Monthly Lists</h2>
            @if($monthlyLists->count() > 0)
                @foreach($monthlyLists as $year => $lists)
                    <div class="mb-4 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                        <!-- Accordion Header -->
                        <button onclick="toggleAccordion('monthly-{{ $year }}')"
                                class="w-full flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-750 transition">
                            <div class="flex items-center gap-3">
                                <svg id="monthly-{{ $year }}-icon" class="w-5 h-5 text-gray-600 dark:text-gray-400 transition-transform {{ $year == now()->year ? 'rotate-90' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $year }}</h3>
                                <span class="px-3 py-1 bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300 rounded-full text-sm font-medium">
                                    {{ $lists->count() }} {{ Str::plural('list', $lists->count()) }}
                                </span>
                            </div>
                        </button>

                        <!-- Accordion Content -->
                        <div id="monthly-{{ $year }}-content" class="{{ $year == now()->year ? '' : 'hidden' }} p-4 bg-white dark:bg-gray-800">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                @foreach($lists as $list)
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
                                                {{ $list->games->count() }} {{ Str::plural('game', $list->games->count()) }}
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
                        </div>
                    </div>
                @endforeach
            @else
                <p class="text-gray-600 dark:text-gray-400">No monthly lists.</p>
            @endif
        </div>

        <!-- Indie Games Lists (Grouped by Year) -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">Indie Games Lists</h2>
            @if($indieGamesList->count() > 0)
                @foreach($indieGamesList as $year => $lists)
                    <div class="mb-4 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                        <!-- Accordion Header -->
                        <button onclick="toggleAccordion('indie-{{ $year }}')"
                                class="w-full flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-750 transition">
                            <div class="flex items-center gap-3">
                                <svg id="indie-{{ $year }}-icon" class="w-5 h-5 text-gray-600 dark:text-gray-400 transition-transform {{ $year == now()->year ? 'rotate-90' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $year }}</h3>
                                <span class="px-3 py-1 bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300 rounded-full text-sm font-medium">
                                    {{ $lists->count() }} {{ Str::plural('list', $lists->count()) }}
                                </span>
                            </div>
                        </button>

                        <!-- Accordion Content -->
                        <div id="indie-{{ $year }}-content" class="{{ $year == now()->year ? '' : 'hidden' }} p-4 bg-white dark:bg-gray-800">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                @foreach($lists as $list)
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
                                                {{ $list->games->count() }} {{ Str::plural('game', $list->games->count()) }}
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
                        </div>
                    </div>
                @endforeach
            @else
                <p class="text-gray-600 dark:text-gray-400">No indie games lists.</p>
            @endif
        </div>

        <!-- Seasoned Lists (Active Only - No Grouping) -->
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
                                    {{ $list->games->count() }} {{ Str::plural('game', $list->games->count()) }}
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

        <!-- Events Lists (Flat List - Ordered by Created Date) -->
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
                                    {{ $list->games->count() }} {{ Str::plural('game', $list->games->count()) }}
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
        function toggleAccordion(id) {
            const content = document.getElementById(id + '-content');
            const icon = document.getElementById(id + '-icon');

            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                icon.classList.add('rotate-90');
            } else {
                content.classList.add('hidden');
                icon.classList.remove('rotate-90');
            }
        }

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
