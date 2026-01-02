@props(['active' => ''])

<div class="bg-gray-700 border-b border-gray-600">
    <div class="container mx-auto px-4">
        <nav class="flex gap-6 py-3">
            <a href="{{ route('releases', 'monthly') }}"
               class="{{ $active === 'monthly' ? 'text-orange-400 font-semibold' : 'text-white hover:text-orange-300' }} transition">
                Monthly Releases
            </a>
            <a href="{{ route('releases', 'indie-games') }}"
               class="{{ $active === 'indie-games' ? 'text-orange-400 font-semibold' : 'text-white hover:text-orange-300' }} transition">
                Indie Games
            </a>
            <a href="{{ route('releases', 'seasoned') }}"
               class="{{ $active === 'seasoned' ? 'text-orange-400 font-semibold' : 'text-white hover:text-orange-300' }} transition">
                Seasoned Lists
            </a>
        </nav>
    </div>
</div>
