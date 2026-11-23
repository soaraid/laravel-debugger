import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

// Builds the debugger UI into dist/. The PHP side reads dist/.vite/manifest.json
// to find the hashed file names (see src/Assets.php).
export default defineConfig({
    plugins: [vue()],
    base: './',
    publicDir: false,
    define: {
        __VUE_OPTIONS_API__: false,
        __VUE_PROD_DEVTOOLS__: false,
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false,
    },
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        manifest: true,
        assetsDir: 'assets',
        rollupOptions: {
            input: ['resources/css/app.css', 'resources/js/app.js'],
        },
    },
});
