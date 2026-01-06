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
                  @click="openAddForm(game)"
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

    <!-- Add Game Form Modal -->
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="showForm && selectedGame"
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
        @click.self="closeForm"
      >
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
          <div class="p-6">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-2xl font-bold text-white">Add Game to List</h3>
              <button
                @click="closeForm"
                class="text-gray-400 hover:text-white transition"
              >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>

            <!-- Game Info -->
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-700">
              <img
                :src="selectedGame.cover_url || '/images/game-cover-placeholder.svg'"
                alt="Cover"
                class="w-20 h-28 object-cover rounded shadow"
                @error="$event.target.src = '/images/game-cover-placeholder.svg'"
              >
              <div>
                <h4 class="text-xl font-bold text-white mb-1">{{ selectedGame.name }}</h4>
                <p class="text-sm text-gray-400">{{ selectedGame.release || 'TBA' }}</p>
              </div>
            </div>

            <!-- Form Fields -->
            <form @submit.prevent="submitAddGame" class="space-y-6">
              <!-- Release Date -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">
                  Release Date
                </label>
                <input
                  v-model="formReleaseDate"
                  type="date"
                  class="w-full px-4 py-2 rounded-lg bg-gray-700 text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-orange-500"
                >
                <p class="mt-1 text-xs text-gray-400">
                  Default: {{ selectedGame.release_date || selectedGame.release || 'TBA' }}
                </p>
              </div>

              <!-- Platforms -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">
                  Platforms
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                  <label
                    v-for="platform in availablePlatforms"
                    :key="platform.id"
                    class="flex items-center gap-2 p-3 rounded-lg bg-gray-700 hover:bg-gray-600 cursor-pointer transition"
                  >
                    <input
                      type="checkbox"
                      :value="platform.id"
                      v-model="formPlatforms"
                      class="rounded border-gray-500 text-orange-600 focus:ring-orange-500"
                    >
                    <span class="text-sm text-white">{{ platform.label }}</span>
                  </label>
                </div>
                <p class="mt-2 text-xs text-gray-400">
                  Default: {{ selectedGame.platforms || 'None' }}
                </p>
              </div>

              <!-- Form Actions -->
              <div class="flex gap-3 pt-4">
                <button
                  type="submit"
                  :disabled="adding"
                  class="flex-1 bg-orange-600 hover:bg-orange-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white px-6 py-3 rounded-lg font-medium transition"
                >
                  <span v-if="adding">Adding...</span>
                  <span v-else>Add to List</span>
                </button>
                <button
                  type="button"
                  @click="closeForm"
                  class="px-6 py-3 rounded-lg bg-gray-700 hover:bg-gray-600 text-white font-medium transition"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
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
  },
  routePrefix: {
    type: String,
    required: true
  },
  availablePlatforms: {
    type: Array,
    default: () => []
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
const showForm = ref(false);
const selectedGame = ref(null);
const formReleaseDate = ref('');
const formPlatforms = ref([]);

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

const openAddForm = (game) => {
  selectedGame.value = game;
  formReleaseDate.value = game.release_date || '';
  formPlatforms.value = game.platform_ids ? [...game.platform_ids] : [];
  showForm.value = true;
};

const closeForm = () => {
  showForm.value = false;
  selectedGame.value = null;
  formReleaseDate.value = '';
  formPlatforms.value = [];
};

const submitAddGame = async () => {
  if (adding.value || !selectedGame.value) return;
  
  adding.value = true;
  addingGameId.value = selectedGame.value.igdb_id;

  try {
    const formData = new FormData();
    formData.append('game_id', selectedGame.value.igdb_id);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
    
    if (formReleaseDate.value) {
      formData.append('release_date', formReleaseDate.value);
    }
    
    if (formPlatforms.value.length > 0) {
      formData.append('platforms', JSON.stringify(formPlatforms.value));
    }

    const response = await fetch(props.routePrefix, {
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
      closeForm();
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
        closeForm();
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

