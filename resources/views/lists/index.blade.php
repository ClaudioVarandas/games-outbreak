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

        <!-- User Lists -->
        <div class="mb-12">
            <h2 class="text-2xl font-semibold mb-6 text-gray-800 dark:text-gray-100">Your Lists ({{ $userLists->count() }})</h2>
            @if($userLists->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    @foreach($userLists as $list)
                        @include('lists.partials.card', ['list' => $list, 'showActions' => true])
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                    <p class="text-xl text-gray-600 dark:text-gray-400 mb-4">
                        You don't have any lists yet.
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

