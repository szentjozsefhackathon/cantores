import {
    createLogger,
    defineConfig,
    loadEnv,
} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";

const logger = createLogger();
const originalWarnOnce = logger.warnOnce.bind(logger);
logger.warnOnce = (msg, options) => {
    if (msg.includes("didn't resolve at build time")) return;
    originalWarnOnce(msg, options);
};

const originalConsoleWarn = console.warn;
console.warn = (...args) => {
    if (typeof args[0] === 'string' && args[0].includes('optimizing generated CSS')) return;
    originalConsoleWarn(...args);
};

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const port = parseInt(env.VITE_EXTERNAL_PORT) || 5173;

    return {
        customLogger: logger,
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
                ignored: ['**/storage/**', '**/vendor/**']
            },
        },
    };
});
