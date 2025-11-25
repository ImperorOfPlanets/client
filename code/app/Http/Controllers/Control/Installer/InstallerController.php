<?php

namespace App\Http\Controllers\Control\Installer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Control\Sync\SyncDBController;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

use App\Models\Core\Objects;
use App\Models\Core\Groups;

use App\Helpers\Control\Ssh;
use App\Helpers\Control\CheckFiles;

use App\Jobs\ForControlProjects\sendJobForSync;
use App\Jobs\ForControlProjects\getResultForSync;
use App\Jobs\ForControlProjects\checkFilesSync;
use App\Jobs\ForControlProjects\checkDBSync;

class InstallerController extends Controller
{
	//Объект проекта
	public $object;

	//Не удалять - выкидывает из масс1ива на удаление во время синхронизации
	public $notDelete =[
		'storage/app',
		'storage/logs',
		'bootstrap/providers.php',
		'bootstrap/cache',
		'public/index.php',
		'public/robots.txt',
		'public/favicon.ico',
		'public/hot',
		'resources/css',
		'resources/views/errors'
	];

	//Могут быть изменены
	public $maybeChange = [
		'public/robots.txt',
		'public/index.php',
		'config',
		'basic.sql'
	];

	//Подключение
	public $ssh = null;

	//Временная папка
	public $tmpdir = null;

	//Список ОС со скриптами
	public $oss = null;

	//Результат
	public $response = [
		'terminal'=>[],
		'status'=>[],
		'message'=>[],
		'debug'=>[],
		'errors'=>[],
		'result'=>null
	];
	public $logs=[];

	//Порядок команд
	public $fullArrayCommands = [];

	//Конфиг nginx
	public $confNgnix = [
		'ssl'=>[],
		'fastcgi'=>[],
		'include'=>[],
		'gzip'=>[],
		'log'=>[]
	];

	//Логи
	public $stringForManually = [];

	//Команды
	public $commands = [];

	//Команды
	public $remote_DIRECTORY_SEPARATOR = null;

	//Символ объенинения
	public $remote_UNION_COMMAND = null;

	//Путь до проекта в папке storage
	public $projectPath;


	public function __construct()
	{
		//Объект проекта
		$this->object = Objects::find(request()->route()->parameter(array_key_first(request()->route()->parameters())));
		//Временная папка
		//$this->tmpdir = '/home/'.$this->object->propertyByID(33)->pivot->value.'/tmpdir';
	}

