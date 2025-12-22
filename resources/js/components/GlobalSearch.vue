<template>
  <div class="relative w-full" ref="searchContainer">
    <form @submit.prevent="submitSearch" class="relative">
      <input
        v-model="query"
        @focus="openDropdown"
        @input="debouncedSearch"
        type="text"
        class="w-full px-6 py-3 text-base rounded-lg bg-gray-700 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500 transition"
        placeholder="Search games..."
        autocomplete="off"
        ref="input"
      >
      <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
      </button>
    </form>

    <!-- Dropdown -->
    <div
      v-show="isOpen"
      class="absolute top-full left-0 right-0 mt-2 bg-gray-800 rounded-lg shadow-2xl overflow-hidden z-50 border border-gray-700"
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
      <template v-else-if="results.length > 0">
        <a
          v-for="game in results"
          :key="game.igdb_id"
          :href="`/game/${game.igdb_id}`"
          @click="closeDropdown"
          class="flex items-center gap-4 px-4 py-3 hover:bg-gray-700 transition border-b border-gray-700 last:border-0"
        >
          <img
            :src="game.cover_url || 'https://via.placeholder.com/80x100/1f2937/6b7280?text=No+Cover'"
            alt="Cover"
            class="w-12 h-16 object-cover rounded shadow"
          >
          <div class="flex-1">
            <div class="font-medium text-white truncate">{{ game.name }}</div>
            <div class="text-xs text-gray-400">
              {{ game.release || 'TBA' }}
              <span v-if="game.platforms"> • {{ game.platforms }}</span>
            </div>
            <div v-if="checkAndLogBadge(game)" class="mt-1">
              <span :class="[getBadgeColor(game.game_type), 'px-2 py-0.5 text-xs font-medium rounded text-white']">
                {{ game.game_type_label }}
              </span>
            </div>
          </div>
        </a>
      </template>

      <!-- No Results -->
      <div v-else-if="query.length >= 2" class="px-4 py-8 text-center text-gray-400">
        No games found for "{{ query }}"
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';

const query = ref('');
const results = ref([]);
const loading = ref(false);
const isOpen = ref(false);
const input = ref(null);
const searchContainer = ref(null);

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
  console.log("Component mounted... yeah!");
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

// Custom debounce (no lodash!)
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
  openDropdown();

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

const checkAndLogBadge = (game) => {
  return !!game.game_type_label;
};

const getBadgeColor = (gameType) => {
  const colorMap = {
    0: 'bg-green-600/80',      // MAIN
    1: 'bg-orange-600/80',     // DLC
    2: 'bg-teal-600/80',       // Expansion
    4: 'bg-yellow-600/80',     // Standalone
    8: 'bg-red-600/80',        // Remake
    9: 'bg-yellow-500/80',      // Remaster
    10: 'bg-purple-600/80',     // Expanded
  };
  return colorMap[gameType] || 'bg-gray-600/80';
};

const submitSearch = () => {
  if (query.value) {
    window.location.href = `/search?q=${encodeURIComponent(query.value)}`;
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
