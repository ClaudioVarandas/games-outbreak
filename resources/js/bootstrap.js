import axios from 'axios';
import Alpine from 'alpinejs';
import persist from '@alpinejs/persist';
import collapse from '@alpinejs/collapse';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.Alpine = Alpine;

Alpine.plugin(persist);
Alpine.plugin(collapse);

// Import Alpine components BEFORE Alpine.start()
import './components/list-filter.js';

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
