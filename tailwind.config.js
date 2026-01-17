import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    safelist: [
        // Platform badge colors (used dynamically via Alpine.js)
        'bg-gray-600', 'bg-gray-600/80', 'bg-gray-600/90',
        'bg-blue-600', 'bg-blue-600/80', 'bg-blue-600/90',
        'bg-green-600', 'bg-green-600/80', 'bg-green-600/90',
        'bg-red-600', 'bg-red-600/80', 'bg-red-600/90',
        // Game type badge colors
        'bg-orange-600/80',
        'bg-yellow-600/80',
        'bg-yellow-500/80',
        'bg-pink-600/80',
        'bg-purple-600/80',
        // Platform group colors (highlights tabs)
        'bg-purple-600',
        'bg-yellow-600',
        'bg-cyan-600',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
