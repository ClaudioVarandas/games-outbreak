<template>
  <Transition
    enter-active-class="transition ease-out duration-300"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition ease-in duration-200"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="show"
      class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
      @click.self="$emit('close')"
    >
      <div class="bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <!-- Header with mode-based styling -->
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
              <!-- Mode Icon -->
              <div
                class="flex items-center justify-center h-10 w-10 rounded-full"
                :class="modeStyles.iconBg"
              >
                <!-- Star icon for highlight -->
                <svg v-if="mode === 'highlight'" class="h-5 w-5" :class="modeStyles.iconColor" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                </svg>
                <!-- Plus circle icon for indie -->
                <svg v-else-if="mode === 'indie'" class="h-5 w-5" :class="modeStyles.iconColor" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"></path>
                </svg>
                <!-- Pencil icon for edit -->
                <svg v-else-if="mode === 'edit'" class="h-5 w-5" :class="modeStyles.iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                <!-- Plus icon for add -->
                <svg v-else class="h-5 w-5" :class="modeStyles.iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
              </div>
              <h3 class="text-2xl font-bold text-white">{{ modalTitle }}</h3>
            </div>
            <button
              @click="$emit('close')"
              class="text-gray-400 hover:text-white transition"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>

          <!-- Target List Info (for highlight/indie modes) -->
          <div v-if="targetListName && (mode === 'highlight' || mode === 'indie')" class="mb-4 px-3 py-2 rounded-lg" :class="modeStyles.targetBg">
            <span class="text-sm" :class="modeStyles.targetText">Target: {{ targetListName }}</span>
          </div>

          <!-- Game Info -->
          <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-700">
            <img
              :src="game.cover_url || '/images/game-cover-placeholder.svg'"
              alt="Cover"
              class="w-20 h-28 object-cover rounded shadow"
              @error="$event.target.src = '/images/game-cover-placeholder.svg'"
            >
            <div>
              <h4 class="text-xl font-bold text-white mb-1">{{ game.name }}</h4>
              <p class="text-sm text-gray-400">{{ game.release || 'TBA' }}</p>
            </div>
          </div>

          <!-- Form Fields -->
          <form @submit.prevent="handleSubmit" class="space-y-6">
            <!-- Release Date -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">
                Release Date
              </label>
              <input
                v-model="formData.releaseDate"
                type="date"
                :disabled="formData.isTba"
                class="w-full px-4 py-2 rounded-lg bg-gray-700 text-white border border-gray-600 focus:outline-none focus:ring-2 disabled:opacity-50 disabled:cursor-not-allowed"
                :class="modeStyles.focusRing"
              >
              <p class="mt-1 text-xs text-gray-400">
                Default: {{ game.release_date || game.release || 'TBA' }}
              </p>
            </div>

            <!-- Platforms -->
            <div v-if="availablePlatforms.length > 0">
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
                    v-model="formData.platforms"
                    class="rounded border-gray-500 focus:ring-orange-500"
                    :class="modeStyles.checkbox"
                  >
                  <span class="text-sm text-white">{{ platform.label }}</span>
                </label>
              </div>
              <p v-if="game.platforms" class="mt-2 text-xs text-gray-400">
                Default: {{ game.platforms }}
              </p>
            </div>

            <!-- TBA Toggle -->
            <div>
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="formData.isTba"
                  class="rounded border-gray-500"
                  :class="modeStyles.checkbox"
                >
                <span class="text-sm text-gray-300">TBA (To Be Announced)</span>
              </label>
            </div>

            <!-- Genre Selection -->
            <div v-if="availableGenres.length > 0">
              <label class="block text-sm font-medium text-gray-300 mb-2">
                Primary Genre
              </label>
              <select
                v-model="formData.primaryGenreId"
                class="w-full px-4 py-2 rounded-lg bg-gray-700 text-white border border-gray-600 focus:outline-none focus:ring-2"
                :class="modeStyles.focusRing"
              >
                <option value="">Select primary genre...</option>
                <option v-for="genre in availableGenres" :key="genre.id" :value="genre.id">
                  {{ genre.name }}
                </option>
              </select>
              <p v-if="mode === 'highlight' || mode === 'indie'" class="mt-1 text-sm text-gray-400">
                Choose which genre tab this game should appear under.
              </p>
            </div>

            <!-- Secondary Genres -->
            <div v-if="availableGenres.length > 0">
              <label class="block text-sm font-medium text-gray-300 mb-2">
                Additional Genres (max 2)
              </label>
              <select
                ref="genreSelectRef"
                multiple
                class="w-full px-4 py-2 rounded-lg bg-gray-700 text-white border border-gray-600"
              >
                <option v-for="genre in availableGenres" :key="genre.id" :value="genre.id">
                  {{ genre.name }}
                </option>
              </select>
              <p class="mt-1 text-xs text-gray-400">
                Selected: {{ formData.secondaryGenreIds.length }}/2
              </p>
            </div>

            <!-- Form Actions -->
            <div class="flex gap-3 pt-4">
              <button
                type="submit"
                :disabled="submitting || (requireGenre && !formData.primaryGenreId)"
                class="flex-1 disabled:bg-gray-600 disabled:cursor-not-allowed text-white px-6 py-3 rounded-lg font-medium transition"
                :class="modeStyles.submitButton"
              >
                <span v-if="submitting">{{ submittingText }}</span>
                <span v-else>{{ submitText }}</span>
              </button>
              <button
                type="button"
                @click="$emit('close')"
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
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  game: {
    type: Object,
    required: true
  },
  mode: {
    type: String,
    default: 'add',
    validator: (value) => ['add', 'highlight', 'indie', 'edit'].includes(value)
  },
  targetListName: {
    type: String,
    default: ''
  },
  availablePlatforms: {
    type: Array,
    default: () => []
  },
  availableGenres: {
    type: Array,
    default: () => []
  },
  submitting: {
    type: Boolean,
    default: false
  },
  initialReleaseDate: {
    type: String,
    default: ''
  },
  initialPlatforms: {
    type: Array,
    default: () => []
  },
  initialPrimaryGenreId: {
    type: [Number, String],
    default: ''
  },
  initialIsTba: {
    type: Boolean,
    default: false
  },
  initialGenreIds: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['close', 'submit']);

