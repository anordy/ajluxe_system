import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/livewire/livewire/src/Features/SupportPagination/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                // one standard family everywhere: Inter for text, Inter Tight for headings and prices
                sans: ['"Inter Variable"', 'Inter', 'Segoe UI', ...defaultTheme.fontFamily.sans],
                shop: ['"Inter Variable"', 'Inter', 'Segoe UI', ...defaultTheme.fontFamily.sans],
                display: ['"Inter Tight Variable"', '"Inter Variable"', 'Segoe UI', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // AJ LUXE AUTO brand (from the logo)
                brand: {
                    50: '#FEF2F2', 100: '#FDE3E4', 200: '#FBCBCD', 300: '#F7A3A6',
                    400: '#F06B70', 500: '#E31B23', 600: '#C8141B', 700: '#A61117',
                    800: '#89131A', 900: '#73161B',
                },
                ink: {
                    50: '#F7F7F8', 100: '#EDEDEF', 200: '#D9D9DE', 300: '#B6B6BF',
                    400: '#8B8B97', 500: '#6B6B76', 600: '#52525C', 700: '#3C3C44',
                    800: '#232328', 900: '#141417', 950: '#0B0B0D',
                },
            },
        },
    },
    plugins: [forms],
};
