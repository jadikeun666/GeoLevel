import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        // maplibre-gl is a large WebGL mapping library (~1MB unminified),
        // lazy-loaded via defineAsyncComponent so it only downloads when a
        // map component actually mounts (Peta tab, LocationPicker,
        // GpxImportModal, Projects/Index mini-map). This is expected size
        // for that chunk, not a sign of unnecessary bloat in app code —
        // raised the warning threshold so it stops flagging on every build.
        chunkSizeWarningLimit: 1200,
    },
});
