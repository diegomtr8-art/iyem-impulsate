import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    darkMode: 'class',

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                guinda: {
                    50:  '#fef2f4',
                    100: '#fde6ea',
                    200: '#fbc4cd',
                    300: '#f89aa9',
                    400: '#f36178',
                    500: '#eb2d4e',
                    600: '#c8113b',
                    700: '#a80e31',
                    800: '#8b1028',
                    900: '#710d21',
                    950: '#45060f',
                },
            },
        },
    },

    plugins: [forms, typography],
};
