import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.jsx'], refresh: true }),
        react(),
    ],
    build: {
        // No source maps in production: they would publish the original source.
        sourcemap: false,
    },
});
