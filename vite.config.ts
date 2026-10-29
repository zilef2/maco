import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import tailwindcss from "@tailwindcss/vite";
import { resolve } from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        host: '127.0.0.1',
    },
    build: {
        // Sube el umbral del warning de 500 kB (valor por defecto de Vite).
        // No cambia la build: solo decide a partir de qué tamaño avisar.
        // Con los manualChunks de abajo ningún chunk debería pasar esta cifra.
        chunkSizeWarningLimit: 800,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) return;
                    if (id.includes('echarts') || id.includes('zrender')) return 'echarts';
                    if (id.includes('gsap')) return 'gsap';
                    if (id.includes('lucide')) return 'icons';
                    if (id.includes('lodash')) return 'lodash';
                    if (id.includes('reka-ui') || id.includes('radix')) return 'ui';
                    return 'vendor';
                },
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
            transformOnServe: (code, devServerUrl) => code.replaceAll(devServerUrl, devServerUrl.replace('http://', 'https://')),

        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
            'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
            '@notificaciones': path.resolve(__dirname, './packages/notificacion/resources/views/components'),
        },
    },
});
