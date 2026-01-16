import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import { config as dotenvConfig } from 'dotenv';

// Загружаем переменные из .env в process.env
// Это необходимо, потому что Vite не читает .env автоматически
dotenvConfig({ path: '.env' });

// Безопасно получаем APP_URL с fallback и валидацией
const rawAppUrl = process.env.APP_URL?.trim() || 'https://localhost';
let appUrl, hmrHost, hmrProtocol;

try {
    // Принудительно добавляем протокол, если его нет (защита от частой ошибки)
    const urlStr = rawAppUrl.startsWith('http') ? rawAppUrl : `http://${rawAppUrl}`;
    const url = new URL(urlStr);
    appUrl = url.toString();
    hmrHost = url.hostname;
    hmrProtocol = url.protocol.replace(/:$/, '');
} catch (err) {
    console.error('\n❌ Ошибка: Некорректный APP_URL в файле .env');
    console.error(`   Получено: "${rawAppUrl}"`);
    console.error('   Ожидается полный URL, например: https://localhost или https://ваш-сайт.local\n');
    process.exit(1);
}

export default defineConfig({
    base: appUrl + '/', // Vite рекомендует завершающий слеш для base
    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern',
            }
        }
    },
    build: {
        rollupOptions: {
            external: [
                'three',
                'three/addons/controls/PointerLockControls.js',
                'three/examples/jsm/controls/PointerLockControls'
            ],
        },
    },
    base: process.env.APP_URL,
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',      // Tailwind
                'resources/sass/app.scss',    // Bootstrap + кастомные
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
                //'resources/js/game.js'
            ],
            refresh: true,
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: hmrHost,
            protocol: hmrProtocol
        }
    }
})