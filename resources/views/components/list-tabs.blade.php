@props([
    'lists',
    'selectedList',
    'type'
])

@if($lists->count() > 1)
    <div class="mb-8 border-b border-gray-200 dark:border-gray-700">
        <nav class="flex gap-6 overflow-x-auto">
            @foreach($lists as $list)
                <a href="{{ route('releases', $type) }}?list={{ $list->id }}"
                   class="px-4 py-3 border-b-2 whitespace-nowrap transition {{ $selectedList && $selectedList->id === $list->id ? 'border-orange-500 text-orange-600 dark:text-orange-400 font-semibold' : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-orange-500 hover:border-orange-300' }}">
                    {{ $list->name }}
                </a>
            @endforeach
        </nav>
    </div>
@endif
