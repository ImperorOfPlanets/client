// URL манифеста
const manifestUrl = '/build/manifest.json';

// Имя кеша
const staticCacheName = 'static-cache-v1';

// Разрешенные расширения файлов
const accessExt = ['js', 'css', 'gif'];

// Функция для загрузки манифеста
async function fetchManifest() {
    const response = await fetch(manifestUrl, { cache: 'no-store' }); // Отключаем кеш браузера
    return response.json();
}

// Проверка изменений файла
async function hasFileChanged(fileUrl, cache) {
    const cachedResponse = await cache.match(fileUrl);
    if (!cachedResponse) return true; // Файл отсутствует в кеше

    const cachedText = await cachedResponse.text();
    const networkResponse = await fetch(fileUrl);
    const networkText = await networkResponse.text();

    return cachedText !== networkText; // Проверка изменений по содержимому
}

// Обновление кеша на основе манифеста
async function updateCacheWithManifest(manifest) {
    const cache = await caches.open(staticCacheName);

    // Файлы из манифеста
    const filesToCache = Object.values(manifest).flatMap(entry => {
        const files = [entry.file];
        if (entry.css) files.push(...entry.css);
        return files.map(file => `/build/${file}`);
    });

    // Проверка и обновление файлов
    await Promise.all(
        filesToCache.map(async fileUrl => {
            const hasChanged = await hasFileChanged(fileUrl, cache);
            if (hasChanged) {
                const response = await fetch(fileUrl);
                await cache.put(fileUrl, response);
                console.log(`Обновлен файл: ${fileUrl}`);
            } else {
                console.log(`Файл не изменился: ${fileUrl}`);
            }
        })
    );

    // Удаление устаревших файлов
    const cachedRequests = await cache.keys();
    const cachedUrls = cachedRequests.map(request => new URL(request.url).pathname);
    const manifestUrls = filesToCache;

    await Promise.all(
        cachedUrls
            .filter(url => !manifestUrls.includes(url))
            .map(url => cache.delete(url).then(() => console.log(`Удален устаревший файл: ${url}`)))
    );
}

// Проверка расширений файлов
function checkURL(url) {
    const filename = url.substring(url.lastIndexOf('/') + 1);
    const ext = filename.split('.').pop();
    return accessExt.includes(ext);
}

// Установка Service Worker
self.addEventListener('install', event => {
	console.log('Установлен электронный сотрудник для работы с памятью.');
    event.waitUntil(
        fetchManifest()
            .then(updateCacheWithManifest)
            .then(() => self.skipWaiting())
    );
});

// Активация Service Worker
self.addEventListener('activate', event => {
    console.log('Активирован электронный сотрудник для работы с памятью.');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== staticCacheName) {
                        console.log(`Удаление старого кеша: ${cacheName}`);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Перехват запросов
self.addEventListener('fetch', event => {
    if (event.request.method === 'GET' && checkURL(event.request.url)) {
        event.respondWith(
            caches.match(event.request).then(cachedResponse => {
                if (cachedResponse) {
                    return cachedResponse;
                }

                return fetch(event.request).then(networkResponse => {
                    return caches.open(staticCacheName).then(cache => {
                        cache.put(event.request, networkResponse.clone());
                        return networkResponse;
                    });
                });
            }).catch(() => caches.match('/offline.html'))
        );
    } else {
        return fetch(event.request);
    }
});

// Обработка сообщений
self.addEventListener('message', event => {
    console.log('Получено сообщение: ' + event.data);
});