

window["initPreloader"] = function(){
	console.log('Инициализация загрузчика');

	if(window.app.preloader != undefined)
	{
		console.log('Загрузчик найден');
		return false;
	}

	console.log('Установка загрузчика');

	//Время запуска
	window.app.preloader = {};
	window.app.preloader.startDate =new Date();
	window.app.preloader.startDateMilliseconds = window.app.preloader.startDate.getTime();

	//Поддержка
	window.app.support ={};

	window.app.events.add('checkClient');
	//проверяет всели файлы скачаны
	//window.app.events.add('checkProcessDownload');
}


//Запуск загрузчика
window["preloaderStart"] = function()
{
	app.events.fire('checkClient');
	preText('Начинаем рабочий процесс');
	//downloadFilesProcess();
}

//Начать скачивание файла

//--------------------------------- Проверка клиента ---------------------------------//
//Проверяет поддерживает ли браузер Push
function checkSupportBrowserPushManager()
{
	preText('Проверяем поддержку уведомлений','blue');
	if(!('PushManager' in window))
	{
		window.app.support.pushmanager = false;
	}
	window.app.support.pushmanager = true;
}

//Проверяет поддерживает ли браузер Cache
function checkSupportBrowserCacheApi()
{
	preText('Проверяем поддержку работы с памятью','blue');
	if (!('caches' in window)){
		window.app.support.cacheapi = false;
	}
	window.app.support.cacheapi = true;
}

//Проверяет поддерживает ли браузер Service workers
function checkSupportBrowserServiceWorker()
{
	preText('Проверяем возможность трудоустроить специалистов','blue');
	if(!('serviceWorker' in navigator))
	{
		window.app.support.workers = false;
	}
	else
	{
		window.app.support.workers = true;
	}
}

//Проверка клиента
window["checkClient"] = function()
{
	preText('Осматриваем клиента','blue');
	checkSupportBrowserCacheApi();
	checkSupportBrowserPushManager();
	checkSupportBrowserServiceWorker();
}

//---------------------------------  Интервал ---------------------------------//
window['preloaderIntervalFuction'] = function()
{
	//Получаем время сработки
	var fireTimeDate = new Date();
	//Получаем время сработки в милисекундах
	var fireDateMilliseconds = fireTimeDate.getTime();
	//Вычисляем разницу
	var diffMilliseconds = fireDateMilliseconds-window.app.preloader.startDateMilliseconds;
	var diffSeconds = diffMilliseconds/1000;
	
	console.log('Запускаю - preloaderIntervalFuction');
	console.log('Прошло времени в секундах: ' + diffSeconds);
	
	//Проверяем статус
	//downloadFilesProcess();
}
			/*---------------------------------Воркеры-----------------------*/


//Запускаем скачку файлов
function downloadFilesProcess()
{
	console.log('>>>>>>>>>>><<<<<<<<<<<<<<<<');
	console.log('>>>DOWLOADFILEPROCESS');
	console.log('>>>STATUS - '+window.app.preloader.status);
	console.log('>>>STATUS - '+window.app.preloader.stage);
	console.log('>>>>>>>>>>><<<<<<<<<<<<<<<<');
	if(window.app.preloader.status===undefined)
	{
		window.app.preloader.status='start';
		console.log('FIRE preloader status - undefined');
		//Добавляем интервал
		var preloaderInterval={
			name:"preloader",
			//Запускаемая функция
			cycFunction:"preloaderIntervalFuction",
			//Период цикличности
			delay:1000,
			logger:false
		};
		window.app.intervals.add(preloaderInterval);
	}

	//Определяет способ загрузки
	if(window.app.preloader.status=='start')
	{
		preText('Выбираем способ получения документов');
		console.log('FIRE preloader status - start');
		//Если поддерживаются воркеры
		if(window.app.support.cacheapi && window.app.support.workers)
		{
			console.log('Память и воркеры доступны');
			console.log('Устанавливаем тип - workercache');
			console.log('Устанавливаем статус подготовки - prepare');
			preText('Пробуем вызвать специалиста для работы с памятью');	
			window.app.preloader.type='workercache';
			window.app.preloader.status='prepare';
		}

		//если память недоступна проверяем соединения
		if(window.app.preloader.status=='start' && window.app.preloader.type==undefined)
		{
			preText('Пробуем через порты');
			window.app.preloader.type='sockets';
			preText('Начали процесс подготовки');
			window.app.preloader.process='connect';
		}

		//if(window.app.settings.download.status=='status'
		
		//qs)
		//else
		//{
		//	preText('Действуем по старинке');
		//		window.app.settings.download.type='get';
		//}
	}

	//Подгототавляет способ загрузки
	if(window.app.preloader.status=='prepare')
	{
		console.log('FIRE preloader status - prepare');

		//Загрузка с помощью воркеров
		if(window.app.preloader.type=='workercache')
		{
			//Добавляeм воркеры и Проверяем скачаны ли все воркеры
			/*if(window.app.preloader.stage===undefined)
			{
				console.log('Стадия не указана. Добавляем воркеры в список и стартуем загрузку');
				window.app.preloader.stage='downloadworkers';
				preText('add workers files');
				window.app.files.add({
					name:'worker.cache',
					path:'/worker.cache.js',
					method:'get'
				});
				window.app.files.add({
					name:'worker.push',
					path:'/worker.push.js'
				});
				window.app.files.setTypeDownload('worker.push','get');
				window.app.files.loadAllFiles();
			}
			else if(window.app.preloader.stage=='downloadworkers')
			{
				console.log('Идет стадия скачки воркеров. Проверяем на скаченность');
				preText('Идет стадия скачки воркеров. Проверяем на скаченность');
				window.app.preloader.workersDownloaded = true;
				for(const indexFile in window.app.files.array)
				{
					if(window.app.files.array[indexFile]['name'].substring(0,6)=='worker')
					{
						console.log('Воркер');
						if(window.app.files.array[indexFile]['status']!=='downloaded')
						{
							window.app.preloader.workersDownloaded = false;
							return false;
						}
					}
				}

				if(window.app.preloader.workersDownloaded)
				{
					preText('Документы к трудоустройству готовы','green');
					initWorkers();
					window.app.workers.add(workerCache);
					window.app.workers.registerAll();
					window.app.preloader.stage='installworkers';
				}
			}
			else if(window.app.preloader.stage=='installworkers')
			{
				logger.enableLogger();
				preText('Идет стадия установки воркеров. Проверяем процесс');
				//Отправляем воркеру сообщение
				//console.log('check status Worker' + window.app.workers.getStatus('Cache'));
				//status = window.app.workers.getStatus('Cache');
				//if
			}*/
		}
	}

	//Проверяем скачались ли файлы
	if(window.app.preloader.status=='process')
	{
		console.log('FIRE preloader status - process');
	}
}