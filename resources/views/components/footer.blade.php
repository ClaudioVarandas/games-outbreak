<footer class="bg-gray-800 dark:bg-gray-900 text-gray-300 mt-auto">
    <div class="container mx-auto px-4 py-8">
{{--        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div>
                <h3 class="text-white font-semibold mb-4">Upcoming Games</h3>
                <ul class="space-y-2">
                    <li><a href="{{ route('upcoming') }}" class="hover:text-orange-400 transition">Upcoming Games</a></li>
                    <li><a href="{{ route('most-wanted') }}" class="hover:text-orange-400 transition">Most Wanted</a></li>
                    <li><a href="{{ route('homepage') }}" class="hover:text-orange-400 transition">Homepage</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-white font-semibold mb-4">Lists</h3>
                <ul class="space-y-2">
                    @auth
                        <li><a href="{{ route('lists.index') }}" class="hover:text-orange-400 transition">My Lists</a></li>
                    @else
                        <li><a href="{{ route('login') }}" class="hover:text-orange-400 transition">Login</a></li>
                    @endauth
                </ul>
            </div>
            <div>
                <h3 class="text-white font-semibold mb-4">About</h3>
                <p class="text-sm text-gray-400">
                    Discover and track upcoming video game releases across all platforms.
                </p>
            </div>
        </div>--}}
        <div class="border-t border-gray-700 mt-8 pt-6 text-center text-sm text-gray-400 space-y-1">
            <p>&copy; {{ date('Y') }} Games Outbreak. All rights reserved.</p>
            <p>Powered by : <a href="https://www.igdb.com" target="_blank" rel="noopener noreferrer" class="text-orange-400 hover:underline transition">IGDB</a> -
                <a href="https://steamspy.com/" target="_blank" rel="noopener noreferrer" class="text-orange-400 hover:underline transition">steamspy</a>
            </p>
            <p>Made with <span class="text-red-500">❤️</span> by
                <span class="text-orange-400 hover:underline transition">Cláudio Varandas</span>
                using open source technologies.
            </p>
        </div>
    </div>
</footer>


