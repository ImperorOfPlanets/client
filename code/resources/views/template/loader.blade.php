<div id="loader" class="loader">
<div class="loader-content">
<div class="loader-text">Загрузка</div>
@include('template.loader-image')
<div class="loader-log p-1" id="loaderLog"></div>
</div>
</div>
<script>
    //Цветная консоль
    function conColor(message,color){var nM = '%c'+message;console.log(nM,'color:'+color);}

    //Воркеры
    class WorkerManager {
        constructor() {
            this.array = [];
            this.messages = { received: [], sent: [], queue: [] };
        }

        // Создать и добавить воркер
        createWorker(name, link) {
            if (!name || !link) {
                console.error('createWorker: Имя или ссылка на воркер отсутствуют');
                return;
            }

            const worker = { name, link, status: 'pending' }; // Инициализация нового воркера
            this.add(worker); // Добавляем воркер в массив
            console.log(`Создан воркер: ${name}, ссылка: ${link}`);
        }

        // Добавить воркер
        add(worker) {
            this.array.push(worker);
        }

        // Установить статус воркера
        setStatus(index, status) {
            if (!this.array[index]) {
                console.error(`setStatus: Воркер с индексом ${index} не найден`);
                return;
            }
            console.log(`Установлен статус '${status}' для воркера: ${this.array[index].name}`);
            this.array[index].status = status;
        }

        // Получить индекс по имени файла
        getIndexByFilename(filename) {
            return this.array.findIndex(worker => worker.link && worker.link.includes(filename));
        }

        // Получить индекс по имени воркера
        getIndexByName(name) {
            return this.array.findIndex(worker => worker.name === name);
        }

        // Обработка сообщения
        handleMessage(event) {
            const message = event.data;
            if (message.command) {
                switch (message.command) {
                    case 'getHash':
                        this.handleGetHash(message, event.ports[0]);
                        break;
                    case 'setstatus':
                        const index = this.getIndexByName(message.worker);
                        this.setStatus(index, message.status);
                        break;
                    case 'ping':
                        this.sendMessage({ command: 'pong' });
                        break;
                    default:
                        conColor(`Неизвестная команда: ${message.command}`, 'orange');
                        break;
                }
            }
        }

        // Отправить сообщение воркеру
        sendMessage(message) {
            if (navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage(message);
            } else {
                console.warn('Нет активного Service Worker для отправки сообщения');
            }
        }

        // Обработка команды getHash
        handleGetHash(message, port) {
            const pathForSearch = message.url.replace('https://vlangepase.ru', '');
            const file = window.files_array.find(file => file.path === pathForSearch);

            if (file) {
                message.hash = file.hash;
                this.sendMessageForPort(port, message);
            } else {
                console.error(`Файл для URL ${pathForSearch} не найден`);
                port.postMessage({ error: 'Файл не найден' });
            }
        }

        // Отправить сообщение в порт
        sendMessageForPort(port, message) {
            port.postMessage(message);
        }

        // Зарегистрировать все воркеры
        registerAll() {
            for (const worker of this.array) {
                if (worker.link) {
                    conColor(`Регистрация воркера: ${worker.link}`, 'blue');
                    navigator.serviceWorker.register(worker.link).then(
                        (registration) => {
                            conColor(`${worker.name} успешно зарегистрирован`, 'green');
                            worker.status = 'registered';
                        },
                        (err) => {
                            conColor(`${worker.name} регистрация не удалась`, 'red');
                            console.error(`${worker.name} регистрация не удалась:`, err);
                            worker.status = 'failed';
                            worker.error = err;
                        }
                    );
                }
            }
        }
    }

    //Функция показывания текста
    function loaderAddText(text, color = null)
    {
        const logElement = document.getElementById('loaderLog');
        if (logElement)
        {
            const message = document.createElement('div');
            message.innerHTML = text;
            if (color) {message.style.color = color;}
            logElement.append(message); // Добавляем текст наверх лога
        }
    }

    // Инициализируем переменную app
    window.app={};
    loaderAddText('Начинаю загрузку приложения','green')

	//Время запуска
	window.app.loader = {};
	window.app.loader.startDate =new Date();
	window.app.loader.startDateMilliseconds = window.app.loader.startDate.getTime();
    loaderAddText('Установил время начала загрузки','green')

    //Поддержка
	window.app.support ={};
    loaderAddText('Начинаю проверку поддержки браузером возможностей','green')

    // Показать загрузчик
    function showloader(){document.getElementById('loader').style.display = 'flex';}

    // Скрыть загрузчик
    function hideloader(){document.getElementById('loader').style.display = 'none';}

    //--------------------------------- Проверка клиента ---------------------------------//
    //Проверяет поддерживает ли браузер Push
    function checkSupportBrowserPushManager()
    {
        loaderAddText('Проверяем поддержку уведомлений','blue');
        if(!('PushManager' in window))
        {
            window.app.support.pushmanager = false;
            loaderAddText('Push уведомления - не поддерживаются','red')
        }
        window.app.support.pushmanager = true;
        loaderAddText('Push уведомления - поддерживаются','green')
    }

    //Проверяет поддерживает ли браузер Cache
    function checkSupportBrowserCacheApi()
    {
        loaderAddText('Проверяем поддержку работы с памятью','blue');
        if (!('caches' in window)){
            window.app.support.cacheapi = false;
            loaderAddText('Работа с памятью - не поддерживается','red')
        }
        window.app.support.cacheapi = true;
        loaderAddText('Работа с памятью - поддерживается','green')
    }

    //Проверяет поддерживает ли браузер Service workers
    function checkSupportBrowserServiceWorker()
    {
        loaderAddText('Проверяем возможность трудоустроить электронных специалистов','blue');
        if(!('serviceWorker' in navigator))
        {
            window.app.support.workers = false;
            loaderAddText('Трудоустроить электронных специалистов нет возможности','red');
        }
        window.app.support.workers = true;
        loaderAddText('Есть возможность трудоустроить электронных специалистов','green')
    }

    checkSupportBrowserPushManager();
    checkSupportBrowserCacheApi();
    checkSupportBrowserServiceWorker();

    //Если поддерпживаются воркеры добавляем
    if(window.app.support.workers)
    {
        // Инициализация менеджера воркеров
        window.app.workers = new WorkerManager();
        loaderAddText('Подключаю менеджера электронных специалистов','green')

        // Проверяем на кэш
        if (window.app.support.cacheapi) {
            const cacheWorkerUrl = "{{ Vite::asset('resources/js/worker.cache.js') }}";;
            window.app.workers.createWorker('CacheWorker', cacheWorkerUrl); // Здесь исправлено имя переменной
            loaderAddText('Электронный специалист по работе с памятью добавлен на трудоустройство', 'blue');
        }

        // Проверяем на пуш
        if (window.app.support.pushmanager) {
            const pushWorkerUrl = "{{ Vite::asset('resources/js/worker.push.js') }}";;;
            window.app.workers.createWorker('PushWorker', pushWorkerUrl);
            loaderAddText('Электронный специалист по работе с уведомлениями добавлен на трудоустройство', 'blue');
        }

        // Регистрация всех воркеров
        window.app.workers.registerAll();
        loaderAddText('Электронных специалистов отправили на регистрацию','blue')
    }
</script>