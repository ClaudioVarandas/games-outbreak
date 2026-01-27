<template>
  <GameFormModal
    :show="showModal"
    :game="gameData"
    :mode="modalMode"
    :target-list-name="targetListName"
    :available-platforms="availablePlatforms"
    :available-genres="availableGenres"
    :submitting="submitting"
    :initial-release-date="initialReleaseDate"
    :initial-primary-genre-id="initialPrimaryGenreId"
    :initial-is-tba="initialIsTba"
    :initial-platforms="initialPlatforms"
    :initial-genre-ids="initialGenreIds"
    @close="closeModal"
    @submit="handleSubmit"
  />

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
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import GameFormModal from './GameFormModal.vue';

const props = defineProps({
  listType: {
    type: String,
    required: true
  },
  listSlug: {
    type: String,
    required: true
  },
  listName: {
    type: String,
    default: ''
  },
  toggleHighlightUrl: {
    type: String,
    required: true
  },
  toggleIndieUrl: {
    type: String,
    required: true
  },
  getGenresUrl: {
    type: String,
    required: true
  },
  updatePivotUrl: {
    type: String,
    default: ''
  },
  csrfToken: {
    type: String,
    required: true
  },
  availablePlatforms: {
    type: Array,
    default: () => []
  },
  systemGenres: {
    type: Array,
    default: () => []
  }
});

const showModal = ref(false);
const modalMode = ref('highlight');
const gameData = ref({ name: '', cover_url: '', release: '' });
const availableGenres = ref([]);
const submitting = ref(false);
const currentGameId = ref(null);
const targetListName = ref('');
const initialReleaseDate = ref('');
const initialPrimaryGenreId = ref('');
const initialIsTba = ref(false);
const initialPlatforms = ref([]);
const initialGenreIds = ref([]);

const notification = ref({ show: false, message: '', type: 'success' });

const showNotification = (message, type = 'success') => {
  notification.value = { show: true, message, type };
  setTimeout(() => {
    notification.value.show = false;
  }, 3000);
};

const openModal = async (event) => {
  const { gameId, mode, isActive, gameName, gameCover, gameRelease } = event.detail;

  currentGameId.value = gameId;
  modalMode.value = mode;
  targetListName.value = props.listName;

  // Set basic game data from event
  gameData.value = {
    name: gameName || 'Loading...',
    cover_url: gameCover || '',
    release: gameRelease || 'TBA'
  };

  // If already active, toggle off without modal (skip for edit mode)
  if (mode !== 'edit' && (isActive === 'true' || isActive === true)) {
    await performToggle(gameId, mode, false);
    return;
  }

  // Fetch game data (platforms, release date, etc.)
  try {
    const url = props.getGenresUrl.replace('__GAME_ID__', gameId);
    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Accept': 'application/json'
      }
    });

    const data = await response.json();
    if (data.igdb_genres !== undefined) {
      // Use system genres instead of IGDB genres for consistency with "Add Game" modal
      availableGenres.value = props.systemGenres;
      initialPrimaryGenreId.value = data.primary_genre_id || '';
      initialReleaseDate.value = data.release_date ? data.release_date.split('T')[0] : '';
      initialIsTba.value = data.is_tba || false;
      initialGenreIds.value = data.genre_ids || [];

      // Parse platforms from game data and ensure they are integers
      let platforms = data.platforms || [];
      if (typeof platforms === 'string') {
        try {
          platforms = JSON.parse(platforms);
        } catch (e) {
          platforms = [];
        }
      }
      // Ensure all platform IDs are integers for checkbox matching
      initialPlatforms.value = Array.isArray(platforms) ? platforms.map(p => parseInt(p, 10)) : [];

      // Update game data with more details
      if (data.game_name) {
        gameData.value.name = data.game_name;
      }
      if (data.cover_url) {
        gameData.value.cover_url = data.cover_url;
      }

      showModal.value = true;
    } else {
      showNotification(data.error || 'Failed to fetch game data', 'error');
    }
  } catch (error) {
    console.error('Fetch game data error:', error);
    showNotification('Failed to fetch game data. Please try again.', 'error');
  }
};

const closeModal = () => {
  showModal.value = false;
  currentGameId.value = null;
  availableGenres.value = [];
  initialReleaseDate.value = '';
  initialPrimaryGenreId.value = '';
  initialIsTba.value = false;
  initialPlatforms.value = [];
  initialGenreIds.value = [];
};

const handleSubmit = async (formData) => {
  if (submitting.value) return;

  submitting.value = true;

  try {
    if (modalMode.value === 'edit') {
      await performEdit(currentGameId.value, formData);
    } else {
      await performToggle(currentGameId.value, modalMode.value, true, formData);
    }
    closeModal();
  } catch (error) {
    console.error('Submit error:', error);
    showNotification('Failed to update game. Please try again.', 'error');
  } finally {
    submitting.value = false;
  }
};

const performEdit = async (gameId, formData) => {
  if (!props.updatePivotUrl) {
    showNotification('Edit URL not configured.', 'error');
    return;
  }

  const url = props.updatePivotUrl.replace('__GAME_ID__', gameId);

  const body = {
    _method: 'PATCH',
    release_date: formData.releaseDate || null,
    is_tba: formData.isTba,
    platforms: formData.platforms || [],
    primary_genre_id: formData.primaryGenreId || null,
    genre_ids: formData.genreIds || [],
  };

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': props.csrfToken,
        'X-HTTP-Method-Override': 'PATCH',
        'Accept': 'application/json'
      },
      body: JSON.stringify(body)
    });

    const data = await response.json();
    if (data.success) {
      window.location.reload();
    } else {
      showNotification(data.error || 'Failed to update game data', 'error');
    }
  } catch (error) {
    console.error('Edit error:', error);
    showNotification('Failed to update game data. Please try again.', 'error');
  }
};

const performToggle = async (gameId, mode, turnOn, formData = null) => {
  const url = mode === 'indie'
    ? props.toggleIndieUrl.replace('__GAME_ID__', gameId)
    : props.toggleHighlightUrl.replace('__GAME_ID__', gameId);

  const body = {
    _method: 'PATCH'
  };

  if (turnOn && formData) {
    body.release_date = formData.releaseDate || null;
    body.is_tba = formData.isTba;
    body.primary_genre_id = formData.primaryGenreId;
    if (formData.platforms && formData.platforms.length > 0) {
      body.platforms = formData.platforms;
    }
    if (formData.genreIds && formData.genreIds.length > 0) {
      body.genre_ids = formData.genreIds;
    }
  }

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': props.csrfToken,
        'X-HTTP-Method-Override': 'PATCH',
        'Accept': 'application/json'
      },
      body: JSON.stringify(body)
    });

    const data = await response.json();
    if (data.success) {
      window.location.reload();
    } else {
      showNotification(data.error || 'Failed to update game status', 'error');
    }
  } catch (error) {
    console.error('Toggle error:', error);
    showNotification('Failed to update game status. Please try again.', 'error');
  }
};

onMounted(() => {
  window.addEventListener('open-game-edit-modal', openModal);
});

onUnmounted(() => {
  window.removeEventListener('open-game-edit-modal', openModal);
});
</script>
