

//Воркеры
class WorkerManager {
    constructor() {
        this.array = [];
        this.messages = { received: [], sent: [], queue: [] };
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
                        worker.status = 'failed';
                        worker.error = err;
                    }
                );
            }
        }
    }
}

//Проверка поддержки воркеров
if ('serviceWorker' in navigator) {
    conColor('Service Workers поддерживаются', 'green');
    initWorkers();

    navigator.serviceWorker.addEventListener('message', (event) => {
        const message = event.data;
        conColor(`Сообщение от Service Worker: ${JSON.stringify(message)}`, 'blue');
        window.app.workers.messages.received.push(message);
        window.app.workers.parseMessage(message);
    });

    navigator.serviceWorker.startMessages?.();
} else {
    conColor('Service Workers не поддерживаются в этом браузере', 'red');
}

function initWorkers()
{
	navigator.serviceWorker.ready.then((registration) => {
		console.log('Service Worker активен:', registration);

		const activeWorker = registration.active;

        if (activeWorker) {
            const scriptURL = activeWorker.scriptURL;
            const filename = scriptURL.substring(scriptURL.lastIndexOf('/') + 1);
            const index = window.app.workers.getIndexByFilename(filename);

            console.log(`Активный Service Worker: ${filename}, индекс: ${index}`);
            window.app.workers.setstatus(index, 'active');
        }
	});

	// Инициализация объекта воркеров
	window.app.workers = new WorkerManager();
}

window.addEventListener('DOMContentLoaded',()=>{
	let displayMode = 'browser tab';
	if (window.matchMedia('(display-mode: standalone)').matches) {
		displayMode = 'fullscreen';
	}
	// Log launch display mode to analytics
	console.log('DISPLAY_MODE_LAUNCH:', displayMode);
});