import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Определяем хост динамически: если локалка — localhost, иначе — внешний домен
const currentHost = window.location.hostname;
const isLocal = currentHost === 'localhost' || currentHost === '127.0.0.1';

const reverbHost = isLocal 
    ? 'localhost' 
    : import.meta.env.VITE_REVERB_HOST || currentHost;

const reverbPort = isLocal 
    ? import.meta.env.VITE_REVERB_PORT_LOCAL || 8080 
    : import.meta.env.VITE_REVERB_PORT || 443;

const forceTLS = !isLocal; // Локально — ws, удалённо — wss

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: reverbHost,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: forceTLS,
    enabledTransports: ['ws', 'wss'],
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        },
    },
});