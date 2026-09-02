import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [laravel({ input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/about.js', 'resources/js/users.js', 'resources/js/projects.js', 'resources/js/topology-detail.js', 'resources/js/region-toggle.js'], refresh: true })],
});
