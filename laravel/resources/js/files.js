window['initFiles'] = function()
{
	console.log('Начал инициализацию файловой системы');

	if(window.app.files!=undefined)
	{
		console.log('Файловая система не инициализирована');
		return false;
	}

	//Интервал
	var filesInterval =
	{
		//Название интервала
		name:"files",
		//Запускаемая функция
		cycFunction:"filesIntervalFuction",
		//Период цикличности
		delay:1000,
		logger:false
	};

	window.app.intervals.add(filesInterval);

	console.log('files set');
	//статусы
		//download - скачивается
		//downloaded - скачен
	window.app.files = {
		//Массив файлов
		array:[],
		//Очередь файлов
		queue:[],
		//Найти файл в массиве по названию
		searchByName:function(name)
		{
			for(const indexFile in this.array)
			{
				if(this.array[indexFile]['name']==name) return indexFile;
			}
		},
		//Добавить
		add:function(file)
		{
			console.log('ДОБАВЛЯЕМ ФАЙЛ В МАССИВ: '+ file.name);
			//Проверяем по имени в массиве
			var indexFile = this.searchByName(file.name);
			//Проверяем его наличие
			if(indexFile==undefined)
			{
				this.array.push(file);
			}
			else
			{console.warn('Файл ' + file.name + ' существует');
			}
		},
		//Установить функцию которая стреляет после скачки всех файлов
		setCallbackForAllDownloaded:function(nameFunction)
		{
			this.callbackAllDownloaded.fired=false;
			this.callbackAllDownloaded.nameFunction=nameFunction;
		},
		//Функция запускаемая после загрузки всех файлов
		callbackAllDownloaded:
		{
			//название функции
			nameFunction:null,
			//функция исполнена
				//true если исполнена
			fired:false
		},
		//Запустить загрузку всех файлов
			//callback - функция вызываемая после скачки всех файлов
		loadAllFiles:function(callback=null)
		{
			console.log('Запускаю загрузку всех файлов');
			for(const indexFile in this.array)
			{
				if(this.array[indexFile]['status']!='downloaded')
				{
					this.loadFile(indexFile);
				}
			}
		},
		//Запускает загрузку файла
		loadFile:function(indexFile)
		{
			console.log('Запускаю загрузку файла: ' + this.array[indexFile]['name']);
			console.log(this.array[indexFile]);

			if(this.array[indexFile]['status']==undefined)
			{
				console.log('Запускаем скачивание');
				if(this.array[indexFile]['method']=='get')
				{
					//Проверяем зависимости
					if(!this.checkDepend(indexFile))
					{return false;}
					var done = false;var file = null;var fileExt = this.array[indexFile]['path'].split('.').pop();

					if(fileExt=='css')
					{
						file = document.createElement('link');
						file.onload = handleLoad;
						file.onreadystatechange = handleReadyStateChange;
						file.onerror = handleError;
						file.href = this.array[indexFile]['path'];
						file.rel = 'stylesheet';
					}
					else if(fileExt=='js')
					{
						file = document.createElement('script');
						file.src = this.array[indexFile]['path'];
						file.onload = handleLoad;
						file.onreadystatechange = handleReadyStateChange;
						file.onerror = handleError;
						file.type = 'text/javascript';
					}

function handleLoad()
{
	if(!done)
	{
		done = true;
		window.app.files.loadCallback(indexFile, "ok");
	}
}
function handleReadyStateChange()
{
	var state;
	if (!done)
	{
		state = scr.readyState;
		if (state === "complete")
		{
			handleLoad();
		}
	}
}
function handleError()
{
	if (!done)
	{
		done = true;
		window.app.files.loadCallback(indexFile, "error");
	}
}

					preText('Запрашиваем документ ' + this.array[indexFile]['name'],'blue');
					document.getElementsByTagName('head')[0].appendChild(file);
					this.array[indexFile]['status']='download';
				}
			}

			if(this.array[indexFile]['status']=='download')
			{
				console.log('Файл скачивается');
			}

			if(this.array[indexFile]['status']=='downloaded')
			{
				console.log('Файл скачан');
			}
		},
		//Запускает после скачки
		loadCallback:function(indexFile,result)
		{
			if(result=='ok')
			{
				this.array[indexFile]['status']='downloaded';
				preText(' Документ '+ this.array[indexFile]['name']+' доставлен','green');
			}
			if(result=='error')
			{
				this.array[indexFile]['status']='error';
				preText('Документ '+this.array[indexFile]['name']+' не смогли доставить','red');
			}
			//Ищем файл и указываем нужные переменные
		},
		//Проверка зависимостей файлов
		checkDepend:function (indexFile)
		{
			//Зависимости неопределенны
				//true - можно качать
			if(this.array[indexFile]['depend']===undefined)
			{
				preText('Файл '+this.array[indexFile]['name']+' зависимостей нет','green');
				return true;
			}
			else
			{
				//Перебираем зависимости
				for(const indexDepend in this.array[indexFile]['depend'])
				{
					var nameDepend = this.array[indexFile]['depend'];
					preText('Проверяем зависимый файл');
					console.error('Проверяем зависимый файл недоделано');
				}
			}
		},
		//Установить тип загрузки
		setTypeDownload:function(IndexOrName,type=null)
		{
			var index=null;
			//Если строка ищем индекс
			if(typeof IndexOrName == 'string')
			{index = this.searchByName(IndexOrName);}
			else
			{
				if(this.array[IndexOrName]!==undefined){index=IndexOrName;}
				else{console.error('setTypeDownload: Файл с индексом ' + IndexOrName + ' невозможно найти');}
			}

			//Проверяем индекс
			if(index==null || index==undefined)
			{console.error('setTypeDownload: IndexOrName ' + IndexOrName + ' невозможно найти'); return false;}
			else
			{this.array[index]['method']=type;}
		}
	};
}

	
//Обработчик загрузки файлов
window['filesIntervalFuction'] = function(){
	//Проверяем количество файлов
	if(window.app.files.array.length == 0)
	{
		return {delay:5000};
	}
	console.log('Запущена filesIntervalFuction');
	console.log('Количество файлов - '+window.app.files.array.length);
	//Проходим по файлам
	for(const indexFile in window.app.files.array)
	{
		console.log('Проверяем файл ' + window.app.files.array[indexFile]['name']);
		console.log(window.app.files.array[indexFile]);
		//скачан
		if(window.app.files.array[indexFile]['status']=='downloaded')
		{
			console.log('скачан проверяем на fire');
		}

		//скачивается
		if(window.app.files.array[indexFile]['status']=='download')
		{
			console.log('скачачивается проверяем на fire');
			//Счетчик срабатываний
			if(window.app.files.array[indexFile]['intervalFireCount']==undefined)
			{
				window.app.files.array[indexFile]['intervalFireCount']=1;
			}
			else
			{
				window.app.files.array[indexFile]['intervalFireCount']++;
			}
		}
	}
}