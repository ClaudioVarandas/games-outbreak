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
                <div class="h-10 px-3 bg-orange-600 rounded-lg flex items-center justify-center font-black text-white text-xl tracking-tight" style="display: none;">
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
                <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                    <button @click="open = !open" class="flex items-center space-x-2 hover:bg-gray-700 px-2 py-1 rounded transition">
                        <div class="w-8 h-8 bg-orange-500 rounded-full flex items-center justify-center text-white font-bold text-sm">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="open" 
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-48 bg-gray-800 text-white rounded-md shadow-lg z-50 border border-gray-700 overflow-hidden"
                         style="display: none;">
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-4 py-2 hover:bg-gray-700 transition cursor-pointer">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <span>Profile</span>
                        </a>
                        <a href="{{ route('backlog') }}" class="flex items-center gap-3 px-4 py-2 hover:bg-gray-700 transition cursor-pointer">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                            </svg>
                            <span>Backlog</span>
                        </a>
                        <a href="{{ route('wishlist') }}" class="flex items-center gap-3 px-4 py-2 hover:bg-gray-700 transition cursor-pointer">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                            </svg>
                            <span>Wishlist</span>
                        </a>
                        <a href="{{ route('lists.index') }}" class="flex items-center gap-3 px-4 py-2 hover:bg-gray-700 transition cursor-pointer">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <span>Lists</span>
                        </a>
                        <hr class="border-gray-700 my-1">
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="flex items-center gap-3 w-full text-left px-4 py-2 hover:bg-gray-700 transition cursor-pointer">
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <!-- Guest -->
                <div x-data>
                    <button type="button" @click.prevent="$dispatch('open-modal', 'login-modal')" class="bg-orange-600 hover:bg-orange-700 px-4 py-2 rounded-lg transition">Login</button>
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
