@props(['game'])

@auth
    <div class="bg-gray-800 p-6 rounded-xl" x-data="{ open: false }">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold">Add to List</h3>
            <button @click="open = !open" 
                    class="text-teal-400 hover:text-teal-300 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
            </button>
        </div>

        <div x-show="open" 
             x-transition
             class="space-y-4">
            @php
                $userLists = auth()->user()->gameLists()->userLists()->with('games')->get();
            @endphp

            @if($userLists->count() > 0)
                <div class="space-y-2 max-h-60 overflow-y-auto">
                    @foreach($userLists as $list)
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
                                    class="px-4 py-1 rounded text-sm transition {{ $isInList ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-teal-600 hover:bg-teal-700 text-white' }}">
                                {{ $isInList ? 'Remove' : 'Add' }}
                            </button>
                        </form>
                    @endforeach
                </div>
                <a href="{{ route('lists.create') }}" 
                   class="block text-center text-sm text-teal-400 hover:text-teal-300 transition">
                    + Create New List
                </a>
            @else
                <p class="text-gray-400 text-sm mb-4">You don't have any lists yet.</p>
                <a href="{{ route('lists.create') }}" 
                   class="block w-full bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg text-center transition">
                    Create Your First List
                </a>
            @endif
        </div>
    </div>
@endauth

