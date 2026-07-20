<div id="toast-container"
     x-data
     class="fixed bottom-6 right-6 z-[70] flex flex-col gap-2 items-end pointer-events-none"
     aria-live="polite">
    <template x-for="item in $store.toasts.items" :key="item.id">
        <div @click="$store.toasts.dismiss(item.id)"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="pointer-events-auto cursor-pointer max-w-sm px-4 py-3 rounded-lg shadow-lg text-sm font-medium text-white"
             :class="{
                 'bg-green-600': item.type === 'success',
                 'bg-blue-600': item.type === 'info',
                 'bg-red-600': item.type === 'error',
             }">
            <span x-text="item.message"></span>
        </div>
    </template>
</div>
