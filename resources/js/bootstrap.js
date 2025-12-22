import axios from 'axios';
import Alpine from 'alpinejs';
import persist from '@alpinejs/persist';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.Alpine = Alpine;

Alpine.plugin(persist);
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



