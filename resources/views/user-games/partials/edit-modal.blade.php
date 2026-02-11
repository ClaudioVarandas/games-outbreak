{{-- Edit Game Modal (Desktop: centered modal, Mobile: bottom sheet) --}}
<div x-data="userGameEditModal()"
     x-show="open"
     x-cloak
     @open-user-game-edit.window="openModal($event.detail)"
     @keydown.escape.window="closeModal()"
     class="fixed inset-0 z-50"
     style="display: none;">

    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity"
         @click="closeModal()"
         x-show="open"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
    </div>

    {{-- Desktop Modal --}}
    <div class="hidden md:flex fixed inset-0 items-center justify-center p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-md bg-gray-800 rounded-2xl shadow-2xl border border-gray-700 overflow-hidden"
             x-show="open"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4"
             @click.stop>

            {{-- Header --}}
            <div class="relative flex items-center gap-4 p-5 border-b border-gray-700">
                <img :src="gameCover" :alt="gameName"
                     class="w-16 h-20 rounded-lg object-cover flex-shrink-0 bg-gray-700"
                     onerror="this.style.display='none'">
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-bold text-white truncate" x-text="gameName"></h3>
                    <a :href="'/game/' + gameSlug"
                       class="text-sm text-orange-400 hover:text-orange-300 transition flex items-center gap-1 mt-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                        </svg>
                        View game page
                    </a>
                </div>
                <button @click="closeModal()" class="absolute top-3 right-3 p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-5 space-y-5 max-h-[60vh] overflow-y-auto">
                @include('user-games.partials.edit-fields')
            </div>

            {{-- Footer --}}
            <div class="p-4 border-t border-gray-700 flex items-center justify-between">
                <div x-show="!confirmingDelete">
                    <button @click="confirmingDelete = true"
                            class="text-sm text-red-400 hover:text-red-300 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                        </svg>
                        Remove
                    </button>
                </div>
                <div x-show="confirmingDelete" class="flex items-center gap-3">
                    <span class="text-sm text-red-400">Remove from collection?</span>
                    <button @click="removeGame()"
                            :disabled="deleting"
                            class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition disabled:opacity-50">
                        <span x-show="!deleting">Yes</span>
                        <span x-show="deleting">...</span>
                    </button>
                    <button @click="confirmingDelete = false" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition">
                        No
                    </button>
                </div>

                {{-- Notification toast --}}
                <div x-show="notification" x-transition class="text-sm" :class="notification?.isError ? 'text-red-400' : 'text-green-400'" x-text="notification?.message"></div>
            </div>
        </div>
    </div>

    {{-- Mobile Bottom Sheet --}}
    <div class="md:hidden fixed inset-x-0 bottom-0 pointer-events-none">
        <div class="pointer-events-auto bg-gray-800 rounded-t-2xl shadow-2xl border-t border-gray-700 max-h-[85vh] overflow-hidden"
             x-show="open"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full"
             @click.stop>

            {{-- Drag handle --}}
            <div class="flex justify-center pt-3 pb-1" @click="closeModal()">
                <div class="w-10 h-1 bg-gray-600 rounded-full"></div>
            </div>

            {{-- Header --}}
            <div class="flex items-center gap-3 px-5 pb-4 border-b border-gray-700">
                <img :src="gameCover" :alt="gameName"
                     class="w-12 h-16 rounded-lg object-cover flex-shrink-0 bg-gray-700"
                     onerror="this.style.display='none'">
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-bold text-white truncate" x-text="gameName"></h3>
                    <a :href="'/game/' + gameSlug"
                       class="text-sm text-orange-400 hover:text-orange-300 transition">
                        View game page &rarr;
                    </a>
                </div>
            </div>

            {{-- Body --}}
            <div class="p-5 space-y-5 overflow-y-auto" style="max-height: calc(85vh - 180px);">
                @include('user-games.partials.edit-fields')
            </div>

            {{-- Footer --}}
            <div class="p-4 border-t border-gray-700 safe-bottom flex items-center justify-between">
                <div x-show="!confirmingDelete">
                    <button @click="confirmingDelete = true"
                            class="text-sm text-red-400 hover:text-red-300 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                        </svg>
                        Remove
                    </button>
                </div>
                <div x-show="confirmingDelete" class="flex items-center gap-3">
                    <span class="text-sm text-red-400">Remove?</span>
                    <button @click="removeGame()" :disabled="deleting"
                            class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition disabled:opacity-50">
                        Yes
                    </button>
                    <button @click="confirmingDelete = false"
                            class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition">
                        No
                    </button>
                </div>

                <div x-show="notification" x-transition class="text-sm" :class="notification?.isError ? 'text-red-400' : 'text-green-400'" x-text="notification?.message"></div>
            </div>
        </div>
    </div>
</div>
