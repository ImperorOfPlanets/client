let deferredPrompt;

// Определяем браузер
const isChrome = /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor);
const isFirefox = /Firefox/.test(navigator.userAgent);

// Обработчик события 'beforeinstallprompt' для Chrome
if(isChrome)
{
    console.log('chrome');
    // Обработчик события 'beforeinstallprompt'
    window.addEventListener('beforeinstallprompt', (e) => {
        // Предотвращаем стандартный баннер установки
        e.preventDefault();

        // Сохраняем событие для последующего вызова
        deferredPrompt = e;

        // Показываем кнопку "Установить приложение"
        const installButton = document.querySelector('[data-action="installApp"]');
        if (installButton) {
            installButton.classList.remove('d-none'); // Делаем кнопку видимой

            // Добавляем обработчик клика по кнопке
            installButton.addEventListener('click', async () => {
                if (deferredPrompt) {
                    // Показываем диалог установки
                    deferredPrompt.prompt();

                    // Ожидаем выбора пользователя
                    const choiceResult = await deferredPrompt.userChoice;
                    if (choiceResult.outcome === 'accepted') {
                        console.log('Пользователь согласился установить приложение.');
                    } else {
                        console.log('Пользователь отклонил установку.');
                    }

                    // Сбрасываем сохранённое событие
                    deferredPrompt = null;

                    // Скрываем кнопку после установки
                    installButton.classList.add('d-none');
                }
            });
        } else {
            console.error('Кнопка для установки PWA не найдена.');
        }
    });
}

// Обработка для Firefox
if (isFirefox)
{
    console.log('firefox');
    const installButton = document.querySelector('[data-action="installApp"]');
    installButton.classList.remove('d-none');
    // Добавляем обработчик клика по кнопке
    installButton.addEventListener('click', async () => {
        // Перенаправляем пользователя на страницу с инструкцией
        window.location.href = '/firefox';
    });
}

// Обработчик события 'appinstalled'
window.addEventListener('appinstalled', () => {
    console.log('PWA успешно установлено.');
});