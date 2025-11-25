window.app={};

//Отключение консольного вывода
var logger = function()
{
	var oldConsoleLog = null;
	var pub = {};
	pub.enableLogger = function enableLogger() 
	{
		if(oldConsoleLog == null) return;
		window['console']['log'] = oldConsoleLog;
	};
	pub.disableLogger = function disableLogger()
	{
		oldConsoleLog = console.log;
		window['console']['log'] = function() {}
	};
	return pub;
}();

//Главный интервал
var mainInterval =
{
	//Название интервала
	name:"main",
	//Запускаемая функция
	cycFunction:"mainIntervalFuction",
	//Период цикличности
	delay:1000,
	logger:false
};

//Переменная интервалов
window.app.intervals = {
	array:[],
	//Добавить
	add:function(interval)
	{
		this.array.push(interval);
		this.startAll();
	},
	//Запустить все
	startAll:function()
	{
		this.array.forEach(function(item,i,arr)
		{
			console.log(i+" - " +item['name']);
			console.log(item);
			//Проверяем на созданость
			if(item['interval']===undefined)
			{
				window.app.intervals.create(i);
			}
		});
	},
	//Создать интервал
	create:function(i)
	{
		console.log('Создаю интервал - '+this.array[i]['name']);
		if(this.array[i]['interval']===undefined)
		{
			this.array[i]['intervalFired'] = 1;
			console.log('Создаю функцию интервала');
			//Описание внутреней функции
			this.array[i]['functionIntelval'] = function()
			{
				//Показывать логи
				//console.log(this.array)
				if(window.app.intervals.array[i]['logger']===undefined || window.app.intervals.array[i]['logger']===false){
					//conColor(window.app.intervals.array[i]['name']+' logger off','blue');
					logger.disableLogger();
				}

				//показываем в название сработавшего интервала
				console.log('Сработал интервал: '+ window.app.intervals.array[i]['name'] + ' - '+window.app.intervals.array[i]['intervalFired']);
	
				//увеличиваем количество срабатываний
				window.app.intervals.array[i]['intervalFired']++;
	
				//Если есть внутреняя циклическая функция то выполняем
				if(window.app.intervals.array[i]['cycFunction']!==undefined)
				{
					console.log('Запускаю циклическую функцию: '+ window.app.intervals.array[i]['cycFunction']);
					//Выполняет функцию интервала и получает результат
					try{
						window[window.app.intervals.array[i]['cycFunction']]();
					}catch(e){
						logger.enableLogger();
						console.log('Ошибка при запуске функции - '+ window.app.intervals.array[i]['cycFunction']);
						console.log(e);
						logger.disableLogger();
					}
					var result = window[window.app.intervals.array[i]['cycFunction']]();
					console.log('>>>>> Интервал '+window.app.intervals.array[i]['name']+' функция '+window.app.intervals.array[i]['cycFunction']+' результат:<<<<<');
					console.log(result);
					console.log('>>>>>>>>>>>><<<<<<<<<<<<<<<');

					//Проверяем результат переназначаем переменные
					if(result!==undefined)
					{
						window.app.intervals.array[i]['cycFunctionResult'] = result;
	
						//Задержка интервала
						if(window.app.intervals.array[i]['cycFunctionResult']['delay']!==undefined)
						{
							window.app.intervals.array[i]['delay']= window.app.intervals.array[i]['cycFunctionResult']['delay'];
						}
	
						//Прерываемый
						if(window.app.intervals.array[i]['cycFunctionResult']['interruptible']!==undefined)
						{
							window.app.intervals.array[i]['interruptible']= window.app.intervals.array[i]['cycFunctionResult']['interruptible'];
						}
	
						//Функция выполняемая после прерывания
						if(window.app.intervals.array[i]['cycFunctionResult']['afterInterrupFunc']!==undefined)
						{
								window.app.intervals.array[i]['afterInterrupFunc']= window.app.intervals.array[i]['cycFunctionResult']['afterInterrupFunc'];
						}
					}
					else
					{
						window.app.intervals.array[i]['cycFunctionResult'] = undefined;
					}
				}
	
				//Если переменная не определенна пересоздаем интервал
				if(window.app.intervals.array[i]['interruptible']===undefined)
				{
					window.app.intervals.array[i]['interval'] = setTimeout(window.app.intervals.array[i]['functionIntelval'],window.app.intervals.array[i]['delay']);
				}
				else if(window.app.intervals.array[i]['interruptible']==true)
				{
					//Завершаем интервал
					clearInterval(window.app.intervals.array[i]['interval']);
					console.log('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>><<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<');
					console.log(window.app.intervals.array[i]);
					console.log('Интервал: '+ window.app.intervals.array[i]['name'] + ' - '+'закончил работу');
					console.log('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>><<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<');
	
					//Запускаем функцию после прерывания, еслиуказана
					if(window.app.intervals.array[i]['afterInterrupFunc']!==undefined)
					{
						window[window.app.intervals.array[i]['afterInterrupFunc']]();
					}
				}
				logger.enableLogger();
			}

			console.log('Запускаем интервал '+ this.array[i]['name']);
			this.array[i]['interval'] = setTimeout(this.array[i]['functionIntelval'],this.array[i]['delay']);
		}
	}
};

//Функция главного интервала
window["mainIntervalFuction"] = function(){
	console.log('Запускается функция mainIntervalFuction');
	console.log('Проверяем переменную window.app.readyForStart: ' + window.app.readyForStart);
	if(window.app.readyForStart==undefined)
	{
		//Проверяем на загруженость событий
		if(window.app.events===undefined)
		{
			console.log('Проверяем функцию');
			if(typeof initEvents == 'function')
			{ 
				initEvents(); 
			}
			else
			{
				return false;
			}
		}
		logger.enableLogger();
		console.log('События определены');
		logger.disableLogger();

		//Проверяем на загруженость файловой
		/*if(window.app.files===undefined)
		{
			if(typeof initFiles == 'function')
			{ 
				initFiles(); 
			}
			else
			{
				return false;
			}
		}
		logger.enableLogger();
		console.log('Файловая определенна');
		logger.disableLogger();*/

		//Проверяем на загруженость предзагрузчика
		if(window.app.preloader===undefined)
		{
			if(typeof initPreloader == 'function')
			{ 
				initPreloader();
			}
			else
			{
				return false;
			}
		}
		logger.enableLogger();
		console.log('Предзагрузчик определен');
		logger.disableLogger();

		/*if(window.app.connections===undefined)
		{
			if(typeof initConnections == 'function')
			{ 
				initConnections();
			}
			else
			{
				return false;
			}
		}
		console.log('Соединения определены');*/

		window.app.readyForStart=true;
		logger.enableLogger();
		preloaderStart();
	}

	//меняем время таймера
	return {delay:10000};
};

window.addEventListener('DOMContentLoaded',(event)=>{
	console.log('>>>>>>>>>>>>>>>>>> intervals.js <<<<<<<<<<<<<<<<<<<<<');
	//добавляем и запускаем главный интервал
	window.app.intervals.add(mainInterval);
});