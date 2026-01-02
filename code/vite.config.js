import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern',
            }
        }
    },
    base: process.env.APP_URL || 'http://localhost',
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/sass/app.scss',
                'resources/js/app.js',
                'resources/js/pwa.js',
                'resources/js/bootstrap.js',
                'resources/js/sidebar.js',
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
            ],
            refresh: true,
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: process.env.APP_URL ? {
            host: new URL(process.env.APP_URL).hostname,
            protocol: new URL(process.env.APP_URL).protocol.replace(':', '')
        } : {
            host: 'localhost',
            protocol: 'ws'
        }
    }
})