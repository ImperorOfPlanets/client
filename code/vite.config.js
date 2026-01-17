import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    
    return {
        base: './',
        build: {
            outDir: 'public/build',
            manifest: true, // Ключевое: генерировать manifest.json
            rollupOptions: {
                external: [
                    'three',
                    'three/addons/controls/PointerLockControls.js',
                    'three/examples/jsm/controls/PointerLockControls'
                ],
            },
        },
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/sass/app.scss',
                    'resources/js/app.js',
                    'resources/js/pwa.js',
                    'resources/js/sidebar.js',
                    'resources/js/bootstrap.js',
                    'resources/js/echo.js',
                    'resources/js/posts.js',
                    'resources/js/assistant.js',
                    'resources/js/shop.js',
                    'resources/js/intervals.js',
                    'resources/js/events.js',
                    'resources/js/files.js',
                    'resources/js/csrf.js',
                    'resources/js/preloader.js',
                    'resources/js/workers.js',
                    'resources/js/worker.cache.js',
                    'resources/js/worker.push.js',
                    'resources/js/workspace.js',
                    //'resources/js/game.js',
                ],
                refresh: false,
                publicDirectory: 'build',
                hotFile: false,
                buildDirectory: 'build',
                assetUrl: env.ASSET_URL || './build/',
            }),
        ],
        server: false,
    };
});