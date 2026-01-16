// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { config as dotenvConfig } from 'dotenv';

dotenvConfig({ path: '.env' });

// === ДОБАВЬТЕ ЭТО ДЛЯ ОТЛАДКИ ===
console.log('🔍 APP_URL из .env:', process.env.APP_URL);
console.log('🔍 Текущая рабочая директория:', process.cwd());
// ================================


const rawAppUrl = process.env.APP_URL?.trim() || 'https://localhost';
let appUrl, hmrHost, hmrProtocol;

try {
    const urlStr = rawAppUrl.startsWith('http') ? rawAppUrl : `https://${rawAppUrl}`;
    const url = new URL(urlStr);
    appUrl = url.toString().replace(/\/$/, ''); // убираем завершающий слеш
    hmrHost = url.hostname;
    hmrProtocol = url.protocol.replace(/:$/, '');
} catch (err) {
    console.error('\n❌ Некорректный APP_URL в .env');
    console.error(`Получено: "${rawAppUrl}"`);
    console.error('Пример: APP_URL=https://localhost\n');
    process.exit(1);
}

export default defineConfig({
    base: appUrl + '/', // ✅ ЕДИНСТВЕННОЕ объявление base
    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern',
            }
        }
    },
    build: {
        outDir: 'public/build', // ← явно указываем директорию сборки (опционально, но рекомендуется)
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
        https: true,
        strictPort: true,
        hmr: {
            host: hmrHost,
            protocol: hmrProtocol
        }
    }
});