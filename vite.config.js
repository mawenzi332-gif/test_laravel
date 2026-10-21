import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

const vitePort = Number(process.env.VITE_PORT ?? 5173);
const appHttpPort = Number(process.env.APP_HTTP_PORT ?? 8080);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
    ],
    server: {
        host: '0.0.0.0',
        port: vitePort,
        strictPort: true,
        origin: `http://localhost:${vitePort}`,
        cors: {
            origin: [
                `http://localhost:${appHttpPort}`,
                `http://127.0.0.1:${appHttpPort}`,
            ],
        },
        hmr: {
            host: 'localhost',
            clientPort: vitePort,
        },
    },
});