	//Вспомогательная функция возвращение результа
	public function returnResponse()
	{
		return response()->json($this->response,200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
	}

	public function index(Request $request)
	{
		$types = Groups::find(29)->objects;
		return view('control.installer.index',[
			'project'=>$this->object,
			'types'=>$types,
			//'oss'=>$this->getOSS(),
			//'phpExtensions'=> $this->phpExtensions
		]);
	}

	public function store(Request $request)
	{
		//Изменение значения - сохраняем в БД и если меняется ТИП СЕРВЕРА
		if($request->command == 'changeValue')
		{
			//Сохраняем значение
			if(is_null($request->property))
			{
				$this->response['alert'] = 'Отсуствует номер свойства';
			}
			else
			{
				if($this->object->propertyById($request->property)===null)
				{$this->object->propertys()->attach($request->property, ['value' => $request->value]);}
				else
				{$this->object->propertys()->updateExistingPivot($request->property,['value'=>$request->value]);}		
			}

			return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
		}

		//Получаем разделы для типа сервера
		if($request->command == 'getSections')
		{
			// Проверяем наличие свойства типа сервера
			$serverTypeProperty = $this->object->propertyById(40);
			if(!$serverTypeProperty || !$serverTypeProperty->pivot) {
				$this->response['alert'] = "Не выбран тип сервера (свойство ID:40, проект ID:{$this->object->id})";
				return $this->returnResponse();
			}

			$serverTypeId = $serverTypeProperty->pivot->value;

			// Получаем тип сервера
			$type = Objects::find($serverTypeId);
			if(!$type) {
				$this->response['alert'] = "Тип сервера не найден (ID:{$serverTypeId}, проект ID:{$this->object->id})";
				return $this->returnResponse();
			}

			// Получаем название типа сервера
			$typeNameProperty = $type->propertyById(1);
			$typeName = ($typeNameProperty && $typeNameProperty->pivot) ? $typeNameProperty->pivot->value : 'Без названия';

			// Проверяем наличие свойства разделов
			$sectionsProperty = $type->propertyById(102);
			if(!$sectionsProperty || !$sectionsProperty->pivot) {
				$this->response['alert'] = "Для типа сервера '{$typeName}' (ID:{$type->id}) не определены разделы (свойство ID:102)";
				return $this->returnResponse();
			}

			// Декодируем JSON с разделами
			$sectionsArray = json_decode($sectionsProperty->pivot->value, true);
			if(!$sectionsArray || !is_array($sectionsArray)) {
				$this->response['alert'] = "Некорректный формат разделов в свойстве ID:102 (тип сервера ID:{$type->id})";
				return $this->returnResponse();
			}

			$enabled = [];
			foreach($sectionsArray as $id => $section) {
				// Отсеиваем отключенные
				if(isset($section['enable']) && $section['enable']) {
					$sectionOBJ = Objects::find($id);
					if(!$sectionOBJ) {
						$this->response['alert'] = "Раздел с ID:{$id} не найден (тип сервера ID:{$type->id})";
						continue; // Пропускаем несуществующие разделы
					}

					// Получаем название раздела
					$nameProperty = $sectionOBJ->propertyById(1);
					$sectionName = ($nameProperty && $nameProperty->pivot) ? $nameProperty->pivot->value : "Без названия (ID:{$id})";

					// Добавляем данные раздела
					$section['name'] = $sectionName;
					$section['id'] = $id;
					$enabled[$id] = $section;
				}
			}

			if(empty($enabled)) {
				$this->response['alert'] = "Нет доступных разделов для типа сервера '{$typeName}' (ID:{$type->id})";
				return $this->returnResponse();
			}

			return response()->json($enabled, 200, [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
		}

		//Получает раздел
		if($request->command == 'getSection')
		{
			//Получаем тип проекта
			$typeProject = $this->object->propertyById(40)->pivot->value ?? null;
			if(is_null($typeProject))
			{
				return response()->json(['alert'=>'Не выбран тип сервера'],200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
			}


			//Получаем вид сервера
			$server = Objects::find($typeProject);

			//Получаем список секций данного вида сервера
			$sectionsJSON = $server->propertyById(102)->pivot->value ?? null;
			if(is_null($sectionsJSON))
			{
				return response()->json(['alert'=>'У данного вида сервера отсуствуют секции'],200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
			}

			$sections = json_decode($sectionsJSON,true);

			//Проверяем наличие секции
			if(!isset($sections[$request->id]))
			{
				return response()->json(['alert'=>'У данного вида сервера отсуствуют секция'],200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
			}

			//Проверяем что секция включена
			if(!isset($sections[$request->id]['enable']))
			{
				return response()->json(['alert'=>'У данного вида сервера отсуствуют секция'],200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
			}
			if($sections[$request->id]['enable'] === false)
			{
				return response()->json(['alert'=>'Секция отключена'],200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
			}

			
			else
			{
				$sectiosArray = json_decode($type->propertyById(102)->pivot->value,true);
				$enabled = [];
				foreach($sectiosArray as $id=>$section)
				{
					//Отсеиваеем отключенные
					if(isset($section['enable']) && $section['enable'])
					{
						$sectionOBJ = Objects::find($id);
						$section['name'] = $sectionOBJ->propertyById(1)->pivot->value;
						$enabled[$id] = $section;
					}
				}
				return response()->json($enabled,200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
			}
			
			$section = Objects::find($request->id);
			
			$pathForClass = 'App\\Helpers\\Control\\Panels\\'.$section->propertyById(35)->pivot->value;
			$viewClass = new $pathForClass;
			$viewClass->setIdProject($this->object);
			//Проверяем у секции массив блоков
			$blocksJSON = $section->propertyById(102)->pivot->value ?? null;
			$blocks = json_decode($blocksJSON);

			$viewClass->setBlocks($blocks);

			return $viewClass->view();
			if(is_null($blocks))
			{
				$viewClass->returnPathForView();
				return View::make($viewClass->returnPathForView(),[
					'object'=>$this->object,
					'variables'=>$viewClass->getVariablesForView(),
				]);
			}
			else
			{
				$viewClass->setBlocks($blocks);
				return $viewClass->view();
			}
		}

		//Получает раздел
		if($request->command == 'getBlock')
		{
			//Получаем тип проекта
			$typeProject = $this->object->propertyById(40)->pivot->value ?? null;
			if(is_null($typeProject))
			{
				return response()->json(['alert'=>'Не выбран тип сервера'],200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
			}

			//Получаем вид сервера
			$server = Objects::find($typeProject);

			//Получаем список секций данного вида сервера
			$sectionsJSON = $server->propertyById(102)->pivot->value ?? null;
			if(is_null($sectionsJSON))
			{
				return response()->json(['alert'=>'У данного вида сервера отсуствуют секции. Параметр 102'],200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
			}
			$sections = json_decode($sectionsJSON,true);

			//Проверяем наличие секции
			if(!isset($sections[$request->section]))
			{
				return response()->json(['alert'=>'В списке секций ID '.$request->section. ' отсуствует'],200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
			}

			//Проверяем что секция включена
			if(!isset($sections[$request->section]['enable']))
			{
				return response()->json(['alert'=>'В списке секций ID '.$request->section. ' отсуствует или отключена'],200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
			}
			if($sections[$request->section]['enable'] === false)
			{
				return response()->json(['alert'=>'В списке секций ID '.$request->section. ' отключена'],200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
			}

			//Проверяем детей
			if(!isset($sections[$request->section]['childrens']))
			{
				return response()->json(['alert'=>'В секций отсуствцуют дочерние элементы'],200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
			}

			//Проверяем блоки
			if(!isset($sections[$request->section]['childrens'][$request->block]))
			{
				return response()->json(['alert'=>'В данный блок в секции'],200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
			}

			//Проверяем включеность блока
			if($sections[$request->section]['childrens'][$request->block]['enable'] === false)
			{
				return response()->json(['alert'=>'Данный блок отключен'],200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
			}

			//Получаем блок
			$block = Objects::find($request->block);

			$pathProperty = $block->propertyById(35);

			if(is_null($pathProperty))
			{
				return response()->json(['alert'=>'Для блока '.$request->block.' - отсуствует класс'],200,[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE);
			}

			$pathForClass = 'App\\Helpers\\Control\\Blocks\\'.$block->propertyById(35)->pivot->value ?? null;

			//вывод блоков чепез класс
			$viewClass = new $pathForClass;
			$viewClass->setIdProject($this->object);
			$viewClass->setBlock($block);
			$viewClass->setPosition($sections[$request->section]['childrens'][$request->block]['position']);
			return $viewClass->view();
		}

		//Передает секции и блоку запрос на обработку
		if(isset($request->section) && isset($request->block) && isset($request->command))
		{
			//Сперва проверяем наличе класса обработки у блока
			$block = Objects::find($request->block);
			$propertyClass = $block->propertyById(35);

			//Проверяем наличие класса
			if(is_null($propertyClass))
			{
				$this->response['alert']='Не указан класс для обработки блока';
				return $this->returnResponse();
			}

			$pathForClass = 'App\\Helpers\\Control\\Blocks\\'.$block->propertyById(35)->pivot->value;
			$blockClass = new $pathForClass;
			$blockClass->block = $block;
			$blockClass->setIdProject($this->object);
			$blockClass->processRequest();
			return $blockClass->returnResult();
		}

		//Проверка
		if($request->command == 'check')
		{
			//Временная папка
			if($request->script=='composer')
			{
				$composer = $this->getComposer();
				$command = "$composer --version";
				$this->commands[] = $command;
			}
			elseif($request->script=='nvm')
			{
				$command = '
				export NVM_DIR="$([ -z "${XDG_CONFIG_HOME-}" ] && printf %s "${HOME}/.nvm" || printf %s "${XDG_CONFIG_HOME}/nvm")"
				[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
				';
				$this->commands[] = $command;
				$this->commands[] = 'nvm -v';
			}
			elseif($request->script=='nodejs')
			{
				$this->commands[] = 'nodejs -v';
			}
			elseif($request->script=='nginx')
			{
				//Получаем операционную систему
				$os = $this->object->propertyById(113)->pivot->value ?? null;
				if(is_null($os))
				{
					return;
				}
				elseif($os=='ubuntu')
				{
					$this->commands[] = 'nginx -v';
				}
			}
			elseif($request->script=='certbot')
			{
				//Получаем операционную систему
				$os = $this->object->propertyById(113)->pivot->value ?? null;
				if(is_null($os))
				{
					return;
				}
				elseif($os=='ubuntu')
				{
					$this->commands[] = 'certbot -v';
				}
			}
			//Считать конфигурационный файл nginx
			elseif($request->script=='readConfNginx')
			{
				$myPath = storage_path('tmp/'.$this->object->id.'-nginx-file.conf');
				$remotePath = $this->object->propertyById(97)->pivot->value;
				$this->ssh->downloadFile($myPath,$remotePath);

				//Считываем файл построчно
				$newLines = [];
				$file = file_get_contents($myPath);
				$this->response['res']=$file;
				$this->genConfNginx();
			}
			elseif($request->script=='nodejs')
			{
				$command = 'nodejs -v';
				$this->commands[]=$command;
				/*$resultCheck = $this->ssh->run($commandCheck);
				if(version_compare(str_replace("v","",$resultCheck),'18.0.0')<0)
				{
					return response()->json([
						'errors' => 'Версия nodejs должна быть не ниже 18.0.0'
					],200,[],JSON_UNESCAPED_UNICODE);
				}
				else
				{
					return response()->json([
						'status'=>'ok',
						'message' => 'Версия nodejs выше 18.0.0'
					],200,[],JSON_UNESCAPED_UNICODE);
				}*/
			}
			elseif($request->script=='npm')
			{
				$command = 'npm -v';
				$this->commands[]=$command;
			}
			//Сгенерировать файлы для копирования
			//Сохранить ключ сгенерировать команда
			elseif($request->script=='genFiles')
			{
				$SyncController = new SyncDBController($this->object->id);
				$SyncController->genFiles();
				$this->response['alert'] = "Файлы сгенерированы";
				$this->response['debug'] = $SyncController->logs;
				$this->response['message'] = $SyncController->all;
				$command = 'cd '.$this->getRemoteProjectPath().' && '.$this->getPHP().' artisan key:generate';
				$this->commands[] = $command;
			}
			//Список процессов
			elseif($request->script=='process')
			{
				$command = 'crontab -l';
				$this->commands[] = $command;
			}
			//Крон проверчная команда
			elseif($request->script=='cron')
			{
				$command = 'crontab -v';
				$this->commands[] = $command;
			}
			//Крол лист
			elseif($request->script=='cronlist')
			{
				$command = 'crontab -l';
				$this->commands[] = $command;
			}
			//Удалить задание
			elseif($request->script=='delCron')
			{
				//'crontab -l | grep "'$request->string." | awk '{print $1}' | xargs crontab -r

				$command= 'crontab -l | grep -v "'.$request->string.'" | awk \'{print $1}\' | xargs crontab -r';
				$this->commands[] = $command;
				//$command = 'crontab -l';
				//$this->commands[] = $command;
			}
			//Подготовить старые данные
			elseif($request->script=='exportOldData')
			{
				$os = $this->object->propertyById(113)->pivot->value ?? null;
				$symbol_union = '&&';
				if($os=='windows'){$symbol_union = '&';}
				if($this->object->id==32)
				{
					//chdir($this->getRemoteProjectPath());
					//dd(getcwd());
					$html = "Для CMD<br />";
					$commandCMD = 'cd '.$this->getRemoteProjectPath();
					$commandCMD = $commandCMD.' '.$symbol_union.' '.$this->getPHP().' '.$this->getRemoteProjectPath().'artisan command:ExportDB';
					//$commandCMD =  'cd '.$this->getRemoteProjectPath(). ' '.$symbol_union.' '.$this->getPHP().' "'.$this->getRemoteProjectPath(). 'artisan" "command:ExportDB"';
					$html = $html.$commandCMD.'<br /><br />';
					$html = $html."Для PowerShell - ДОДЕЛАТЬ<br />";
					//$commandPS =  'Start-Job { cd C:\control\localhost; php C:\control\localhost\artisan command:ExportDB }<br />Receive-Job -Job $job';
					//$html = $html.$commandPS;
					//$this->execInBackground('start cmd.exe @cmd /k "'.$commandCMD.'"');
					$this->response['alert'] = $html;
					//$this->execInBackground('start cmd.exe @cmd /k "'.$commandCMD.'"');
				}
				else
				{
					//Таблицы для экспорта
					$command =  'cd '.$this->getRemoteProjectPath(). ' '.$symbol_union.' '.$this->getPHP().' artisan command:ExportDB';
					$this->commands[] = $command;
				}
			}
		}

		//Синхронизация
		if($request->command == 'sync')
		{
			//Создафть файл с хешами
			if($request->script=='sendJobcreateHashes')
			{
				dispatch(new sendJobForSync([
					'id'=>$this->object->id,
					'data'=>[
						'command'=>'sync',
						'type'=>'files'
					]
				]))->onQueue('sync');
				$plus10minute = \Carbon\Carbon::now()->addMinutes(1);
				dispatch(new getResultForSync([
					'id'=>$this->object->id,
					'data'=>[
						'command'=>'get',
						'type'=>'files'
					]
				]))->onQueue('sync')->delay($plus10minute);
				$this->response['alert']='Задание добавлено';
			}
			//Создать бекап БД
			elseif($request->script=='sendJobcreateDB')
			{
				dispatch(new sendJobForSync([
					'id'=>$this->object->id,
					'data'=>[
						'command'=>'sync',
						'type'=>'db'
					]
				]))->onQueue('sync');

				//Вычисляем время через 10 минут
				$plus10minute = \Carbon\Carbon::now()->addMinutes(10);

				//Добавляем скачать результат через час возврашает last.sql
				dispatch(new getResultForSync([
					'id'=>$this->object->id,
					'data'=>[
						'command'=>'get',
						'type'=>'db'
					]
				]))->onQueue('sync');//->delay($plus10minute);
				$this->response['alert']='Задание добавлено';
			}
			//Проверить файлы
			elseif($request->script=='addJobcheckFiles')
			{
				dispatch(new checkFilesSync([
					'id'=>$this->object->id,
					'data'=>[
						'command'=>'sync',
						'type'=>'db'
					]
				]))->onQueue('sync');
				$this->response['alert']='Задание добавлено';
			}
			//Проверить БД
			elseif($request->script=='addJobcheckDB')
			{
				dispatch(new checkDBSync([
					'id'=>$this->object->id,
					'data'=>[
						'command'=>'sync',
						'type'=>'db'
					]
				]))->onQueue('sync');
				$this->response['alert']='Задание добавлено';
			}
			//Получеть результаты сравнения в бэке
			elseif($request->script=='getDataForCompareFiles')
			{
				//Получаем последний файл с хешами
				$checkerFiles = new CheckFiles([
					'id'=> $this->object->id
				]);
				if($request->type=='remote')
				{
					$checkerFiles->getLastHashes();
					$res = $checkerFiles->getJsonTreeRemote();
				}
				elseif($request->type=='standart')
				{
					$checkerFiles->getStandartHashes();
					$res = $checkerFiles->getJsonTreeStandart();
				}
				return response()->json($res,200,[],JSON_UNESCAPED_SLASHES);
			}
			//Получить результат сравнения во фронте
			elseif($request->script=='createJobForSync')
			{
				$jsonArray = $request->array;
				$arrayWorks = json_decode($jsonArray, true);
				//ДОДЕЛАТЬ Отправляем на техническое обслуживание
				/*dispatch(new sendJobForSync([
					'id'=>$this->object->id,
					'data'=>[
						'command'=>'update',
						'type'=>'files',
						//'secret'=>,
						//'redirect'=>'down'
					]
				]))->onQueue('sync');*/
				//Создаем архив с файлами на копирование
				$pathFolder = storage_path('sync/'.$this->object->id.'/forCopy');

				//Проверяем папку
				if(!is_dir($pathFolder))
				{
					mkdir($pathFolder, 0777, true);
				}
				$file_name = date('Y-m-d H-i');
				$this->createZIP($arrayWorks['copy'],$pathFolder,$file_name);

				//Пересобираем массив удалений
				foreach($arrayWorks['delete'] as $keyP => $path)
				{
					foreach($this->notDelete as $path2)
					{
						if(str_contains($path,$path2))
						{
							unset($arrayWorks['delete'][$keyP]);
						}
					}
				}

				//Пересобираем массив копирований
				foreach($arrayWorks['copy'] as $keyP => $path)
				{
					foreach($this->maybeChange as $path2)
					{
						if(str_contains($path,$path2))
						{
							unset($arrayWorks['copy'][$keyP]);
						}
					}
				}

				//Отправляем задание на синхрон! Архив + то что нужно удалить
				dispatch(new sendJobForSync([
					'id'=>$this->object->id,
					'data'=>[
						'command'=>'sync',
						'type'=>'start',
						'delete'=>json_encode($arrayWorks['delete'],JSON_UNESCAPED_SLASHES)
					],
					'attach'=>$pathFolder.DIRECTORY_SEPARATOR.$file_name,
					'attach_name'=>$file_name
				]))->onQueue('sync');
				//
				dd($arrayWorks);
			}
			//Технические работы
			elseif($request->script=='down')
			{
				if($request->type=='on')
				{
					$random = Str::random(40);

					dispatch(new sendJobForSync([
						'id'=>$this->object->id,
						'data'=>[
							'command'=>'down',
							'type'=>'on',
							'secret'=>$random,
							//'redirect'=>'down'
						]
					]))->onQueue('sync');
					$this->response['alert']='Отключен.<br/><a href="'.$this->object->propertyByID(77)->pivot->value.'/'.$random.'">Перейти в проект</a>';
				}
				elseif($request->type=='off')
				{
					dispatch(new sendJobForSync([
						'id'=>$this->object->id,
						'data'=>[
							'command'=>'down',
							'type'=>'off',
							//'secret'=>,
							//'redirect'=>'down'
						]
					]))->onQueue('sync');
					$this->response['alert']='Включен';
				}
			}
			//Показать результаты проверки
			elseif($request->script=='showLastcheckFiles')
			{

				$path = storage_path('sync/results/'.$this->object->id.DIRECTORY_SEPARATOR.'files');

				//Получаем список файлов
				$items = glob($path.'/*');

				//Сортируем
				usort($items,array($this,'compareDates'));
				//Получаем последний ключ массива
				$lastKey = array_key_last($items);
				//Считываем файл
				$context = file_get_contents($items[$lastKey]);
				$strings = explode("\n",$context);
				$works = [];
				foreach($strings as $keyString=>$string)
				{
					if(strlen($string)>5)
					{
						$explodedString = explode('->',$string);
						try
						{
							$work[$explodedString[0]]=unserialize($explodedString[1]);
					
						}
						catch(\Exception $e)
						{
							dd($strings,$string,$explodedString);
						}
					}
				}
				dd($work);
				dd($context);
				dd($items);
			}
		}

		//Установка и создание
		if($request->command == 'install')
		{
			//Создать временную папку
			if($request->script=='tmpdir')
			{
				$remotePathTMP = $this->getRemotePathTMP();
				$this->commands[] = 'mkdir -p '.$remotePathTMP;
				//$remotePathTMP = $this->getRemotePathTMP();
				//$this->commands[] = 'cd '.$remotePathTMP;
			}		
			//Отправить комманду с интерфейса
			elseif($request->script=='sendcommand')
			{
				//Запускаем скрипт добавления репозитория
				if(strlen($request->sendcommand)>3)
				{
					$sshInstallCommandPHP =$request->sendcommand;
					$this->ssh->run($sshInstallCommandPHP);
				}
				else
				{
					$this->response['errors'][] = 'Укажите репозиторий';
				}
			}
			//Добавить репозиторий
			elseif($request->script=='addrep')
			{	//Запускаем скрипт добавления репозитория
				if(strlen($request->rep)>3)
				{
					$command = "add-apt-repository ".$request->rep;
					$this->commands[] = $command;
					//$this->ssh->run($sshInstallCommandPHP);
				}
				else
				{
					$this->response['errors'][] = 'Укажите репозиторий';
				}
			}
			//PHP Extensions
			elseif(str_starts_with($request->script,'php') and strlen($request->script)>3)
			{
				$os = $this->object->propertyById(113)->pivot->value ?? null;
				if(is_null($os))
				{
					return;
				}
				elseif($os=='ubuntu')
				{
					$script = str_replace("php",'',$request->script);
					$command = 'apt install php8.3-'.$script;
					$this->commands[] = $command;
				}
				//$resultCheck = $this->ssh->run($commandInstall);
				//dd($resultCheck);
			}
			elseif($request->script=='unzip')
			{
				//Получаем операционную систему
				$os = $this->object->propertyById(113)->pivot->value ?? null;
				if(is_null($os))
				{
					return;
				}
				elseif($os=='ubuntu')
				{
					$this->commands[]='apt install unzip';
				}
			}
			//Установить composer
			elseif($request->script=='composer')
			{
				//Получаем операционную систему
				$os = $this->object->propertyById(113)->pivot->value ?? null;
				if(is_null($os))
				{
					return;
				}
				elseif($os=='ubuntu')
				{
					$this->commands[]='cd ~';
					$this->commands[]='curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php';
					$this->commands[]='php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer';
				}
			}
			//Установить NVM
			elseif($request->script=='nvm')
			{
				//Получаем операционную систему
				$os = $this->object->propertyById(113)->pivot->value ?? null;
				if(is_null($os))
				{
					return;
				}
				elseif($os=='ubuntu')
				{
					$this->commands[]='wget -qO- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash';
				}
			}
			//Установить NODEJS
			elseif($request->script=='nodejs')
			{
				//Получаем операционную систему
				$os = $this->object->propertyById(113)->pivot->value ?? null;
				if(is_null($os))
				{
					return;
				}
				elseif($os=='ubuntu')
				{
					$this->commands[]='apt install nodejs';
				}
			}
			//Установить mariadb
			elseif($request->script=='mariadb')
			{
				//Генерируем название БД
				$p88 = $this->object->propertyById(88)->pivot->value ?? null;
				if(is_null($p88))
				{
					$this->response['alert']='Не указана база данных';
				}
				if(empty($p88))
				{
					$this->response['alert']='Не указана база данных - Пустота';
					//break;
				}

				$p89 = $this->object->propertyById(89)->pivot->value ?? null;
				if(is_null($p89))
				{
					$this->response['alert']='Не указан логин';
				}
				if(empty($p89))
				{
					$this->response['alert']='Не указан логин - Пустота';
				}

				$p90 = $this->object->propertyById(90)->pivot->value ?? null;
				if(is_null($p90))
				{
					$this->response['alert']='Не указан пароль';
				}
				if(empty($p90))
				{
					$this->response['alert']='Не указан пароль - Пустота';
				}

				$nameDB = $p88;
				$userDB = $p89;
				$passwordDB = $p90;

				//Получаем операционную систему
				$os = $this->object->propertyById(113)->pivot->value ?? null;
				if(is_null($os))
				{
					return;
				}
				elseif($os=='ubuntu')
				{
					$this->commands[] = 'apt update';
					$this->commands[] = 'apt install software-properties-common -y';
					$this->commands[] = 'apt-add-repository universe';
					$this->commands[] = 'apt update';
					$this->commands[] = 'apt install mariadb-server -y';
					$this->commands[] = 'systemctl start mysql';
					$this->commands[] = 'systemctl status mysql';
					$this->commands[] = "mysql -e 'CREATE DATABASE $nameDB;'";
					//$this->commands[] = "mysql -e 'CREATE OR REPLACE USER '$userDB' IDENTIFIED BY PASSWORD '$passwordDB';'";
					//CREATE OR REPLACE USER foo2@test IDENTIFIED BY 'password';
					$this->commands[] = "mysql -e 'CREATE USER $userDB@localhost IDENTIFIED BY \"$passwordDB\";'";
					$this->commands[] = "mysql -e 'GRANT ALL PRIVILEGES ON `$nameDB`.* TO `$userDB`@`localhost`;'";
					$this->commands[] = "mysql -e 'FLUSH PRIVILEGES;'";
					$this->commands[] = "mysql -e 'SELECT user,host FROM mysql.user;'";
				}
			}
			//Сохранить конфигурацию Nginx
			elseif($request->script=='nginx')
			{
				//Получаем операционную систему
				$os = $this->object->propertyById(113)->pivot->value ?? null;
				$domains = $this->getDomains();
				if(is_null($os))
				{
					return;
				}
				elseif($os=='ubuntu')
				{
					$this->commands[] = 'apt update';
					$this->commands[] = 'apt install nginx';
					$this->commands[] = 'ufw status';
					//$this->commands[] = 'mkdir -p /var/www/'.$domains[0].'/public';
					//$this->commands[] = 'nano /etc/nginx/sites-available/'.$domains[0].'.conf';
					//$this->commands[] = $this->genFirstNginxConf();
					//$this->commands[] = 'rm /etc/nginx/sites-enabled/default';
					//$this->commands[] = 'systemctl restart nginx';
					//Создаем файл
				}
			}
			elseif($request->script=='certbot')
			{
				//Получаем операционную систему
				$os = $this->object->propertyById(113)->pivot->value ?? null;
				if(is_null($os))
				{
					return;
				}
				elseif($os=='ubuntu')
				{
					$this->commands[] = 'apt install certbot python3-certbot-nginx';
					$this->commands[] = 'certbot register --email glok87@list.ru';
					$commandsInstallCets = 'certbot --nginx';
					$domains = $this->getDomains();
					foreach($domains as $domain)
					{
						$commandsInstallCets = $commandsInstallCets.' -d '.$domain;
					}
					$this->commands[] = $commandsInstallCets;
					//dd($this->commands);
				}
			}
			//Генерация конфига для Nginx
			elseif($request->script=='genNginxConf')
			{
				$this->genNginxConf();
			}	
			//Вернуть сгенерированный конфиг Nginx
			elseif($request->script=='generatedNginxConf')
			{
				$this->response['result'] = $this->object->propertyById(145)->pivot->value ?? null;
			}
			//Установка Nginx
			elseif($request->script=='newConfNginx')
			{
				File::put(storage_path('tmp/'.$this->object->id.'-nginx-file.ready.conf'),$request->conf);
			}
			//Установить projectdir
			elseif($request->script=='projectdir')
			{
				//root /var/www/html/your-project-name/public;
				//$commandCreate = 'mkdir /home/'.$this->object->propertyByID(33)->pivot->value.'/tmpdir';
				//$this->logs[] = $this->ssh->run($commandCreate);
			}
			//Установить laravel
			elseif($request->script=='laravel')
			{
				$remotePathProject = $this->getRemoteProjectPath();
				if($this->object->id==32)
				{
					$command = 'composer create-project laravel/laravel '.$remotePathProject;

					$this->execInBackground('start cmd.exe @cmd /k "'.$command.'"');
				}
				else
				{
					ini_set('max_execution_time', 300);
					$php = $this->getPHP();
					$composer = $this->getComposer();
					//Проверяем если композер phar то добавляем в начале php
					if(strpos($composer,'phar') !== false)
					{
						$command = $php.' '.$composer.' create-project laravel/laravel '.$remotePathProject;
					}
					else
					{
						$command = $composer.' create-project laravel/laravel '.$remotePathProject;
					}
					$this->commands[] = 'cd '.$remotePathProject;
					$this->commands[] = $command;
				}
			}
			//Intervention/image
			elseif($request->script=='interventionimage')
			{
				$remoteProjectPath = $this->getRemoteProjectPath();
				$php = $this->getPHP();
				$composer = $this->getComposer();

				$command = 'cd '.$remoteProjectPath. ' && '.$php.' '.$composer.' require intervention/image';
				$this->commands[] = $command;
			}
			//Сохранить ключ сгенерировать команда
			elseif($request->script=='newAPPKEY')
			{
				if($this->object->id==32)
				{
					$os = $this->object->propertyById(113)->pivot->value ?? null;
					$symbol_union = '&&';
					if($os=='windows'){$symbol_union = '&';}
					$command = 'cd '.$this->getRemoteProjectPath().' '.$symbol_union.' '.$this->getPHP().' artisan key:generate';
					dd($command);
					//passthru($command);
					//$this->execInBackground('start cmd.exe @cmd /k "'.$command.'"');
				}
				else
				{
					$command = 'cd '.$this->getRemoteProjectPath().' && '.$this->getPHP().' artisan key:generate';
					$this->commands[] = $command;
				}
			}
			//Установить таблицы
			elseif($request->script=='runsql')
			{
				if($this->object->id==32)
				{
					//$command = '"C:\Program Files\MariaDB 11.4\bin\mysql.exe -u '.$this->object->propertyByID(89)->pivot->value.' --password=\''.$this->object->propertyByID(90)->pivot->value.'\' -D '.$this->object->propertyByID(88)->pivot->value.' < '.$this->getRemoteProjectPath().'basic.sql"';
					$command = '"C:\\Program Files\\MariaDB 11.4\\bin\\mysql.exe" -u ' . $this->object->propertyByID(89)->pivot->value . ' --password=' . $this->object->propertyByID(90)->pivot->value . " -D " . $this->object->propertyByID(88)->pivot->value . ' < "' . $this->getRemoteProjectPath() . 'basic.sql"';
					//dd($command);
					//$command = "'C:\Program Files\MariaDB 11.4\bin\mysql.exe' -u ".$this->object->propertyByID(89)->pivot->value.' --password='.$this->object->propertyByID(90)->pivot->value.' -D '.$this->object->propertyByID(88)->pivot->value.' < '.$this->getRemoteProjectPath().'basic.sql ';
					$this->execInBackground('start cmd.exe @cmd /k "'.$command.'"');
					//$this->execInBackground($command);
				}
				else
				{
					$command = 'mysql -u '.$this->object->propertyByID(89)->pivot->value.' --password=\''.$this->object->propertyByID(90)->pivot->value.'\' -D '.$this->object->propertyByID(88)->pivot->value.' < '.$this->getRemoteProjectPath().'/basic.sql';
					$this->commands[] = $command;
					$this->commands[] = $this->object->propertyByID(90)->pivot->value;
				}

			}
			//Показывает список процессов
			elseif($request->script=='process')
			{
				$command = 'crontab -l';
				$this->commands[] = $command;
			}
			//Добаляем задание в crontab
			elseif($request->script=='cronlist')
			{
				$remotePathProject = $this->getRemoteProjectPath();
				$getPHP = $this->getPHP();
				$username = $this->object->propertyByID(33)->pivot->value;
				$croncommand = $getPHP.' '.$remotePathProject.'/artisan schedule:run';
				$time = '*/10 * * * *';
				$croncommand = $time. " ".$croncommand;
				$command = 'echo "'.$croncommand.'" | crontab -u '.$username.' -';
				$this->commands[] = $command;
			}
			//Добаляем библиотеку Telegram 
			elseif($request->script=='tgbot')
			{
				$remotePathProject = $this->getRemoteProjectPath();
				$php = $this->getPHP();
				$composer = $this->getComposer();

				$command = 'cd '.$remotePathProject. ' && '.$php.' '.$composer.' require longman/telegram-bot';
				$this->commands[] = $command;
			}
			//Добаляем библиотеку Guzzle
			elseif($request->script=='guzzle')
			{
				$remotePathProject = $this->getRemoteProjectPath();
				$php = $this->getPHP();
				$composer = $this->getComposer();

				$command = 'cd '.$remotePathProject. ' && '.$php.' '.$composer.' require guzzlehttp/guzzle';
				$this->commands[] = $command;
			}
			//Добаляем библиотеку VK
			elseif($request->script=='vk')
			{
				$remotePathProject = $this->getRemoteProjectPath();
				$php = $this->getPHP();
				$composer = $this->getComposer();

				$command = 'cd '.$remotePathProject. ' && '.$php.' '.$composer.' require vkcom/vk-php-sdk';
				$this->commands[] = $command;
			}
			//Скачать экспортированную базу данных
			elseif($request->script=='exportOldData')
			{
				$this->ssh = new Ssh(['object_id'=>$this->object->id]);
				$directoryForBackups = storage_path('backups'.DIRECTORY_SEPARATOR.$this->object->id);
				if(!is_dir($directoryForBackups))
				{
					mkdir($directoryForBackups,0777,true);
				}
				$directoryForBackupsWithName = storage_path('backups'.DIRECTORY_SEPARATOR.$this->object->id.DIRECTORY_SEPARATOR.'last.sql');
				$remoteProjectPath = $this->getRemoteProjectPath();
				$remotePathBackup = $remoteProjectPath."/backups/last.sql";
				$this->ssh->downloadFile($directoryForBackupsWithName,$remotePathBackup);
			}
			//Возврашает строку для исполнения крон команды
			elseif($request->script=='croncommand')
			{
				$remotePathProject = $this->getRemoteProjectPath();
				$php = $this->getPHP();
				$command = 'cd '.$remotePathProject. ' && '.$php.' artisan '.$request->value;
				$this->commands[] = $command;
			}
			//
			elseif($request->script=='bashcommand')
			{
				$scriptBashPath = storage_path('bash'.DIRECTORY_SEPARATOR.$request->value);
				$context = file_get_contents($scriptBashPath);
				$this->commands[] = $context;
				//$this->commands[] = $command;
			}
		}

	}



					/*					Дополнительные функции					*/




	//Поиск массива значений в строке
	public function strpos_array($haystack,$needles)
	{
		if(is_array($needles))
		{
			foreach($needles as $str)
			{
				if(is_array($str))
				{
					$pos = strpos_array($haystack,$str);
				}
				else
				{
					$pos = strpos($haystack,$str);
				}
				if($pos!==FALSE)
				{
					return $pos;
				}
			}
		}
		else
		{
			return strpos($haystack, $needles);
		}
	}

	//Копируем скрипт на временную папку
	public function copyScript()
	{
		$pathScript = storage_path('scripts'.DIRECTORY_SEPARATOR.$this->object->propertyByID(113)->pivot->value.DIRECTORY_SEPARATOR.request()->script.'.sh');
		$remotePath = '/home/'.$this->object->propertyByID(33)->pivot->value.'/tmpdir/'.request()->script.'.sh';
		//Проверяем файл на наличие
		if($fileCheck = $this->ssh->checkFile($remotePath))
		{
			$this->logs[]='Файл присуствует!';
			$this->ssh->removeFile($remotePath);
		}
		$this->ssh->sendFile($pathScript,$remotePath,$chmod=0644);
		$this->logs[] = 'Файл скопирован';
	}



	//Получаем путь до проекта на сервере
	public function getRemoteProjectPath()
	{
		if(!is_null($this->object->propertyByID(64)) && strlen($this->object->propertyByID(64)->pivot->value)>2)
		{
			$remoteProjectPath = $this->object->propertyByID(64)->pivot->value;
			if(substr($remoteProjectPath,-1)=='/')
			{
				$remoteProjectPath = substr($remoteProjectPath, 0, -1);
			}
			if(substr($remoteProjectPath,-1)=='\\')
			{
				$remoteProjectPath = substr($remoteProjectPath, 0, -1);
			}
		}
		else
		{
			$remoteProjectPath='/var/www';
		}
		return rtrim($remoteProjectPath);
	}

	//Получаем временную папку на сервере
	public function getRemotePathTMP(){
		if(!is_null($this->object->propertyByID(114)) && strlen($this->object->propertyByID(114)->pivot->value)>2)
		{
			$remotePathTMP = $this->object->propertyByID(114)->pivot->value;
			if(substr($remotePathTMP,-1)=='/')
			{
				$remotePathTMP = substr($this->remoteProjectPath, 0, -1);
			}
		}
		else
		{
			$remotePathTMP='/home/'.$this->object->propertyByID(33)->pivot->value.'/tmpdir';
		}
		return $remotePathTMP;
	}

	//Герерация списка команд
	public function getArrayCommands(){
		$turn = [
			['block'=>'OS','command'=>'check','script'=>'os'],
			['block'=>'TMP DIR','command'=>'check','script'=>'tmp'],
			['block'=>'TMP DIR','command'=>'install','script'=>'tmp'],
			['block'=>'OS UPDATE','command'=>'check','script'=>'os'],
			['block'=>'PHP','command'=>'check','script'=>'php'],
			['block'=>'PHP','command'=>'install','script'=>'php']
		];
	}



	//Сохраниние env файла //Возвращает путь до файла на удаленной машине
	public function setENV()
	{
		$myPath = storage_path('tmp'.DIRECTORY_SEPARATOR.$this->object->id);
		$myPathENV = $myPath.".env";

		$this->projectPath = storage_path('projects'.DIRECTORY_SEPARATOR.$this->object->id);
		$this->createDIR($this->projectPath);
		File::put($myPathENV,request()->ENV."\r\n");
		$myPathENV = $this->projectPath.DIRECTORY_SEPARATOR.'.env';
		File::put($myPathENV,request()->ENV."\r\n");
		
		$remoteProjectPath = $this->getRemoteProjectPath();
		$remotePathProjectENV = $remoteProjectPath."/.env";
		$this->ssh = new Ssh(['object_id'=>$this->object->id]);
		$this->ssh->sendFile(
			[$myPathENV],[$remotePathProjectENV]
		);
		return $remotePathProjectENV;
	}



	//Проверить папку и если нету то создать
	public function createDIR($path)
	{
		if(!is_dir($path))
		{
			mkdir($path,0755,true);
		}
	}


	//Получить сепаратор удаленной машины
	public function getRemoteDirectorySeparator()
	{
		if(is_null($this->remote_DIRECTORY_SEPARATOR))
		{
			//Получаем операционную систему
			$os = $this->object->propertyById(113)->pivot->value ?? null;
			if(is_null($os))
			{
				return;
			}
			elseif($os=='ubuntu')
			{
				$this->remote_DIRECTORY_SEPARATOR= '/';
			}
			elseif($os=='windows')
			{
				$this->remote_DIRECTORY_SEPARATOR= '\\';
			}
			else
			{
				$this->remote_DIRECTORY_SEPARATOR= '/';
			}
		}
		return $this->remote_DIRECTORY_SEPARATOR;
	}

	// Функция для сравнения двух строк, представляющих даты и время
	private function compareDates($file1,$file2)
	{
		$dateTime1 = \DateTime::createFromFormat('Y-m-d H-i', basename($file1));
		$dateTime2 = \DateTime::createFromFormat('Y-m-d H-i', basename($file2));
		return $dateTime1 > $dateTime2;
	}
}