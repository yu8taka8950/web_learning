import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            assets: ['resources/images/onboarding/**', 'resources/images/toppage/**', 'resources/images/icon/**'],
            refresh: true,
        }),
    ],
});
