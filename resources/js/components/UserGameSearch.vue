<template>
    <div class="relative" ref="container">
        <!-- Search Input -->
        <div class="relative">
            <input
                v-model="query"
                @input="debouncedSearch"
                @focus="isOpen = results.length > 0"
                type="text"
                placeholder="Search and add a game..."
                class="w-full bg-gray-800 border border-gray-700 rounded-lg pl-10 pr-4 py-2.5 text-gray-100 placeholder-gray-500 focus:border-orange-500 focus:ring-orange-500 text-sm"
            >
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <svg v-if="loading" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 animate-spin text-orange-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        <!-- Results Dropdown -->
        <div v-if="isOpen && results.length > 0"
             class="absolute top-full left-0 right-0 mt-1 bg-gray-800 border border-gray-700 rounded-lg shadow-xl z-40 max-h-80 overflow-y-auto">
            <div v-for="game in results" :key="game.igdb_id"
                 class="flex items-center gap-3 px-3 py-2 hover:bg-gray-700/50 transition cursor-pointer border-b border-gray-700/50 last:border-0"
                 @click="addGame(game)">
                <img v-if="game.cover_url"
                     :src="game.cover_url"
                     :alt="game.name"
                     class="w-8 h-11 rounded object-cover flex-shrink-0">
                <div v-else class="w-8 h-11 rounded bg-gray-700 flex-shrink-0"></div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-200 truncate">{{ game.name }}</div>
                    <div class="text-xs text-gray-500">{{ game.release || 'TBA' }} &middot; {{ game.platforms }}</div>
                </div>
                <span v-if="game.game_type_label && game.game_type_label !== 'Main'"
                      class="text-xs px-1.5 py-0.5 rounded bg-gray-700 text-gray-400 flex-shrink-0">
                    {{ game.game_type_label }}
                </span>
                <button class="text-orange-500 hover:text-orange-400 text-xs font-medium flex-shrink-0"
                        :disabled="game.adding">
                    <span v-if="game.adding">Adding...</span>
                    <span v-else>+ Add</span>
                </button>
            </div>
        </div>

        <!-- No Results -->
        <div v-if="isOpen && !loading && query.length >= 2 && results.length === 0"
             class="absolute top-full left-0 right-0 mt-1 bg-gray-800 border border-gray-700 rounded-lg shadow-xl z-40 p-4 text-center text-gray-500 text-sm">
            No games found for "{{ query }}"
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    routeUrl: { type: String, required: true },
    defaultStatus: { type: String, default: 'backlog' },
    csrfToken: { type: String, required: true },
});

const query = ref('');
const results = ref([]);
const loading = ref(false);
const isOpen = ref(false);
const container = ref(null);
let debounceTimer = null;

function debouncedSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(search, 300);
}

async function search() {
    if (query.value.length < 2) {
        results.value = [];
        isOpen.value = false;
        return;
    }

    loading.value = true;
    isOpen.value = true;

    try {
        const response = await fetch(`/api/search?q=${encodeURIComponent(query.value)}`);
        if (response.ok) {
            results.value = (await response.json()).map(g => ({ ...g, adding: false }));
        } else {
            results.value = [];
        }
    } catch (err) {
        console.error('Search failed:', err);
        results.value = [];
    } finally {
        loading.value = false;
    }
}

async function addGame(game) {
    if (game.adding) return;
    game.adding = true;

    try {
        const formData = new FormData();
        formData.append('_token', props.csrfToken);
        formData.append('game_id', game.igdb_id);
        formData.append('status', props.defaultStatus);

        const response = await fetch(props.routeUrl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: formData,
        });

        const data = await response.json();
        if (data.success || data.info) {
            // Remove from results
            results.value = results.value.filter(g => g.igdb_id !== game.igdb_id);
            if (results.value.length === 0) {
                isOpen.value = false;
                query.value = '';
            }
            // Reload to show new game
            setTimeout(() => window.location.reload(), 500);
        }
    } catch (err) {
        console.error('Failed to add game:', err);
    } finally {
        game.adding = false;
    }
}

function handleClickOutside(e) {
    if (container.value && !container.value.contains(e.target)) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>