const genreSelectRef = ref(null);
let tomSelectInstance = null;

const formData = ref({
  releaseDate: '',
  platforms: [],
  primaryGenreId: '',
  secondaryGenreIds: [],
  isTba: false
});

const modeStyles = computed(() => {
  switch (props.mode) {
    case 'highlight':
      return {
        iconBg: 'bg-yellow-100 dark:bg-yellow-900',
        iconColor: 'text-yellow-600 dark:text-yellow-400',
        focusRing: 'focus:ring-yellow-500',
        checkbox: 'text-yellow-600 focus:ring-yellow-500',
        submitButton: 'bg-yellow-500 hover:bg-yellow-600',
        targetBg: 'bg-yellow-900/30',
        targetText: 'text-yellow-400'
      };
    case 'indie':
      return {
        iconBg: 'bg-purple-100 dark:bg-purple-900',
        iconColor: 'text-purple-600 dark:text-purple-400',
        focusRing: 'focus:ring-purple-500',
        checkbox: 'text-purple-600 focus:ring-purple-500',
        submitButton: 'bg-purple-500 hover:bg-purple-600',
        targetBg: 'bg-purple-900/30',
        targetText: 'text-purple-400'
      };
    case 'edit':
      return {
        iconBg: 'bg-blue-100 dark:bg-blue-900',
        iconColor: 'text-blue-600 dark:text-blue-400',
        focusRing: 'focus:ring-blue-500',
        checkbox: 'text-blue-600 focus:ring-blue-500',
        submitButton: 'bg-blue-500 hover:bg-blue-600',
        targetBg: 'bg-blue-900/30',
        targetText: 'text-blue-400'
      };
    default:
      return {
        iconBg: 'bg-orange-100 dark:bg-orange-900',
        iconColor: 'text-orange-600 dark:text-orange-400',
        focusRing: 'focus:ring-orange-500',
        checkbox: 'text-orange-600 focus:ring-orange-500',
        submitButton: 'bg-orange-600 hover:bg-orange-700',
        targetBg: 'bg-orange-900/30',
        targetText: 'text-orange-400'
      };
  }
});

const modalTitle = computed(() => {
  switch (props.mode) {
    case 'highlight':
      return 'Add to Highlights';
    case 'indie':
      return 'Mark as Indie';
    case 'edit':
      return 'Edit Game Data';
    default:
      return 'Add Game to List';
  }
});

