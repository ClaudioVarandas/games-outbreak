@props([
    'year',
    'month',
    'type'
])

@php
    $currentDate = \Carbon\Carbon::create($year, $month, 1);
    $prevMonth = $currentDate->copy()->subMonth();
    $nextMonth = $currentDate->copy()->addMonth();

    // Generate year range (current year ± 2)
    $years = range(now()->year - 2, now()->year + 2);
@endphp

<div class="flex items-center justify-between mb-8 bg-gray-100 dark:bg-gray-800 p-4 rounded-lg">
    <!-- Previous Month -->
    <a href="{{ route('releases', $type) }}?year={{ $prevMonth->year }}&month={{ $prevMonth->month }}"
       class="p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>

    <!-- Current Month/Year Display -->
    <div class="flex items-center gap-4">
        <span class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ $currentDate->format('F Y') }}</span>

        <!-- Year Dropdown -->
        <select onchange="window.location.href='{{ route('releases', $type) }}?year=' + this.value + '&month={{ $month }}'"
                class="px-3 py-1 rounded bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100">
            @foreach($years as $y)
                <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>
    </div>

    <!-- Next Month -->
    <a href="{{ route('releases', $type) }}?year={{ $nextMonth->year }}&month={{ $nextMonth->month }}"
       class="p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </a>
</div>
