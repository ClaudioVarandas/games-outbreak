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
            <div class="relative p-5 border-b border-gray-700">
                <div class="flex items-center gap-3 pr-8">
                    <img x-show="gameCover" :src="gameCover" :alt="gameName"
                         class="w-14 h-[4.5rem] rounded-lg object-cover flex-shrink-0 bg-gray-700"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                    <div x-show="!gameCover" class="w-14 h-[4.5rem] rounded-lg bg-gray-700 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.491 48.491 0 01-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 01-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 00-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 01-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 00.657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 01-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 005.427-.63 48.05 48.05 0 00.582-4.717.532.532 0 00-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 00.658-.663 48.422 48.422 0 00-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 01-.61-.58v0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-bold text-white truncate" x-text="gameName"></h3>
                            <a :href="'/game/' + gameSlug"
                               class="flex-shrink-0 text-gray-400 hover:text-orange-400 transition"
                               title="View game page">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
                <button @click="closeModal()" class="absolute top-3 right-3 p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                {{-- Status buttons + Wishlist toggle --}}
                <div class="flex items-center gap-2 mt-4">
                    @include('user-games.partials.edit-status-buttons')
                </div>
            </div>

            {{-- Body --}}
            <div class="p-5 space-y-5 max-h-[60vh] overflow-y-auto">
                @include('user-games.partials.edit-fields')
            </div>

            {{-- Footer --}}
            <div class="p-4 border-t border-gray-700 flex items-center justify-between gap-3">
                <div x-show="!confirmingDelete" class="flex-shrink-0">
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
                <div x-show="notification" x-transition class="text-sm flex-1 text-center" :class="notification?.isError ? 'text-red-400' : 'text-green-400'" x-text="notification?.message"></div>

                {{-- Save button --}}
                <button @click="saveAll()"
                        :disabled="saving"
                        class="px-5 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition disabled:opacity-50 flex-shrink-0">
                    <span x-show="!saving">Save</span>
                    <span x-show="saving">Saving...</span>
                </button>
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
            <div class="px-5 pb-4 border-b border-gray-700">
                <div class="flex items-center gap-3">
                    <img :src="gameCover" :alt="gameName"
                         class="w-12 h-16 rounded-lg object-cover flex-shrink-0 bg-gray-700"
                         onerror="this.style.display='none'">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-white truncate" x-text="gameName"></h3>
                            <a :href="'/game/' + gameSlug"
                               class="flex-shrink-0 text-gray-400 hover:text-orange-400 transition"
                               title="View game page">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Status buttons + Wishlist toggle --}}
                <div class="flex items-center gap-2 mt-3">
                    @include('user-games.partials.edit-status-buttons')
                </div>
            </div>

            {{-- Body --}}
            <div class="p-5 space-y-5 overflow-y-auto" style="max-height: calc(85vh - 240px);">
                @include('user-games.partials.edit-fields')
            </div>

            {{-- Footer --}}
            <div class="p-4 border-t border-gray-700 safe-bottom flex items-center justify-between gap-3">
                <div x-show="!confirmingDelete" class="flex-shrink-0">
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

                <div x-show="notification" x-transition class="text-sm flex-1 text-center" :class="notification?.isError ? 'text-red-400' : 'text-green-400'" x-text="notification?.message"></div>

                {{-- Save button --}}
                <button @click="saveAll()"
                        :disabled="saving"
                        class="px-5 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition disabled:opacity-50 flex-shrink-0">
                    <span x-show="!saving">Save</span>
                    <span x-show="saving">Saving...</span>
                </button>
            </div>
        </div>
    </div>
</div>
