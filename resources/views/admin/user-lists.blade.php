@extends('layouts.app')

@section('title', 'User Lists (Admin)')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-4xl font-bold mb-8 text-gray-800 dark:text-gray-100">
            All User Lists
        </h1>

        @if($lists->count() > 0)
            <div class="space-y-8">
                @foreach($lists as $userId => $userLists)
                    @php
                        $user = $userLists->first()->user;
                    @endphp

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                        <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">
                            {{ $user->name }} (@{{ $user->username }})
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($userLists as $list)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <h3 class="font-bold text-gray-900 dark:text-white">{{ $list->name }}</h3>
                                            <span class="text-xs px-2 py-1 rounded {{ $list->list_type->colorClass() }}">
                                                {{ $list->list_type->label() }}
                                            </span>
                                        </div>

                                        <span class="text-xs px-2 py-1 rounded {{ $list->is_public ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' }}">
                                            {{ $list->is_public ? 'Public' : 'Private' }}
                                        </span>
                                    </div>

                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        {{ $list->games->count() }} {{ Str::plural('game', $list->games->count()) }}
                                    </p>

                                    <div class="flex gap-2">
                                        @if($list->list_type->value === 'backlog')
                                            <a href="{{ route('user.lists.backlog', $user->username) }}"
                                               class="text-sm text-orange-600 hover:text-orange-700">View</a>
                                        @elseif($list->list_type->value === 'wishlist')
                                            <a href="{{ route('user.lists.wishlist', $user->username) }}"
                                               class="text-sm text-orange-600 hover:text-orange-700">View</a>
                                        @else
                                            <a href="{{ route('user.lists.lists.show', [$user->username, $list->slug]) }}"
                                               class="text-sm text-orange-600 hover:text-orange-700">Manage</a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <p class="text-lg text-gray-600 dark:text-gray-400">
                    No user lists found.
                </p>
            </div>
        @endif
    </div>
@endsection
