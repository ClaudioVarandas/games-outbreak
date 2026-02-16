{{-- Time Played --}}
<div>
    <label class="block text-sm font-medium text-gray-400 mb-2">
        Time Played
        <span x-show="timePlayed" class="ml-1 font-bold text-white" x-text="timePlayed ? timePlayed + 'h' : ''"></span>
    </label>
    <div class="flex justify-center mb-2">
        <input type="number"
               x-model="timePlayed"
               min="0"
               max="99999"
               step="0.5"
               placeholder="0"
               class="w-24 bg-gray-700 border border-gray-600 rounded-lg px-3 py-1.5 text-white text-sm text-center focus:ring-orange-500 focus:border-orange-500">
    </div>
    <div class="flex items-center gap-2">
        <button @click="decrementTime()"
                class="p-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-lg transition flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
        </button>
        <input type="range"
               x-model="timePlayed"
               min="0"
               max="500"
               step="0.5"
               class="flex-1 h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-orange-500">
        <button @click="incrementTime()"
                class="p-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-lg transition flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        </button>
    </div>
</div>

{{-- Rating --}}
<div>
    <label class="block text-sm font-medium text-gray-400 mb-2">
        Rating
        <span x-show="rating" :class="getRatingColor()" class="ml-1 font-bold" x-text="rating ? rating + '/100' : ''"></span>
    </label>
    <div class="flex justify-center mb-2">
        <input type="number"
               x-model="rating"
               min="0"
               max="100"
               placeholder="-"
               class="w-24 bg-gray-700 border border-gray-600 rounded-lg px-3 py-1.5 text-white text-sm text-center focus:ring-orange-500 focus:border-orange-500">
    </div>
    <div class="flex items-center gap-2">
        <button @click="decrementRating()"
                class="p-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-lg transition flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
        </button>
        <input type="range"
               x-model="rating"
               min="0"
               max="100"
               step="1"
               class="flex-1 h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-orange-500">
        <button @click="incrementRating()"
                class="p-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-lg transition flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        </button>
    </div>
</div>
