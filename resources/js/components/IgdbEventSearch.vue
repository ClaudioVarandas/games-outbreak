<template>
  <div class="relative" ref="root">
    <!-- IGDB Event ID + search button -->
    <div class="flex gap-2">
      <input
        v-model="selectedId"
        type="text"
        inputmode="numeric"
        name="igdb_event_id"
        placeholder="e.g. 251"
        class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
      >
      <button
        type="button"
        @click="search"
        :disabled="loading"
        title="Search IGDB using the list name"
        class="shrink-0 px-3 py-2 text-sm rounded-lg bg-orange-500 text-white hover:bg-orange-600 disabled:opacity-50"
      >
        <span v-if="loading">…</span>
        <span v-else>Search</span>
      </button>
    </div>

    <input type="hidden" name="igdb_slug" :value="selectedSlug ?? ''">

    <p v-if="selectedName" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
      Linked: {{ selectedName }}
    </p>

    <!-- Results dropdown -->
    <div
      v-show="open && (loading || results.length > 0 || searched)"
      class="absolute z-30 mt-1 w-80 max-w-[90vw] rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800"
    >
      <div v-if="loading" class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">Searching…</div>
      <ul v-else-if="results.length > 0" class="max-h-60 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
        <li v-for="event in results" :key="event.id">
          <button
            type="button"
            @click="pick(event)"
            class="w-full px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <span class="block font-medium text-gray-900 dark:text-white">{{ event.name }}</span>
            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ event.date || 'TBA' }} · #{{ event.id }}</span>
          </button>
        </li>
      </ul>
      <div v-else class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">
        No matches for "{{ lastQuery }}". Adjust the list name and try again.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  searchUrl: { type: String, required: true },
  nameFieldId: { type: String, default: 'name' },
  initialEventId: { type: [Number, String], default: null },
  initialSlug: { type: String, default: '' },
  initialName: { type: String, default: '' },
});

const selectedId = ref(props.initialEventId || '');
const selectedSlug = ref(props.initialSlug || '');
const selectedName = ref(props.initialName || '');
const results = ref([]);
const loading = ref(false);
const open = ref(false);
const searched = ref(false);
const lastQuery = ref('');
const root = ref(null);

const listName = () => {
  const field = document.getElementById(props.nameFieldId);
  return field ? field.value.trim() : '';
};

const search = async () => {
  const query = listName();
  lastQuery.value = query;
  open.value = true;
  searched.value = true;
  results.value = [];

  if (query.length < 2) {
    return;
  }

  loading.value = true;
  searched.value = false;

  try {
    const response = await fetch(`${props.searchUrl}?q=${encodeURIComponent(query)}`, {
      headers: { Accept: 'application/json' },
    });
    results.value = response.ok ? ((await response.json()).results || []) : [];
  } catch (err) {
    console.error('IGDB event search failed:', err);
    results.value = [];
  } finally {
    loading.value = false;
    searched.value = true;
  }
};

const pick = (event) => {
  selectedId.value = event.id;
  selectedSlug.value = event.slug || '';
  selectedName.value = event.name || '';
  open.value = false;
  results.value = [];
};

const onClickOutside = (event) => {
  if (root.value && !root.value.contains(event.target)) {
    open.value = false;
  }
};

onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>
