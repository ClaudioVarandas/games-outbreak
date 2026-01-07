@extends('layouts.app')

@section('title', 'My Lists (Admin)')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-4xl font-bold mb-8 text-gray-800 dark:text-gray-100">
            My Lists
        </h1>

        <!-- Backlog -->
        @if($backlogList)
            <div class="mb-8">
                <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">Backlog</h2>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">
                                {{ $backlogList->games->count() }} {{ Str::plural('game', $backlogList->games->count()) }}
                            </p>
                        </div>
                        <a href="{{ route('user.lists.backlog', auth()->user()->username) }}"
                           class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                            Manage
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <!-- Wishlist -->
        @if($wishlistList)
            <div class="mb-8">
                <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">Wishlist</h2>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">
                                {{ $wishlistList->games->count() }} {{ Str::plural('game', $wishlistList->games->count()) }}
                            </p>
                        </div>
                        <a href="{{ route('user.lists.wishlist', auth()->user()->username) }}"
                           class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                            Manage
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <!-- Regular Lists -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Regular Lists</h2>
                <a href="{{ route('user.lists.lists.create', auth()->user()->username) }}"
                   class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                    Create New List
                </a>
            </div>

            @if($regularLists->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($regularLists as $list)
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 relative">
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

                            <div class="pr-16">
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">{{ $list->name }}</h3>
                                <p class="text-gray-600 dark:text-gray-400 mb-4">
                                    {{ $list->games->count() }} {{ Str::plural('game', $list->games->count()) }}
                                </p>
                                <a href="{{ route('user.lists.lists.show', [auth()->user()->username, $list->slug]) }}"
                                   class="inline-block px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                                    Manage
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                    <p class="text-gray-600 dark:text-gray-400">No regular lists yet.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
