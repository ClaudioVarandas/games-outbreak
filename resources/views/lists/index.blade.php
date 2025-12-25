@extends('layouts.app')

@section('title', 'Game Lists')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-10">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                Game Lists
            </h1>
            <a href="{{ route('lists.create') }}" class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-2 rounded-lg transition">
                Create New List
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif

        <!-- Special Lists (Backlog & Wishlist) -->
        <div class="mb-12">
            <h2 class="text-2xl font-semibold mb-6 text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <svg class="w-6 h-6 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
                Special Lists
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @if($backlogList)
                    @include('lists.partials.card', ['list' => $backlogList, 'showActions' => true])
                @endif
                @if($wishlistList)
                    @include('lists.partials.card', ['list' => $wishlistList, 'showActions' => true])
                @endif
            </div>
        </div>

        <!-- Regular Lists -->
        <div class="mb-12">
            <h2 class="text-2xl font-semibold mb-6 text-gray-800 dark:text-gray-100">Your Lists ({{ $regularLists->count() }})</h2>
            @if($regularLists->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    @foreach($regularLists as $list)
                        @include('lists.partials.card', ['list' => $list, 'showActions' => true])
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                    <p class="text-xl text-gray-600 dark:text-gray-400 mb-4">
                        You don't have any custom lists yet.
                    </p>
                    <a href="{{ route('lists.create') }}" class="text-orange-600 hover:text-orange-700">
                        Create your first list
                    </a>
                </div>
            @endif
        </div>

        <!-- Featured Lists (System Lists) - Admin Only -->
        @auth
            @if(auth()->user()->isAdmin())
                <div class="mb-12">
                    <h2 class="text-2xl font-semibold mb-6 text-gray-800 dark:text-gray-100">Featured Lists ({{ $activeSystemLists->count() }})</h2>
                    @if($activeSystemLists->count() > 0)
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            @foreach($activeSystemLists as $list)
                                @include('lists.partials.card', ['list' => $list, 'showActions' => true, 'isSystem' => true])
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                            <p class="text-xl text-gray-600 dark:text-gray-400">
                                No featured lists available.
                            </p>
                        </div>
                    @endif
                </div>
            @endif
        @endauth

        <!-- Other Users' Lists - Admin Only -->
        @auth
            @if(auth()->user()->isAdmin())
                <div>
                    <h2 class="text-2xl font-semibold mb-6 text-gray-800 dark:text-gray-100">Other Users Lists ({{ $otherUsersLists->count() }})</h2>
                    @if($otherUsersLists->count() > 0)
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            @foreach($otherUsersLists as $list)
                                @php
                                    $canEdit = $list->canBeEditedBy(auth()->user());
                                @endphp
                                @include('lists.partials.card', ['list' => $list, 'showActions' => $canEdit])
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                            <p class="text-xl text-gray-600 dark:text-gray-400">
                                No other user lists available.
                            </p>
                        </div>
                    @endif
                </div>
            @endif
        @endauth
    </div>
@endsection

