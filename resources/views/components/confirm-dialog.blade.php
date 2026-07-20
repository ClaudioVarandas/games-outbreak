<div id="confirm-dialog"
     x-data
     x-show="$store.confirmDialog.open"
     x-cloak
     @keydown.escape.window="$store.confirmDialog.open && $store.confirmDialog.settle(false)"
     class="fixed inset-0 z-[60] flex items-center justify-center bg-black bg-opacity-50"
     @click.self="$store.confirmDialog.settle(false)">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">
        <div class="p-6">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4" x-text="$store.confirmDialog.title"></h3>
            <p class="text-gray-600 dark:text-gray-400 mb-6" x-text="$store.confirmDialog.message"></p>
            <div class="flex gap-3">
                <button type="button"
                        @click="$store.confirmDialog.settle(false)"
                        class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                    Cancel
                </button>
                <button type="button"
                        @click="$store.confirmDialog.settle(true)"
                        class="flex-1 px-4 py-2 text-white rounded-lg transition"
                        :class="$store.confirmDialog.danger ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-600 hover:bg-amber-700'"
                        x-text="$store.confirmDialog.confirmLabel">
                </button>
            </div>
        </div>
    </div>
</div>
