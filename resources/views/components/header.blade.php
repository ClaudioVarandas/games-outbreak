@props([])

<div class="bg-gray-800 text-white py-4 shadow-md">
    <div class="container mx-auto flex items-center justify-between px-4">
        <!-- Left: Logo -->
        <div class="flex items-center">
            <a href="{{ route('homepage') }}" class="flex items-center gap-3 hover:opacity-80 transition">
                <img src="{{ asset('images/games-outbreak-logo.png') }}" 
                     alt="Games Outbreak" 
                     class="h-10 w-auto"
                     onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="h-10 px-3 bg-teal-600 rounded-lg flex items-center justify-center font-black text-white text-xl tracking-tight" style="display: none;">
                    GO
                </div>
                <span class="text-xl font-bold text-white">Games Outbreak</span>
            </a>
        </div>

        <!-- Center: Search Bar -->
        <div id="app-search" class="flex-1 mx-8 max-w-2xl">
            <global-search></global-search>
        </div>

        <!-- Right: Auth Links -->
        <div class="flex items-center space-x-4">
            @auth
                <!-- User Dropdown -->
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="flex items-center space-x-2 hover:bg-gray-700 px-2 py-1 rounded">
                        <div class="w-8 h-8 bg-teal-500 rounded-full flex items-center justify-center text-white font-bold text-sm">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-white text-gray-800 rounded-md shadow-lg z-10">
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 hover:bg-gray-100">Profile</a>
                        <a href="{{ route('lists.index') }}" class="block px-4 py-2 hover:bg-gray-100">Lists</a>
                        <hr class="border-gray-200">
                        <form action="{{ route('logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="block w-full text-left px-4 py-2 hover:bg-gray-100">Logout</button>
                        </form>
                    </div>
                </div>
            @else
                <!-- Guest -->
                <div x-data>
                    <button type="button" @click.prevent="$dispatch('open-modal', 'login-modal')" class="bg-teal-600 hover:bg-teal-700 px-4 py-2 rounded-lg transition">Login</button>
                </div>
            @endauth
        </div>
    </div>

    <!-- Auth Modals -->
    @guest
        <x-auth.login-modal />
        <x-auth.register-modal />
        <x-auth.forgot-password-modal />
    @endguest
</div>
