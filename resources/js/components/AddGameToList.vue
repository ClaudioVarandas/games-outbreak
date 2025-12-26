<template>
  <div class="relative w-full" ref="searchContainer">
    <form @submit.prevent="submitSearch" class="relative">
      <input
        v-model="query"
        @focus="openDropdown"
        @input="debouncedSearch"
        type="text"
        class="w-full px-4 py-2 rounded-lg bg-gray-700 dark:bg-gray-600 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 transition"
        placeholder="Search games to add..."
        autocomplete="off"
        ref="input"
      >
      <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
      </button>
    </form>

    <!-- Dropdown -->
    <div
      v-show="isOpen && (loading || results.length > 0 || (query.length >= 2 && !loading))"
      class="absolute top-full left-0 right-0 mt-2 bg-gray-800 dark:bg-gray-700 rounded-lg shadow-2xl overflow-hidden z-50 border border-gray-700 max-h-96 overflow-y-auto"
      v-cloak
      @click.stop
    >
      <!-- Loading -->
      <div v-if="loading" class="px-4 py-8 text-center text-gray-400">
        <svg class="inline-block w-6 h-6 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        <span class="ml-2">Searching...</span>
      </div>

      <!-- Results -->
      <div v-else-if="results.length > 0" class="divide-y divide-gray-700">
        <div
          v-for="game in results"
          :key="game.igdb_id"
          class="flex items-center gap-4 px-4 py-3 hover:bg-gray-700 transition"
        >
          <img
            :src="game.cover_url || '/images/game-cover-placeholder.svg'"
            alt="Cover"
            class="w-12 h-16 object-cover rounded shadow"
            @error="$event.target.src = '/images/game-cover-placeholder.svg'"
          >
          <div class="flex-1">
            <div class="font-medium text-white truncate">{{ game.name }}</div>
            <div class="text-xs text-gray-400">
              {{ game.release || 'TBA' }}
              <span v-if="game.platforms"> • {{ game.platforms }}</span>
            </div>
            <div v-if="shouldShowBadge(game)" class="mt-1">
              <span :class="[getBadgeColor(game.game_type), 'px-2 py-0.5 text-xs font-medium rounded text-white']">
                {{ game.game_type_label }}
              </span>
            </div>
          </div>
          <button 
                  type="button"
                  @click="addGame(game.igdb_id)"
                  :disabled="adding"
                  class="bg-orange-600 hover:bg-orange-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white px-4 py-2 rounded text-sm transition">
            <span v-if="adding && addingGameId === game.igdb_id">Adding...</span>
            <span v-else>Add</span>
          </button>
        </div>
      </div>

      <!-- No Results -->
      <div v-else-if="query.length >= 2" class="px-4 py-8 text-center text-gray-400">
        No games found for "{{ query }}"
      </div>
    </div>

    <!-- Notification Toast -->
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="opacity-0 translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="notification.show"
        class="fixed bottom-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg max-w-sm"
        :class="{
          'bg-green-600 text-white': notification.type === 'success',
          'bg-blue-600 text-white': notification.type === 'info',
          'bg-red-600 text-white': notification.type === 'error'
        }"
      >
        <p>{{ notification.message }}</p>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  listId: {
    type: [Number, String],
    required: true
  }
});

const query = ref('');
const results = ref([]);
const loading = ref(false);
const isOpen = ref(false);
const input = ref(null);
const searchContainer = ref(null);
const adding = ref(false);
const addingGameId = ref(null);
const notification = ref({ show: false, message: '', type: 'success' });

const showNotification = (message, type = 'success') => {
  notification.value = { show: true, message, type };
  setTimeout(() => {
    notification.value.show = false;
  }, 3000);
};

const openDropdown = () => {
  isOpen.value = true;
};

const closeDropdown = () => {
  isOpen.value = false;
};

// Handle click outside
const handleClickOutside = (event) => {
  if (searchContainer.value && !searchContainer.value.contains(event.target)) {
    closeDropdown();
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

// Custom debounce
const debounce = (fn, delay = 300) => {
  let timeout;
  return (...args) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => fn(...args), delay);
  };
};

const debouncedSearch = debounce(async () => {
  if (query.value.length < 2) {
    results.value = [];
    closeDropdown();
    return;
  }

  loading.value = true;
  isOpen.value = true;

  try {
    const response = await fetch(`/api/search?q=${encodeURIComponent(query.value)}`);
    if (response.ok) {
      results.value = await response.json();
    } else {
      results.value = [];
    }
  } catch (err) {
    console.error('Search failed:', err);
    results.value = [];
  } finally {
    loading.value = false;
  }
}, 300);

const shouldShowBadge = (game) => {
  return !!game.game_type_label;
};

const getBadgeColor = (gameType) => {
  const colorMap = {
    0: 'bg-orange-600/80',      // MAIN
    1: 'bg-orange-600/80',     // DLC
    2: 'bg-orange-600/80',       // Expansion
    4: 'bg-yellow-600/80',     // Standalone
    8: 'bg-red-600/80',        // Remake
    9: 'bg-yellow-500/80',      // Remaster
    10: 'bg-purple-600/80',     // Expanded
  };
  return colorMap[gameType] || 'bg-gray-600/80';
};

const submitSearch = () => {
  if (query.value) {
    debouncedSearch();
  }
};

const addGame = async (gameId) => {
  if (adding.value) return;
  
  adding.value = true;
  addingGameId.value = gameId;

  try {
    const formData = new FormData();
    formData.append('game_id', gameId);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

    const response = await fetch(`/lists/${props.listId}/games`, {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    });

    let data;
    try {
      data = await response.json();
    } catch (e) {
      // If response is not JSON (e.g., redirect), reload page
      if (response.ok || response.redirected) {
        window.location.reload();
        return;
      }
      showNotification('Failed to add game to list', 'error');
      adding.value = false;
      addingGameId.value = null;
      return;
    }

    if (response.ok) {
      if (data.success) {
        // Reload page to show updated list
        window.location.reload();
      } else if (data.info) {
        // Game already in list
        showNotification(data.info, 'info');
        adding.value = false;
        addingGameId.value = null;
      }
    } else {
      // Error response
      showNotification(data.error || data.message || 'Failed to add game to list', 'error');
      adding.value = false;
      addingGameId.value = null;
    }
  } catch (err) {
    console.error('Add game failed:', err);
    showNotification('Failed to add game to list', 'error');
    adding.value = false;
    addingGameId.value = null;
  }
};

// Close on escape
watch(query, (newVal) => {
  if (!newVal) closeDropdown();
});
</script>

<style scoped>
[v-cloak] { display: none; }
</style>

