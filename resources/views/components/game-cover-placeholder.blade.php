@props([
    'gameName',
    'class' => 'w-full h-full',
])

<div class="{{ $class }} bg-gradient-to-br from-gray-800 to-gray-900 flex flex-col items-center justify-center p-4 text-center">
    <p class="text-white font-semibold text-sm md:text-base mb-6 line-clamp-2 px-2">
        {{ $gameName }}
    </p>
    <img src="{{ asset('images/game-controller.svg') }}" 
         alt="Game Controller" 
         class="w-24 h-24 max-w-full opacity-70">
</div>

