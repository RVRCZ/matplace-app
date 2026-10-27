import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // embed.ts: the calculator inside another site's iframe, without three.js (resources/views/layouts/embed.blade.php)
            input: ['resources/css/app.css', 'resources/js/app.ts', 'resources/js/embed.ts'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        chunkSizeWarningLimit: 1200,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
