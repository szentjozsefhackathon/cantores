import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";
import { loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const port = parseInt(env.VITE_EXTERNAL_PORT) || 5173;

    return {
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
            port: port,

            watch: {
                ignored: ['**/storage/framework/views/**', '**/vendor/**']
            },
        },
    };
});
