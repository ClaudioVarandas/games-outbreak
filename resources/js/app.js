import './bootstrap';
import {createApp, defineAsyncComponent, h} from 'vue';
import GlobalSearch from './components/GlobalSearch.vue';
import AddGameToList from './components/AddGameToList.vue';
import './components/AuthModals.js';
import 'tom-select/dist/css/tom-select.css';

// Wait for DOM to be ready before mounting Vue
document.addEventListener('DOMContentLoaded', () => {
    // Mount global search in header (desktop)
    const appSearchElement = document.getElementById('app-search');
    if (appSearchElement) {
        createApp({})
            .component('global-search', GlobalSearch)
            .mount('#app-search');
    }

    // Mount global search for mobile
    const appSearchMobileElement = document.getElementById('app-search-mobile');
    if (appSearchMobileElement) {
        createApp({})
            .component('global-search', GlobalSearch)
            .mount('#app-search-mobile');
    }

    // Mount add game to list components (can be multiple on page)
    document.querySelectorAll('[data-vue-component="add-game-to-list"]').forEach(element => {
        const listId = parseInt(element.getAttribute('data-list-id'));
        const routePrefix = element.getAttribute('data-route-prefix');
        const platformsData = element.getAttribute('data-platforms');
        const showGenreSelection = element.getAttribute('data-show-genre-selection') === 'true';
        const availableGenresData = element.getAttribute('data-available-genres');

        let platforms = [];
        if (platformsData) {
            try {
                platforms = JSON.parse(platformsData);
            } catch (e) {
                console.error('Failed to parse platforms data:', e);
            }
        }

        let availableGenres = [];
        if (availableGenresData) {
            try {
                availableGenres = JSON.parse(availableGenresData);
            } catch (e) {
                console.error('Failed to parse genres data:', e);
            }
        }

        if (listId && routePrefix) {
            createApp({
                render() {
                    return h(AddGameToList, {
                        listId: listId,
                        routePrefix: routePrefix,
                        availablePlatforms: platforms,
                        showGenreSelection: showGenreSelection,
                        availableGenres: availableGenres
                    });
                }
            }).mount(element);
        }
    });

    // Mount Tiptap editor components (lazy-loaded)
    const tiptapElements = document.querySelectorAll('[data-vue-component="tiptap-editor"]');
    if (tiptapElements.length) {
        const TiptapEditor = defineAsyncComponent(() => import('./components/TiptapEditor.vue'));
        tiptapElements.forEach(element => {
            const name = element.getAttribute('data-name') || 'content';
            const placeholder = element.getAttribute('data-placeholder') || 'Write your content here...';
            const initialContentRaw = element.getAttribute('data-initial-content');
            let initialContent = { type: 'doc', content: [{ type: 'paragraph' }] };
            if (initialContentRaw) {
                try {
                    initialContent = JSON.parse(initialContentRaw);
                } catch (e) {
                    console.error('Failed to parse initial content:', e);
                }
            }
            createApp({
                render() {
                    return h(TiptapEditor, {
                        name: name,
                        initialContent: initialContent,
                        placeholder: placeholder
                    });
                }
            }).mount(element);
        });
    }

    // Mount game edit modals component (lazy-loaded)
    const gameEditModalsElement = document.getElementById('game-edit-modals');
    if (gameEditModalsElement) {
        const GameEditModals = defineAsyncComponent(() => import('./components/GameEditModals.vue'));
        const listType = gameEditModalsElement.getAttribute('data-list-type');
        const listSlug = gameEditModalsElement.getAttribute('data-list-slug');
        const listName = gameEditModalsElement.getAttribute('data-list-name');
        const toggleHighlightUrl = gameEditModalsElement.getAttribute('data-toggle-highlight-url');
        const toggleIndieUrl = gameEditModalsElement.getAttribute('data-toggle-indie-url');
        const getGenresUrl = gameEditModalsElement.getAttribute('data-get-genres-url');
        const updatePivotUrl = gameEditModalsElement.getAttribute('data-update-pivot-url') || '';
        const csrfToken = gameEditModalsElement.getAttribute('data-csrf-token');
        const platformsData = gameEditModalsElement.getAttribute('data-platforms');
        const systemGenresData = gameEditModalsElement.getAttribute('data-system-genres');

        let platforms = [];
        if (platformsData) {
            try {
                platforms = JSON.parse(platformsData);
            } catch (e) {
                console.error('Failed to parse platforms data:', e);
            }
        }

        let systemGenres = [];
        if (systemGenresData) {
            try {
                systemGenres = JSON.parse(systemGenresData);
            } catch (e) {
                console.error('Failed to parse system genres data:', e);
            }
        }

        createApp({
            render() {
                return h(GameEditModals, {
                    listType,
                    listSlug,
                    listName,
                    toggleHighlightUrl,
                    toggleIndieUrl,
                    getGenresUrl,
                    updatePivotUrl,
                    csrfToken,
                    availablePlatforms: platforms,
                    systemGenres: systemGenres
                });
            }
        }).mount(gameEditModalsElement);
    }
});




