@extends('layouts.app')

@section('title', 'System Lists Management')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-10">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                System Lists Management
            </h1>
            <a href="{{ route('admin.system-lists.create') }}" class="bg-teal-600 hover:bg-teal-700 text-white px-6 py-2 rounded-lg transition">
                Create System List
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif

        @if($systemLists->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Slug</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Games</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($systemLists as $list)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $list->name }}</div>
                                    @if($list->description)
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ Str::limit($list->description, 50) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($list->slug)
                                        <a href="{{ route('lists.show', [$list->list_type->toSlug(), $list->slug]) }}"
                                           class="text-sm text-teal-600 hover:text-teal-700"
                                           target="_blank">
                                            /list/{{ $list->list_type->toSlug() }}/{{ $list->slug }}
                                        </a>
                                    @else
                                        <span class="text-sm text-gray-400">No slug</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $list->games->count() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $list->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $list->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                    @if($list->end_at)
                                        <div class="text-xs text-gray-500 mt-1">
                                            Expires {{ $list->end_at->format('d/m/Y') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $list->user->name ?? 'System' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex gap-2">
                                        <a href="{{ route('lists.show', $list) }}" 
                                           class="text-teal-600 hover:text-teal-900">View</a>
                                        <a href="{{ route('lists.edit', $list) }}" 
                                           class="text-blue-600 hover:text-blue-900">Edit</a>
                                        <form action="{{ route('admin.system-lists.toggle', $list) }}" 
                                              method="POST" 
                                              class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" 
                                                    class="text-{{ $list->is_active ? 'yellow' : 'green' }}-600 hover:text-{{ $list->is_active ? 'yellow' : 'green' }}-900">
                                                {{ $list->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <p class="text-xl text-gray-600 dark:text-gray-400 mb-4">
                    No system lists found.
                </p>
                <a href="{{ route('admin.system-lists.create') }}" class="text-teal-600 hover:text-teal-700">
                    Create your first system list
                </a>
            </div>
        @endif
    </div>
@endsection

