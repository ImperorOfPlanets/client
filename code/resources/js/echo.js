// resources/js/echo.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Определяем среду на основе APP_URL
const appUrl = import.meta.env.VITE_APP_URL || 'https://localhost';
const isLocal = appUrl.includes('localhost') || appUrl.includes('127.0.0.1');

// Локальные настройки
const localSettings = {
    host: import.meta.env.VITE_REVERB_HOST || 'localhost',
    port: import.meta.env.VITE_REVERB_PORT_LOCAL || 8080,
    scheme: import.meta.env.VITE_REVERB_SCHEME || 'http'
};

// Production настройки
const productionSettings = {
    host: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    port: import.meta.env.VITE_REVERB_PORT || 443,
    scheme: import.meta.env.VITE_REVERB_SCHEME || 'https'
};

const settings = isLocal ? localSettings : productionSettings;
const forceTLS = settings.scheme === 'https';

console.log('🔄 Reverb initialization:', {
    environment: isLocal ? 'local' : 'production',
    appUrl: appUrl,
    settings: settings,
    forceTLS: forceTLS
});

try {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: settings.host,
        wsPort: settings.port,
        wssPort: settings.port,
        forceTLS: forceTLS,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            },
        },
        cluster: 'mt1', // Используем стандартный кластер
        namespace: 'App\\Events', // Пространство имен событий
    });

    // Отладка подключения
    const pusher = window.Echo.connector.pusher;
    
    pusher.connection.bind('state_change', function(states) {
        console.log('🌐 Reverb connection state:', {
            current: states.current,
            previous: states.previous
        });
        
        if (states.current === 'connected') {
            console.log('✅ Reverb successfully connected!');
        }
    });

    pusher.connection.bind('error', function(error) {
        console.error('❌ Reverb connection error:', error);
    });

    console.log('✅ Echo initialized successfully');
    console.log('🔌 Reverb connection status:', pusher.connection.state);

} catch (error) {
    console.error('🔥 Failed to initialize Echo:', error);
    window.Echo = null;
}

// Экспортируем для использования в других модулях
export default window.Echo;