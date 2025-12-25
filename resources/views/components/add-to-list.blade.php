@props(['game'])

@auth
    <div class="bg-gray-800 p-6 rounded-xl" x-data="{ open: false }">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold">Add to List</h3>
            <button @click="open = !open" 
                    class="text-orange-400 hover:text-orange-300 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
            </button>
        </div>

        <div x-show="open" 
             x-transition
             class="space-y-4">
            @php
                $user = auth()->user();
                $backlogList = $user->gameLists()->backlog()->with('games')->first();
                $wishlistList = $user->gameLists()->wishlist()->with('games')->first();
                $regularLists = $user->gameLists()->userLists()->regular()->with('games')->get();
            @endphp

            <!-- Special Lists (Backlog & Wishlist) -->
            @if($backlogList || $wishlistList)
                <div class="space-y-2">
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Special Lists</p>
                    @if($backlogList)
                        @php
                            $isInList = $backlogList->games->contains('id', $game->id);
                        @endphp
                        <form action="{{ $isInList ? route('lists.games.remove', ['gameList' => $backlogList, 'game' => $game]) : route('lists.games.add', $backlogList) }}" 
                              method="POST" 
                              class="flex items-center justify-between p-3 bg-blue-900/30 border border-blue-700/50 rounded-lg hover:bg-blue-900/40 transition">
                            @csrf
                            @if($isInList)
                                @method('DELETE')
                            @endif
                            <input type="hidden" name="game_id" value="{{ $game->id }}">
                            <div class="flex items-center gap-2 flex-1">
                                <svg class="w-4 h-4 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm font-medium text-white">{{ $backlogList->name }}</span>
                            </div>
                            <button type="submit" 
                                    class="px-4 py-1 rounded text-sm transition {{ $isInList ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-blue-600 hover:bg-blue-700 text-white' }}">
                                {{ $isInList ? 'Remove' : 'Add' }}
                            </button>
                        </form>
                    @endif
                    @if($wishlistList)
                        @php
                            $isInList = $wishlistList->games->contains('id', $game->id);
                        @endphp
                        <form action="{{ $isInList ? route('lists.games.remove', ['gameList' => $wishlistList, 'game' => $game]) : route('lists.games.add', $wishlistList) }}" 
                              method="POST" 
                              class="flex items-center justify-between p-3 bg-pink-900/30 border border-pink-700/50 rounded-lg hover:bg-pink-900/40 transition">
                            @csrf
                            @if($isInList)
                                @method('DELETE')
                            @endif
                            <input type="hidden" name="game_id" value="{{ $game->id }}">
                            <div class="flex items-center gap-2 flex-1">
                                <svg class="w-4 h-4 text-pink-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm font-medium text-white">{{ $wishlistList->name }}</span>
                            </div>
                            <button type="submit" 
                                    class="px-4 py-1 rounded text-sm transition {{ $isInList ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-pink-600 hover:bg-pink-700 text-white' }}">
                                {{ $isInList ? 'Remove' : 'Add' }}
                            </button>
                        </form>
                    @endif
                </div>
            @endif

            <!-- Regular Lists -->
            @if($regularLists->count() > 0)
                <div class="space-y-2">
                    @if($backlogList || $wishlistList)
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Your Lists</p>
                    @endif
                    <div class="max-h-60 overflow-y-auto space-y-2">
                        @foreach($regularLists as $list)
                            @php
                                $isInList = $list->games->contains('id', $game->id);
                            @endphp
                            <form action="{{ $isInList ? route('lists.games.remove', ['gameList' => $list, 'game' => $game]) : route('lists.games.add', $list) }}" 
                                  method="POST" 
                                  class="flex items-center justify-between p-3 bg-gray-700 rounded-lg hover:bg-gray-600 transition">
                                @csrf
                                @if($isInList)
                                    @method('DELETE')
                                @endif
                                <input type="hidden" name="game_id" value="{{ $game->id }}">
                                <span class="text-sm font-medium text-white flex-1">{{ $list->name }}</span>
                                <button type="submit" 
                                        class="px-4 py-1 rounded text-sm transition {{ $isInList ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-orange-600 hover:bg-orange-700 text-white' }}">
                                    {{ $isInList ? 'Remove' : 'Add' }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
                <a href="{{ route('lists.create') }}" 
                   class="block text-center text-sm text-orange-400 hover:text-orange-300 transition">
                    + Create New List
                </a>
            @elseif(!$backlogList && !$wishlistList)
                <p class="text-gray-400 text-sm mb-4">You don't have any lists yet.</p>
                <a href="{{ route('lists.create') }}" 
                   class="block w-full bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg text-center transition">
                    Create Your First List
                </a>
            @endif
        </div>
    </div>
@endauth

