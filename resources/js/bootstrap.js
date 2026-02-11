import axios from 'axios';
import Alpine from 'alpinejs';
import persist from '@alpinejs/persist';
import collapse from '@alpinejs/collapse';
import TomSelect from 'tom-select';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.Alpine = Alpine;
window.TomSelect = TomSelect;

Alpine.plugin(persist);
Alpine.plugin(collapse);

// Import Alpine components BEFORE Alpine.start()
import './components/list-filter.js';
import './components/game-collection-actions.js';
import './components/user-game-edit-modal.js';

Alpine.start();

// Global Alpine data
document.addEventListener('alpine:init', () => {
    Alpine.data('theme', () => ({
        dark: Alpine.$persist(false).as('theme-dark'),

        toggle() {
            this.dark = !this.dark;
        },
    }));
});