const submitText = computed(() => {
  switch (props.mode) {
    case 'highlight':
      return 'Confirm';
    case 'indie':
      return 'Confirm';
    case 'edit':
      return 'Save Changes';
    default:
      return 'Add to List';
  }
});

const submittingText = computed(() => {
  switch (props.mode) {
    case 'highlight':
    case 'indie':
      return 'Saving...';
    case 'edit':
      return 'Saving...';
    default:
      return 'Adding...';
  }
});

const requireGenre = computed(() => {
  return (props.mode === 'highlight' || props.mode === 'indie') && props.availableGenres.length > 0;
});

const initTomSelect = () => {
  if (!genreSelectRef.value || !window.TomSelect) return;

  if (tomSelectInstance) {
    tomSelectInstance.destroy();
  }

  tomSelectInstance = new window.TomSelect(genreSelectRef.value, {
    maxItems: 2,
    plugins: ['remove_button'],
    onChange: (values) => {
      formData.value.secondaryGenreIds = values.map(v => parseInt(v));
    }
  });

  // Set initial values if we have any secondary genres
  if (formData.value.secondaryGenreIds.length > 0) {
    formData.value.secondaryGenreIds.forEach(id => {
      tomSelectInstance.addItem(String(id), true);
    });
  }
};

const destroyTomSelect = () => {
  if (tomSelectInstance) {
    tomSelectInstance.destroy();
    tomSelectInstance = null;
  }
};

const resetForm = () => {
  // Calculate secondary genre IDs (all genre IDs except primary)
  const primaryId = props.initialPrimaryGenreId ? parseInt(props.initialPrimaryGenreId) : null;
  const secondaryIds = (props.initialGenreIds || [])
    .map(id => parseInt(id))
    .filter(id => id !== primaryId);

  // Ensure platforms are integers for checkbox v-model matching
  let platformIds = [];
  if (props.initialPlatforms && props.initialPlatforms.length > 0) {
    platformIds = props.initialPlatforms.map(p => parseInt(p, 10));
  } else if (props.game?.platform_ids && props.game.platform_ids.length > 0) {
    platformIds = props.game.platform_ids.map(p => parseInt(p, 10));
  }

  formData.value = {
    releaseDate: props.initialReleaseDate || props.game?.release_date || '',
    platforms: platformIds,
    primaryGenreId: props.initialPrimaryGenreId || '',
    secondaryGenreIds: secondaryIds,
    isTba: props.initialIsTba
  };
};

const handleSubmit = () => {
  const allGenreIds = [];
  if (formData.value.primaryGenreId) {
    allGenreIds.push(parseInt(formData.value.primaryGenreId));
  }
  formData.value.secondaryGenreIds.forEach(id => {
    if (!allGenreIds.includes(id)) {
      allGenreIds.push(id);
    }
  });

  emit('submit', {
    releaseDate: formData.value.isTba ? null : formData.value.releaseDate,
    platforms: formData.value.platforms,
    primaryGenreId: formData.value.primaryGenreId,
    genreIds: allGenreIds,
    isTba: formData.value.isTba
  });
};

watch(() => props.show, async (newVal) => {
  if (newVal) {
    // Wait for next tick to ensure all props are updated
    await nextTick();
    resetForm();
    if (props.availableGenres.length > 0) {
      await nextTick();
      setTimeout(() => initTomSelect(), 100);
    }
  } else {
    destroyTomSelect();
  }
});

watch(() => props.game, () => {
  if (props.show) {
    resetForm();
  }
}, { deep: true });

watch(() => props.initialPlatforms, (val) => {
  if (props.show && val && val.length > 0) {
    formData.value.platforms = val.map(p => parseInt(p, 10));
  }
}, { deep: true });

watch(() => props.initialReleaseDate, (val) => {
  if (props.show && val) {
    formData.value.releaseDate = val;
  }
});

watch(() => props.initialPrimaryGenreId, (val) => {
  if (props.show) {
    formData.value.primaryGenreId = val || '';
  }
});

watch(() => props.initialIsTba, (val) => {
  if (props.show) {
    formData.value.isTba = val;
  }
});

onUnmounted(() => {
  destroyTomSelect();
});
</script>
