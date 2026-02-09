import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        hmr: {
            host: 'localhost'
        },
        host: '0.0.0.0',

        watch: {
            ignored: ['**/storage/framework/views/**']
        },
    },
});
