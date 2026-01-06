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
                <a href="{{ route('user.lists.regular.create', auth()->user()->username) }}"
                   class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                    Create New List
                </a>
            </div>

            @if($regularLists->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($regularLists as $list)
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">{{ $list->name }}</h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-4">
                                {{ $list->games->count() }} {{ Str::plural('game', $list->games->count()) }}
                            </p>
                            <a href="{{ route('user.lists.regular.edit', [auth()->user()->username, $list->slug]) }}"
                               class="inline-block px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                                Manage
                            </a>
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
