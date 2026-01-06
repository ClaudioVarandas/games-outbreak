import './bootstrap';
import {createApp, h} from 'vue';
import GlobalSearch from './components/GlobalSearch.vue';
import AddGameToList from './components/AddGameToList.vue';
import './components/AuthModals.js';

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
        let platforms = [];
        if (platformsData) {
            try {
                platforms = JSON.parse(platformsData);
            } catch (e) {
                console.error('Failed to parse platforms data:', e);
            }
        }
        if (listId && routePrefix) {
            createApp({
                render() {
                    return h(AddGameToList, {
                        listId: listId,
                        routePrefix: routePrefix,
                        availablePlatforms: platforms
                    });
                }
            }).mount(element);
        }
    });
});




