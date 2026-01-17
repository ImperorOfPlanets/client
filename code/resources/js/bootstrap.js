// resources/js/bootstrap.js
import * as bootstrap from 'bootstrap/dist/js/bootstrap.bundle.min.js';
window.bootstrap = bootstrap;

// Делаем jQuery глобальным для плагинов
import $ from 'jquery';
window.$ = window.jQuery = $;

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */
import './echo';
console.log('Echo:', window.Echo);
