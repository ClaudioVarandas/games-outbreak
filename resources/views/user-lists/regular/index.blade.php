@extends('layouts.app')

@section('title', ($canManage ? 'Manage ' : '') . $user->name . "'s Lists")

@section('content')
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-4xl font-bold mb-2 text-gray-800 dark:text-gray-100">
                    {{ $user->name }}'s Lists
                    @if($canManage)
                        <span class="text-sm text-orange-600 dark:text-orange-400 font-normal ml-2">(Managing)</span>
                    @endif
                </h1>

                @if($lists->count() > 0)
                    <p class="text-gray-600 dark:text-gray-400">
                        {{ $lists->count() }} {{ Str::plural('list', $lists->count()) }}
                    </p>
                @endif
            </div>

            @if($canManage)
                <a href="{{ route('user.lists.regular.create', $user->username) }}"
                   class="px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                    Create New List
                </a>
            @endif
        </div>

        @if($lists->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($lists as $list)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition">
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                                        {{ $list->name }}
                                    </h3>

                                    @if($list->description)
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                            {{ Str::limit($list->description, 100) }}
                                        </p>
                                    @endif

                                    <div class="flex items-center gap-2 text-sm">
                                        <span class="px-2 py-1 {{ $list->is_public ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' }} rounded">
                                            {{ $list->is_public ? 'Public' : 'Private' }}
                                        </span>

                                        <span class="text-gray-600 dark:text-gray-400">
                                            {{ $list->games->count() }} {{ Str::plural('game', $list->games->count()) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            @if($canManage)
                                <div class="flex gap-2 mt-4">
                                    <a href="{{ route('user.lists.regular.edit', [$user->username, $list->slug]) }}"
                                       class="flex-1 px-4 py-2 bg-orange-600 text-white text-center rounded hover:bg-orange-700 transition">
                                        Manage
                                    </a>

                                    <form action="{{ route('user.lists.regular.destroy', [$user->username, $list->slug]) }}"
                                          method="POST"
                                          onsubmit="return confirm('Are you sure you want to delete this list?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <p class="text-lg text-gray-600 dark:text-gray-400 mb-4">
                    @if($canManage)
                        You don't have any lists yet.
                    @else
                        This user hasn't created any lists yet.
                    @endif
                </p>
                @if($canManage)
                    <a href="{{ route('user.lists.regular.create', $user->username) }}"
                       class="inline-block px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                        Create Your First List
                    </a>
                @endif
            </div>
        @endif
    </div>
@endsection
