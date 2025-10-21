<?php

namespace App\Http\Controllers\Control\Sync;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use stdClass;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\File;

use App\Helpers\Ssh;

use App\Models\Core\Objects;
use App\Models\Core\Groups;
use App\Models\Core\Propertys;
use App\Models\Core\Params;

class SyncDBController extends Controller
{
	//Переменные для передачи
	public $all = [
		'copy'=>[
			['Откуда','Куда']
		],
		'gen'=>[
			['Куда','Что']
		]
	];

	//Основные файлы
	public $basicFoldersAndFiles = [
		'app/Console',
		'app/Helpers/Socials',
		'app/Helpers/Files',
		'app/Helpers/Bot',
		'app/Http/Controllers/Management',
		'app/Http/Middleware/Management.php',
		'app/Providers/AppServiceProvider.php',
		'app/Jobs',
		'app/Models/Settings/Keywords',
		'app/Models/Propertys.php',
		'app/Services',
		'bootstrap/app.php',
		'resources/views/management',
		'resources/views/template',
		'resources/views/vendor',
		'resources/views/welcome.blade.php',
		'resources/sass/app.scss',
		'resources/js',
		'routes/project.php',
		'routes/management.php',
		'routes/console.php',
		'storage/migrations',
		'public/js',
		'public/img',
		'lang',
		'storage/migrations'
	];

	//Пропускаемые файлы
    public $skipFiles = [
        //'app/Http/Controllers/Controller.php',
		'app/Jobs/ForControlProjects',
		'app/Models/Core',
		'app/Console/Commands/Control'
    ];

	//Массив вставок для настроек сайта
	public $lastInsertForSQL =[];

	//Объект проекта
	public $object;

	//Папки для создания на удаленке
	public $DirsForCreate = [];

	//Массив для копирования
	public $ForSend = [];

	//Объект проекта
	public $essence;

	//Объект синхронизации
	public $sync;

	public $sql;

	public $params;

	public $logs = [];

	//Переменные удаленного сервера
	public $remote = [
		'separator'=>null,
	];

	//Папка проекта в каталоге storage
	public $projectPath;

	//Массив для создания миграций
	public $arrayForMigrations = ['create_sessions_table','create_jobs_table','create_failed_jobs_table'];

    public function __construct($idObject = null)
    {
		if(is_null($idObject))
		{
			//Объект проекта
			$this->object = Objects::find(request()->route()->parameter(array_key_first(request()->route()->parameters())));
		}
		else
		{
			$this->object = Objects::find($idObject);
		}

		//Переменные удаленого сервера
		$os = $this->object->propertyById(113)->pivot->value ?? null;

		if(is_null($os))
		{
			$this->remote['separator'] = '/';
		}
		elseif($os=='windows')
		{
			$this->remote['separator'] = '\\';
		}
		else
		{
			$this->remote['separator'] = '/';
		}

		//Синхронизатор
		$this->sync = new stdClass();
		$this->sync->propertys = [];

		//Получаем свойства команд
		//$this->time = time();

		//Удаляем все файлы
		$this->projectPath = storage_path('projects'.DIRECTORY_SEPARATOR.$this->object->id);

		//dd($this->projectPath);
		$commandRM = 'rm -r '.$this->projectPath.'*';

		//Создаем папку проекта если она отсуствует
		if(!is_dir($this->projectPath))
		{
			mkdir($this->projectPath,0775,true);
		}

		//Получаем все параметры
		$this->params = Params::all();

		//Подготовка к SQL
		$this->sql ="SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\nSET AUTOCOMMIT = 0;\nSTART TRANSACTION;\nSET time_zone = \"+00:00\";SET names utf8;\n\n";
		
	}

			/*              Копирование                       */

	//Подготовка файлов для копирования
	public function copyFiles()
	{
		//Скинарует папку и добавляет в список на отправку
		$this->scanPath('app');
		$this->scanPath('bootstrap');
		$this->scanPath('lang');
		$this->scanPath('config');
		$this->scanPath('resources');
		$this->scanPath('public');
		$this->scanPath('routes');
		$this->scanPath('storage');

		$remoteVITE = $this->getRemoteProjectPath().$this->remote['separator'].'vite.config.js';
		$this->genViteCFG();

		$localVITE = $this->projectPath.DIRECTORY_SEPARATOR.'vite.config.js';
		$this->ForSend[]=[$localVITE,$remoteVITE];

		//$remoteBASHCRON = substr_replace($this->object->propertyById(64)->pivot->value,'',-1).'/cron.sh';
		//$localBASHCRON = $this->projectPath.DIRECTORY_SEPARATOR.'cron.sh';
		//$this->ForSend[]=[$localBASHCRON,$remoteBASHCRON];

		$remoteSQL = $this->getRemoteProjectPath().$this->remote['separator'].'basic.sql';
		$localSQL = $this->projectPath.DIRECTORY_SEPARATOR.'basic.sql';
		$this->ForSend[]=[$localSQL,$remoteSQL];
	}

	//Сканирует файлы в папке проект на локалке и добавляет в список на отправку
	public function scanPath($path)
	{
		$lastSymbolPath = substr($path,-1);

		if($lastSymbolPath=='/')
		{
			$path = substr($path,0,-1);
		}
		$pathForScan = $this->projectPath.DIRECTORY_SEPARATOR.$path;
		$objs = @scandir($pathForScan);
		foreach($objs as $obj)
		{
			if(($obj == '.') || ($obj == '..')) continue;
			//Если папка отправляем дальше сканировать
			if(is_dir($pathForScan.'/'.$obj))
			{
				$this->scanPath($path.DIRECTORY_SEPARATOR.$obj);
			}
			else
			{
				$remotePath = $this->getRemoteProjectPath();
				$newPatch = str_replace(DIRECTORY_SEPARATOR,$this->remote['separator'],$path);

				//Папки для создания
				if(!in_array($remotePath.$this->remote['separator'].$newPatch,$this->DirsForCreate))
				{
					$this->DirsForCreate[]=$remotePath.$this->remote['separator'].$newPatch;
				}

				//Добавить в массив для копирования
				if(!in_array($newPatch.'/'.$obj,$this->skipFiles))
				{
					$this->ForSend[]=[$this->projectPath.DIRECTORY_SEPARATOR.$path.DIRECTORY_SEPARATOR.$obj,$remotePath.$this->remote['separator'].$newPatch.$this->remote['separator'].$obj];
				}
			}
		}
	}

/*              Генерация БД и Моделей                     */

	//Добавляет свойство в общую таблицу свойств
	public function addProperty($id)
	{
		if(array_search($id,$this->sync->propertys)===false)
		{
			$this->sync->propertys[]= $id;
		};
	}

	//В SQL
	public function addObject($object,$prefix) 
	{
		//Вставляю объект
		$this->sql = $this->sql.'INSERT INTO `'.$prefix.'_objects` (`id`) VALUES ('.$object->id.');'."\n\n";
		//$this->sql = $this->sql.'SET @last_id = LAST_INSERT_ID();'."\n\n";
		//Заготовка для свойства
		$INTO = 'INSERT INTO `'.$prefix.'_propertys` (`object_id`, `property_id`, `value`,`params`) VALUES ';

		if($object->id==12)
		{
			//dd($object->id,$object->propertys);
		}

		$object->propertys->map(function($property,$key) use ($object,$INTO){

			//Проверяем свойство на блокировку
			if(!$property->pivot->block)
			{
				//Добавляем свойство в общий список
				$this->addProperty($property->id);

				//Добавляем параметр видимости
				$params = [
					'access'=>json_decode($property->pivot->access)
				];

				//Проверяем блокировку значения
				if($property->pivot->lock)
				{
					$this->logs[] = 'ЗАБЛОКИРОВАНА ВЫГРУЗКА ЗНАЧЕНИЯ! Значение '.$property->pivot->value.' для свойства '.$property->id.' ('.$property->name.') для объекта '.$object->id." (".($object->propertyById(1)->pivot->value).") извлечено";
					$this->sql = $this->sql.$INTO."($object->id,$property->id,null,'".json_encode($params,JSON_UNESCAPED_UNICODE)."');\n\n";
				}
				else
				{
					$this->logs[] = 'Значение '.$property->pivot->value.' для свойства '.$property->id.' ('.$property->name.') для объекта '.$object->id." (".($object->propertyById(1)->pivot->value).") извлечено";
					$this->sql = $this->sql.$INTO."($object->id,$property->id,'".addslashes($property->pivot->value)."','".json_encode($params,JSON_UNESCAPED_UNICODE)."');\n\n";
				}
			}
		});
	}

	//Подготовка файлов для копирования
	public function genFiles(){

		//Внедряем отношение
		Groups::resolveRelationUsing('params',function($orderModel){
			return $orderModel->belongsToMany(Params::class, 'groups_params', 'group_id','param_id')->withPivot('value');
		});
		Objects::resolveRelationUsing('params',function($orderModel){
			return $orderModel->belongsToMany(Params::class, 'objects_params','object_id','param_id')->withPivot('value');
		});

		//Все группы
		$this->sync->groups = Groups::all();
		//Объекты без групп
		$this->sync->objects = Objects::doesntHave("groups")->get();

		//Проверяем чтоб объект не был без группы
		if($this->sync->objects->count()>0)
		{
			dd('Замечен объект без группы',$this->sync->objects);
		}
		
		
		//Удаляем скрытые группы  -> pакрытие должно вернуться true, если элемент должен быть удален из результирующей коллекции
		$this->sync->groups = $this->sync->groups->reject(function($group){
			//Получаем параметр 1 проверяем на экспортируемость если параметр отсуствует или значение false - не экспортирует
			$param1 = $group->paramById(1)->pivot->value ?? null;
			if(!filter_var($param1,FILTER_VALIDATE_BOOLEAN))
			{
				$this->logs[] = "Группа ".$group->id." (".$group->name.") исключена";
				return true;
			}
			else
			{
				return false;
			}
			
		});


		/*Удаляем скрытые объекты  -> pакрытие должно вернуться true, если элемент должен быть удален из результирующей коллекции
		$this->sync->objects = $this->sync->objects->reject(function($object){
			//Получаем параметр 1 проверяем на экспортируемость если параметр отсуствует или значение false - не экспортирует
			$param1 = $group->paramById(1)->pivot->value ?? null;

			if($object->params()->where('params.id',1)->exists())
			{
				$this->logs[] = "Объект ".$object->id." (".($object->propertyById(1)->pivot->value ?? 'Без названия').") из группы ".$group->id." (".$group->name.") исключен";
				return true;
			}
			else
			{
				return false;
			}
		});*/
		

		//Очищаем папку для новых файлов
		File::deleteDirectory($this->projectPath);

		//Проходим по группам и создаем таблицы и группы и вставляем объекты по умолчанию
		$this->sync->groups->map(function($group){

			//Если есть переменная pathModel с моделью копируем ее иначе создаем заготовку
			$param10 = $group->params()->where('params.id',10)->first()->pivot->value ?? null;
			try
			{
				if(!is_null($param10))
				{
					if($this->isJson($param10))
					{
						$paths = json_decode($param10);
						//dd($paths);
						foreach($paths as $path)
						{
							//Переделываем путь для локальной ОС
							$pathForLocalOS = str_replace('/',DIRECTORY_SEPARATOR,$path);
							
							//Получаем данные по путям
							$pathFile = pathinfo(base_path($pathForLocalOS));
							$pathFile2 = pathinfo($this->projectPath.DIRECTORY_SEPARATOR.$pathForLocalOS);
							
							//Проверяем папку на существование
							if(!is_dir($pathFile2['dirname']))
							{
								mkdir($pathFile2['dirname'], 0755, true);
							}

							//Копируем файл и добавляем в лог
							$to = $this->projectPath.DIRECTORY_SEPARATOR.$pathForLocalOS;
							$this->all['copy'][]=[base_path($pathForLocalOS),$to];
							File::copy(base_path($pathForLocalOS),$to);
						}
					}
					else
					{
						//File::copy(base_path($path),$this->projectPath.'/'.$path);
					}
				}
				else
				{
					//Отправляем создавать модели
					$this->createModel($group);
				}
			}
			catch(\Exception $e)
			{
				dd("Проблема при копированиии $param10 в группе $group->id" );
			}


			//Если есть переменная pathSQL копируем SQL в общий иначе создаем заготовку
			$param11 = $group->params()->where('params.id',11)->first()->pivot->value ?? null;
			if(!is_null($param11))
			{
				$this->sql = $this->sql."\n$param11\n\n";
			}

			//Если группа и существует этот параметр, то для всех объектов создается отдельная таблица с этим префиксом
			if(!is_null($group->params()->where('params.id',2)->first()->pivot->value ?? null))
			{
				//Создаем SQL и добавляем объекты и свойства
				$this->createSQL($group);

				//Собираем список всех свойств используемые группой
				$group->propertys->map(function($property){
					//Если свойство не заблокировано
					if(!$property->pivot->block)
					{
						$this->addProperty($property->id);
					}
				});
			}
		});

		//Проходим по группам и собираем контроллеры и зависимости и копируем их
		$this->copyControllersAndDependencies();

		//Копируем основные файлы
		foreach($this->basicFoldersAndFiles as $obj)
		{
			$this->copyPathInFilesProject($obj);
		}

		//Создаем таблицу свойств
		$this->createPropertysTable();
		
		//Создаем файл с роутами
		$this->createFileRoute();

		//Создаем sql файл
		File::put($this->projectPath.'/basic.sql',$this->sql);

		//Файлы найстройки
		$this->createConfigs();

		//Генерация BASH скрипта для CRON
		//$this->genBashForCron();

		//$this->genAuthServiceProvider();
		//$this->genKernel();


		//Копирует в основную папку проекта на локалке, env файл
		$this->projectPath = storage_path('projects'.DIRECTORY_SEPARATOR.$this->object->id);
		File::put($this->projectPath.DIRECTORY_SEPARATOR.'.env',file_get_contents(storage_path('tmp'.DIRECTORY_SEPARATOR.$this->object->id).'.env'));
		
		//VITE
		//Копирует в основную папку проекта на локалке, VITE.CONFIG.JS
		File::put($this->projectPath.DIRECTORY_SEPARATOR.'vite.config.js',file_get_contents(base_path('vite.config.js')));

		//SQL
		//Копирует в основную папку проекта на локалке, VITE.CONFIG.JS
		//File::put($this->projectPath.DIRECTORY_SEPARATOR.'basic',file_get_contents(base_path('vite.config.js')));

	}

	//Создаем таблицу свойств
	public function createPropertysTable()
    {
		//Для локальной разработки создаем удаление
		//Создаем таблицу
		$this->sql = $this->sql.'CREATE TABLE `propertys` (`id` bigint UNSIGNED NOT NULL, `name` text COLLATE utf8mb4_unicode_ci NOT NULL,`desc` text COLLATE utf8mb4_unicode_ci NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'."\n";
		$this->sql = $this->sql.'INSERT INTO `propertys` (`id`, `name`, `desc`) VALUES'."\n";
		$countPropertys = count($this->sync->propertys);
		foreach($this->sync->propertys as $key =>$pid)
		{
			$property = Propertys::find($pid);
			$this->sql =$this->sql."($property->id,'".addslashes($property->name)."','".addslashes($property->desc)."')";
			if($key==$countPropertys-1)
				{$this->sql = $this->sql.";\n\n";}
			else
				{$this->sql = $this->sql.",\n";}
		}
		$this->sql = $this->sql.'ALTER TABLE `propertys` ADD PRIMARY KEY (`id`);'."\n";
	}

	//Создает SQL файл для группы
	//ПОЛУЧАЕТ ПРЕФИКС
	//ПОДГОТАВЛАЕТ ЗАПРОСЫ СОЗДАНИЯ ТАБЛИЦ
	//Добавляем свойства с описанием для групп
	//ПАРАМЕТР 8 ЭКСПОРТИРОВАНИЕ ОБЪЕКТОВ ЕСЛИ ОН ДОБАВЛЕН
	public function createSQL($group)
	{
		//Получаем префикс группы, если его нет нечего не делаем
		$prefix = $group->params()->where('params.id',2)->first()->pivot->value ?? null;
		if(!is_null($prefix))
		{
			if(strpos($prefix,'_'))
			{
				//dd($prefix);
				$explodedPrefix = explode('_',$prefix);
				foreach($explodedPrefix as $keyP=>$valueP)
				{
					$explodedPrefix[$keyP] = ucfirst($valueP);
				}
				$forName = implode($explodedPrefix);
			}
			else
			{
				$forName=ucfirst($prefix);
			}
		}
		else
		{
			$this->logs[] = 'Ошибка при получении параметра 2 ('.$this->params->where('id',2)->first()->desc.') для группы '.$group->id."($group->name)";
		}


		//Для локальной разработки создаем удаление
		$this->sql = $this->sql.'DROP TABLE IF EXISTS '.$prefix.'_objects;'."\n";
		$this->sql = $this->sql.'DROP TABLE IF EXISTS '.$prefix.'_propertys;'."\n";
		$this->sql = $this->sql.'DROP TABLE IF EXISTS '.$prefix.'_fields;'."\n";

		//Проверяем параметр 7
		$disableCreateDefaultTables = $group->params()->where('params.id',7)->first()->pivot->value ?? null;

		if(!is_null($disableCreateDefaultTables))
		{
			return;
		}

		//Создать таблицу объектов
		$this->sql = $this->sql.'CREATE TABLE `'.$prefix.'_objects` (`id` int UNSIGNED NOT NULL,`params` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, `created_at` timestamp NULL DEFAULT NULL, `updated_at` timestamp NULL DEFAULT NULL, `deleted_at` timestamp NULL DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'."\n";

				//Индексы таблицы
		$this->sql = $this->sql.'ALTER TABLE `'.$prefix.'_objects` ADD PRIMARY KEY (`id`);'."\n";
		
				//Автоинкрименты
		$this->sql = $this->sql.'ALTER TABLE `'.$prefix.'_objects` MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;'."\n";

		$this->sql = $this->sql."\n";




				//Cоздание таблицы свойств для объектов
		$this->sql = $this->sql.'CREATE TABLE `'.$prefix.'_propertys` ( `id` bigint UNSIGNED NOT NULL, `object_id` bigint UNSIGNED NOT NULL, `property_id` bigint UNSIGNED NOT NULL, `value` text NULL DEFAULT NULL COLLATE utf8mb4_unicode_ci, `params` text NULL DEFAULT NULL COLLATE utf8mb4_unicode_ci) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'."\n";

		$this->sql = $this->sql.'ALTER TABLE `'.$prefix.'_propertys` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `object_id` (`object_id`,`property_id`), ADD KEY `property_id` (`property_id`);'."\n";

		$this->sql = $this->sql.'ALTER TABLE `'.$prefix.'_propertys` MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;'."\n";

		$this->sql = $this->sql."\n";



				//Cоздание таблицы свойств для объектов с описание
		$this->sql = $this->sql.'CREATE TABLE `'.$prefix.'_fields` ( `id` bigint UNSIGNED NOT NULL, `property_id` bigint UNSIGNED NOT NULL, `params` text COLLATE utf8mb4_unicode_ci) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'."\n";

		$this->sql = $this->sql.'ALTER TABLE `'.$prefix.'_fields` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `id` (`id`), ADD KEY `property_id` (`property_id`);'."\n";

		$this->sql = $this->sql.'ALTER TABLE `'.$prefix.'_fields` MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;'."\n";

		$this->sql = $this->sql."\n";

		if($group->id==7)
		{
		//	dd($group->propertys);
		};

		//Добавляем свойства с описанием для групп
		foreach($group->propertys as $property)
		{
			$params = [
				'desc'=>(is_null($property->pivot->desc)? $property->desc : $property->pivot->desc),
				'require'=>(is_null($property->pivot->require)? 0 : $property->pivot->require),
				'access'=>json_decode($property->pivot->access)
			];

			if($group->id==188)
			{
				dd(json_decode($property->pivot->access));
				dd(
					'Группа: '.$group->id,
					'Свойство: '.$property->id,
					'Params: ',$params,
					'Свойства доступа: ',$property->pivot->access
				);
			}
			$this->sql = $this->sql."INSERT INTO `".$prefix."_fields` (`id`,`property_id`,`params`) VALUES (null,".$property->id.",'".json_encode($params,JSON_UNESCAPED_UNICODE)."')".";\n\n";
		}

		//Получаем объекты группы
		$objects = $group->objects()->get();

		//Проверяем количество объектов
		if($objects->count()>0)
		{
			//Отсеиваем не экспортируемые
			$objects = $objects->reject(function($object){
				//Получаем параметр 1 проверяем на экспортируемость если параметр отсуствует или значение false - не экспортирует
				$param1 = $object->paramById(1)->pivot->value ?? null;
				if(!filter_var($param1,FILTER_VALIDATE_BOOLEAN))
				{
					$this->logs[] = "Объект ".$object->id." (".$object->name.") исключен";
					return true;
				}
				else
				{
					return false;
				}
			});
		}

		//Проверяем количество объекто псоле отсеивания, если есть, то экспортируем
		if($objects->count()>0)
		{
			//dd($objects);
			$objects->map(function($object,$key) use ($prefix){
				$this->addObject($object,$prefix);
			});
		}

		/*Проверяем на наличие объектов для экспортирования - параметр 8
		//Если параметр 8 есть и он JSON то экспортирует из массива
		//Иначе экспортирует все объекты
		//$objectsExport = $group->params()->where('params.id',8)->first() ?? null;
		//Параметр есть точно экспортируем из группы
		if(!is_null($objectsExport))
		{
			$this->logs[] = 'Объекты для группы '.$group->id."($group->name) будут добавлены";
			//Проверяем на JSON
			if($this->isJson($objectsExport->pivot->value))
			{
				$objects = json_decode($objectsExport->pivot->value);
				//Если массив то экспортирует из массива
				if(is_array($objects))
				{
					$countObjects = count($objects);
					foreach($objects as $key =>$obj)
					{
						$object = Objects::find($obj);
						$this->addObject($object,$prefix);
					}
				}
				//Пустое значение или не массив
				else
				{
					$this->logs[] = 'Значение параметра 8 ('.$this->params->where('id',8)->first()->name.') для группы '.$group->id." ($group->name) не массив! Будут извлечены все объекты! Код 101";
					$group->objects->map(function($object,$key) use ($prefix){
						$this->addObject($object,$prefix);
					});
				}
			}
			//Добавляем все объекты так как параметр 8 есть, и он не JSON
			else
			{
				//Добавляем все объекты так как проверка на JSON не прошла
				$group->objects->map(function($object,$key) use ($prefix){
					$this->addObject($object,$prefix);
				});
			}
		}*/
	}

	//Создает модель 
	//ПАРАМЕТР 2 - ПРЕФИКС
	//ПАРАМЕТР 14 - ПАПКА ДЛЯ РАЗМЕЩЕНИЯ МОДЕЛИ 
	public function createModel($group)
	{
		//Получаем префикс группы
		try
		{
			$prefix = $group->params()->where('params.id',2)->first()->pivot->value;
			if(strpos($prefix,'_'))
			{
				//dd($prefix);
				$explodedPrefix = explode('_',$prefix);
				foreach($explodedPrefix as $keyP=>$valueP)
				{
					$explodedPrefix[$keyP] = ucfirst($valueP);
				}
				$forName = implode($explodedPrefix);
			}
			else
			{
				$forName=ucfirst($prefix);
			}
		}
		catch(\Exception $e)
		{
			dd('Ошибка при получении параметра 2 для группы '.$group->id."($group->name)");
		}

		//Доделать копировать в папку! Нет параметра в БД
		$folderToCopy = $group->params()->where('params.id',12)->first()->pivot->value ?? null;

		//Получаем NameSpace
		$namespace = 
			$group->params()->where('params.id',5)->first()->pivot->value
			??
			'App\Models\\'.(is_null($folderToCopy)?$forName:$folderToCopy);

$modelText ='<?php

namespace '.$namespace.';

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Propertys;

use Illuminate\Support\Facades\DB;

class '.$forName.'Model extends Model
{
	protected $table = "'.$prefix.'_objects";

	protected $primaryKey = "id";

	protected $casts = [
		"params" => "array",
	];

	protected function asJson($value)
	{
		return json_encode($value,JSON_UNESCAPED_UNICODE);
	}
	public function propertys()
	{
		return $this->belongsToMany(Propertys::class,"'.$prefix.'_propertys","object_id","property_id")->withPivot("value","params");
	}

	public function propertyById($pid)
	{
		return $this->propertys()->where("property_id",$pid)->where("object_id",$this->id)->first();
	}
	public function fields()
	{
		return DB::table("'.$prefix.'_fields")->get();
	}
}';

		//Проверяем есть ли в парраметрах папка для размещения - Параметр 14
		$folderToCopy = $group->params()->where('params.id',12)->first()->pivot->value ?? null;
		if(is_null($folderToCopy))
		{
			//Создаем каталог модели и модель
			$projectModelFolder = $this->projectPath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR.$forName;
			@mkdir($projectModelFolder,0755,true);
			File::put($projectModelFolder.DIRECTORY_SEPARATOR.$forName.'Model.php',$modelText);
		}
		else
		{
			$projectModelFolder = $this->projectPath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR.$folderToCopy;
			@mkdir($projectModelFolder,0755,true);
			File::put($projectModelFolder.DIRECTORY_SEPARATOR.$forName.'Model.php',$modelText);
		}

	}


/*              ГЕНЕРАЦИЯ ОТДЕЛЬНЫХ ФАЙЛОВ                     */



	//Функция поиска файлов в папке
	public function checkFileInDirectory($textInFilename)
	{
		//Ищем файлы для проверки
		//dd($this->migrationsPath);
		$files = @scandir($this->migrationsPath);
		$finded = false;
		//dd($files);
		foreach($files as $file)
		{
			if(($file == '.') || ($file == '..')) continue;
			
			if(strpos($file,$textInFilename)!==false)
			{
				$finded = true;
				//return false;
			}

		}
		return $finded;
	}

	//Папка копирует контроллер, и все зависимости не представленные\
	//ПАРАМЕТР 4
	//ПАРАМЕТР 6
	public function copyControllersAndDependencies()
	{
		//Копируем контролеры виды и т д
		$this->sync->groups->map(function($group){
			//Папка контроллеров параметр 4
			$pathController = $group->params()->where('params.id',4)->first()->pivot->value ?? null;
			if(!is_null($pathController))
			{
				try
				{
					$paths = json_decode($pathController);
				}
				catch(\Exception $e)
				{
					dd('Параметр 4 не json - '.$pathController. ' для группы '.$group->id);
				}

				try
				{

					foreach($paths as $path)
					{
						if(DIRECTORY_SEPARATOR!='/')
						{
							//dd('replace');
							$path = str_replace("/",DIRECTORY_SEPARATOR,$path);
						}
						//Проверяем файл на существование
						if(is_file(base_path($path)))
						{
							//Копирует контроллер и все зависимости
							try
							{
								$this->getControllerDependences($path);
							}
							catch(\Exception $e)
							{
								dd($path);
								dd('Проблема в getControllerDependences для folderVIews для группы '.$group->id);
							}
						}
						else
						{
							dd('Отсутствует файл контролера '.$path. ' для группы '.$group->id);
						}
					}
				}
				catch(\Exception $e)
				{
					dd('Параметр 4  проблема в цикле для группы '.$group->id);
				}
			}
			else
			{
				$this->logs[] = "Для группы ".$group->id." (".$group->name.") отсуствует параметр 4 (".$this->params->where('id',4)->first()->desc.")";
			}

			//Папка видов - параметр 6
			$folderViews = $group->params()->where('params.id',6)->first()->pivot->value ?? null;
			if(!is_null($folderViews))
			{
				try
				{
					$paths = json_decode($folderViews);
					foreach($paths as $path)
					{
						try
						{
							$this->copyPathInFilesProject($path);
						}
						catch(\Exception $e)
						{
							dd('Проблема в copyPathInFilesProject для folderVIews для '.$group->id);
						}
					}
				}
				catch(\Exception $e)
				{
					dd('Параметр 6 не json - '.$pathController. ' для группы '.$group->id);
					
				}
			}
			else
			{
				$this->logs[] = "Для группы ".$group->id." (".$group->name.") отсуствует параметр 6 (".$this->params->where('id',6)->first()->desc.")";
			}
        });
	}

	//Копирует папку в папку с проектами
	public function copyPathInFilesProject($path)
	{
		//Локальный путь
		$pathLocal = str_replace('/',DIRECTORY_SEPARATOR,$path);

		//Получаем последний символ
		$lastSymbolPath = substr($pathLocal,-1);

		if($lastSymbolPath=='/' or $lastSymbolPath=='\\')
		{
			$pathLocal = substr($pathLocal,0,-1);
		}
		//Отсеиваем пропускающиеся
		foreach($this->skipFiles as $skipPath)
		{
			//Сверяет пути
			if(str_contains($path,$skipPath))
			{
				return;
			}
			//dd($path,$skipPath);
		}

		//Путь для сканирования
		$pathForScan = base_path($pathLocal);

		//Если директория отправляем заново на скан
		if(is_dir($pathForScan))
		{
			$objs = @scandir($pathForScan);
			//Сканируем внутри папки файлы
			foreach($objs as $obj)
			{
				if(($obj == '.') || ($obj == '..')) continue;

				$newPath = $pathLocal.DIRECTORY_SEPARATOR.$obj;
				//Если папка скинируем заново
				if(is_dir(base_path($newPath)))
				{
					$this->copyPathInFilesProject($newPath);
				}
				//Если файл
				else if(is_file(base_path($newPath)))
				{
					try
					{
						$exploded=explode(DIRECTORY_SEPARATOR,$newPath);
						unset($exploded[array_key_last($exploded)]);

						//Для проверки на существрвание папки
						$forCheck = $this->projectPath.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR,$exploded);
						//dd($forCheck);

						//dd(!file_exists(($forCheck));
						if(!file_exists($forCheck))
						{
							mkdir($forCheck,0755,true);
						}
						//Проверяем файлы на исключение
						if(!in_array(str_replace(DIRECTORY_SEPARATOR,'/',$newPath),$this->skipFiles))
						{
							//Откуда
							$from = base_path($newPath);
							//Куда
							$to = $this->projectPath.DIRECTORY_SEPARATOR.$newPath;
							//dd([$from,$to]);
							//$this->$all['copy'][] = [$from,$to];
							File::copy(base_path($newPath),$this->projectPath.DIRECTORY_SEPARATOR.$newPath);
						}

					}
					catch(\Exception $e)
					{
						dd($newPath);
						//dd($this->projectPath.'/'.implode('/',$exploded),0755,true);
						//dd($newPath);
					}
				}
				else
				{
					dd('error');
				}
			}
		}
		else
		{
			$exploded=explode(DIRECTORY_SEPARATOR,$pathLocal);
			unset($exploded[array_key_last($exploded)]);
			//Если не файл то создаем папку
			if(!file_exists($this->projectPath.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR,$exploded)))
			{
				//dd($this->projectPath.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR,$exploded),0755,true));
				@mkdir($this->projectPath.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR,$exploded),0755,true);
			}
			try
			{
				//dd(base_path($pathLocal));
				File::copy(base_path($pathLocal),$this->projectPath.DIRECTORY_SEPARATOR.$pathLocal);
			}
			catch(\Exception $e2)
			{
				//dd("Возможно отсутствует папка или файл ".$path);
				//dd($path);
				dd($this->projectPath.DIRECTORY_SEPARATOR.$path);
				dd(base_path($path));
				dd($e2);
			}
		}
	}

	//Cоздает файл для роутов
	public function createFileRoute()
	{
$route = "<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

require __DIR__.'/project.php';
require __DIR__.'/management.php';
";

		File::put($this->projectPath.'/routes/web.php',$route);

	}

	//Содает файлы для config
	public function createConfigs()
	{

		$auth = "
		<?php

		return [

			'defaults' => [
				'guard' => 'myidon',
			],

			'guards' => [
				'web' => [
					'driver' => 'session',
					'provider' => 'users',
				],
				'myidon' => [
					'driver' => 'myidon',
					'provider' => 'users', //Поставщик
				]
			],

			'providers' => [
				'users' => [
					'driver' => 'myidon'
				]
			]
		];
		";

		$app = "
		<?php

		use Illuminate\Support\Facades\Facade;

		return [

			'name' => env('APP_NAME', 'Laravel'),

			'env' => env('APP_ENV', 'production'),

			'debug' => (bool) env('APP_DEBUG', false),

			'url' => env('APP_URL', 'http://localhost'),

			'asset_url' => env('ASSET_URL'),

			'timezone' => '".$this->object->propertyById(93)->pivot->value."',

			'locale' => '".$this->object->propertyById(92)->pivot->value."',
			//Когда нет перевода выводит этот
			'fallback_locale' => 'en',

			'faker_locale' => 'en_US',

			'key' => env('APP_KEY'),

			'cipher' => 'AES-256-CBC',

			'maintenance' => [
				'driver' => 'file',
				// 'store'  => 'redis',
			],

			'providers' => [

				Illuminate\Auth\AuthServiceProvider::class,
				Illuminate\Broadcasting\BroadcastServiceProvider::class,
				Illuminate\Bus\BusServiceProvider::class,
				Illuminate\Cache\CacheServiceProvider::class,
				Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
				Illuminate\Cookie\CookieServiceProvider::class,
				Illuminate\Database\DatabaseServiceProvider::class,
				Illuminate\Encryption\EncryptionServiceProvider::class,
				Illuminate\Filesystem\FilesystemServiceProvider::class,
				Illuminate\Foundation\Providers\FoundationServiceProvider::class,
				Illuminate\Hashing\HashServiceProvider::class,
				Illuminate\Mail\MailServiceProvider::class,
				Illuminate\Notifications\NotificationServiceProvider::class,
				Illuminate\Pagination\PaginationServiceProvider::class,
				Illuminate\Pipeline\PipelineServiceProvider::class,
				Illuminate\Queue\QueueServiceProvider::class,
				Illuminate\Redis\RedisServiceProvider::class,
				Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
				Illuminate\Session\SessionServiceProvider::class,
				Illuminate\Translation\TranslationServiceProvider::class,
				Illuminate\Validation\ValidationServiceProvider::class,
				Illuminate\View\ViewServiceProvider::class,

				/*
				* Package Service Providers...
				*/

				/*
				* Application Service Providers...
				*/
				App\Providers\AppServiceProvider::class,
				App\Providers\AuthServiceProvider::class,
				// App\Providers\BroadcastServiceProvider::class,
				App\Providers\EventServiceProvider::class,
				App\Providers\RouteServiceProvider::class,

			],

			'aliases' => Facade::defaultAliases()->merge([
				// 'ExampleClass' => App\Example\ExampleClass::class,
			])->toArray(),

		];

		";
		@mkdir($this->projectPath.'/config');
		File::put($this->projectPath.'/config/auth.php',$auth);
		//File::put($this->projectPath.'/config/app.php',$app);

	}

	//Гененрирует SH файл для cron на будущее
	public function genBashForCron()
	{
		$textSH = '
#!/bin/env bash

# Debug code to start on minute boundary and to
# gradually increase maximum payload duration to
# see what happens when the payload exceeds 30 seconds.

((maxtime = 10))
while [[ "$(date +%S)" != "00" ]]; do true; done

while true; do
    # Start a background timer BEFORE the payload runs.

    sleep 20 &

    # Execute the payload, some random duration up to the limit.
    # Extra blank line if excess payload.

    ((delay = RANDOM % maxtime + 1))
    ((maxtime += 1))
    echo "$(date) Sleeping for ${delay} seconds (max ${maxtime})."
    [[ ${delay} -gt 30 ]] && echo
    sleep ${delay}

    # Wait for timer to finish before next cycle.

    wait
done
';

		//File::put($this->projectPath.DIRECTORY_SEPARATOR.'cron.sh',$textSH);
	}

	//Гененрирует SH файл для cron на будущее
	public function genKernel()
	{
		$kernel = '<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
		$schedule->command(\'command:TGgetUpdates\')->cron(\'* * * * *\')->withoutOverlapping();
		$schedule->command(\'command:VKgetUpdates\')->cron(\'* * * * *\')->withoutOverlapping();
		$schedule->command(\'command:MessageProcessing\')->cron(\'* * * * *\')->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.\'/Commands\');

        require base_path(\'routes/console.php\');
    }
}
';

		//File::put($this->projectPath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Console'.DIRECTORY_SEPARATOR.'Kernel.php',$kernel);
	}

	//Генерирует провайдер для установки
	public function genAuthServiceProvider()
	{

$text = '<?php
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

use Illuminate\Support\Facades\Auth;
use App\Services\Auth\UserProvider;
use App\Services\Auth\MyidonGuard;

class AuthServiceProvider extends ServiceProvider
{
	protected $policies = [];

	public function boot(): void
	{
		$this->registerPolicies();
		//Добавляем провайдвера - поставщика пользователей
		Auth::provider(\'myidon\', function ($app, array $config) {
			// Return an instance of Illuminate\Contracts\Auth\UserProvider...
			return new UserProvider();
		});
		Auth::extend(\'myidon\', function ($app, $name, array $config) {
			return new MyidonGuard(Auth::createUserProvider($config[\'provider\']), $app->make(\'request\'));
		});
	}
}
';

		$path = 
		//Создаем каталог модели и модель
		$authSPFILE = $this->projectPath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Providers';
		mkdir($authSPFILE,0755,true);
		File::put($authSPFILE.DIRECTORY_SEPARATOR.'AuthServiceProvider.php',$text);
		$authServiceFolder = $this->projectPath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'Auth';
		mkdir($authServiceFolder,0755,true);
		File::copy(base_path('app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'Auth'.DIRECTORY_SEPARATOR.'MyidonGuard.php'),$this->projectPath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'Auth'.DIRECTORY_SEPARATOR.'MyidonGuard.php');
		File::copy(base_path('app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'Auth'.DIRECTORY_SEPARATOR.'UserProvider.php'),$this->projectPath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'Auth'.DIRECTORY_SEPARATOR.'UserProvider.php');
	}

	//Получает все зависимости контроллера
	public function getControllerDependences($pathController)
	{
		//Сюда прописывается патч при котром происходит ошибка
		$debugPath = 'app\Http\Controllers\Management\Settings\SettingsController.php';
		//dd($this->skipFiles);




		//Проверяем на массив файлов которые нужно пропускать
        if(in_array($pathController,$this->skipFiles))
        {
            return;
        }




		//Проверяем наличие контроллера
		if(!file_exists(base_path($pathController)))
		{
			dd("Файл $pathController не существует");
		}




		if(!is_file(base_path($pathController)))
		{
			dd("Файл $pathController не существует 2");
		}



        try
        {
            //Копируем контроллер
            //Разбиваем путь до контроллера
            $explodedPathClass=explode(DIRECTORY_SEPARATOR,$pathController);
			//if($pathController==$debugPath){dd($explodedPathClass);}
            //Последний элемент - это имя файла
            $className = $explodedPathClass[array_key_last($explodedPathClass)];
            //Удаляем название файла
            unset($explodedPathClass[array_key_last($explodedPathClass)]);
            //Создаем каталоги до контроллера
            $pathFolderForClass = implode(DIRECTORY_SEPARATOR,$explodedPathClass);
			//if($pathController==$debugPath){dd($pathFolderForClass);}
            //dd($pathFolderForClass);
			//dd($this->projectPath.DIRECTORY_SEPARATOR.$pathFolderForClass);
			try
			{
				@mkdir($this->projectPath.DIRECTORY_SEPARATOR.$pathFolderForClass,0755,true);
            }
			catch(\Exception $e)
			{
				dd('При создании папки '.$this->projectPath.DIRECTORY_SEPARATOR.$pathFolderForClass);
			}
			//Копируем контроллер
			//if($pathController=='app/Http/Controllers/Management/Organizations/OrganizationsController.php'){dd($this->projectPath.'/'.$pathFolderForClass.'/'.$className);}
            try{
				/*if($pathController==$debugPath)
				{
					$string = 'Отсюда: '.base_path($pathFolderForClass.DIRECTORY_SEPARATOR.$className);
					$string = $string.' - ';
					$string = $string.'Cюда: '.$this->projectPath.DIRECTORY_SEPARATOR.$pathFolderForClass.DIRECTORY_SEPARATOR.$className;
					dd($string);
				}*/
				//dd(is_file(base_path($pathFolderForClass.DIRECTORY_SEPARATOR.$className)));
				//---
				//dd($this->projectPath);
				//dd($this->projectPath.$pathFolderForClass.DIRECTORY_SEPARATOR.$className);
				//dd(is_file($this->projectPath.$pathFolderForClass.DIRECTORY_SEPARATOR.$className));
				File::copy(base_path($pathFolderForClass.DIRECTORY_SEPARATOR.$className),$this->projectPath.DIRECTORY_SEPARATOR.$pathFolderForClass.DIRECTORY_SEPARATOR.$className);
			}
			catch(\Exception $e)
			{
				dd('Проблема при копировании контроллера '.$pathController);
			}
        }
        catch(\Exception $e)
        {
            dd('Проблема перед копированием копировании контроллера '.$pathController);
        }




		//if($pathController==$debugPath){dd('Перешел к считыванию класса');}




		//Находим зависимости
		//Считываем строки класса
		//if($pathController==$debugPath){dd($this->projectPath.DIRECTORY_SEPARATOR.$pathFolderForClass.DIRECTORY_SEPARATOR.$className);}
		$lines = file($this->projectPath.DIRECTORY_SEPARATOR.$pathFolderForClass.DIRECTORY_SEPARATOR.$className, FILE_IGNORE_NEW_LINES);
		//if($pathController==$debugPath){dd($lines);}



		//Перменная которая хранит namespace, массив use
		$controller = new stdClass;
		//Создаем основные переменные
		//if($pathController==$debugPath){dd($lines);}
		foreach($lines as $keyLine=>$line)
		{
			//Если строка пустая то пропускаем
			if(empty($line))
			{
				continue;
			}
			$lineExploded = explode(' ',$line);
			if($lineExploded[0]=='namespace')
			{
				$controller->namespace = str_replace(";","",$lineExploded[1]);
			}
			elseif($lineExploded[0]=='use')
			{
				if(!isset($controller->use)){$controller->use=[];}
				$controller->use[] = str_replace(";","",$lineExploded[1]);
			}
			if($lineExploded[0]=='class')
			{
				break;
			}
		}



		//if($pathController==$debugPath){dd($controller->use);}



		//Если отсутсвуют зависимости пропускаем
		if(isset($controller->use))
		{
			//dd($controller->use);
			//Перебираем используемые use
			foreach($controller->use as $keyUse=>$useClass)
			{
				if($useClass=='App\Http\Controllers\Controller')
				{
					continue;
				}
				//Разбиваем путь для проверки
				//dd($useClass);
				$explodedUseClass=explode('\\',$useClass);
				//if($pathController==$debugPath){dd($explodedUseClass);}
				//Если не App, то это пропускаем
				if($explodedUseClass[0]==='App')
				{
					//Пропускаем модели
					if($explodedUseClass[1]==='Models')
					{
						continue;
					}
					//Последний элемент - это имя файла
					$className = $explodedUseClass[array_key_last($explodedUseClass)].'.php';
					//Удаляем его
					unset($explodedUseClass[array_key_last($explodedUseClass)]);
					//dd();
					//Создаем каталоги до класса
					$explodedUseClass[0] = 'app';
					$pathFolderForClass = implode(DIRECTORY_SEPARATOR,$explodedUseClass);
					
					@mkdir($this->projectPath.DIRECTORY_SEPARATOR.$pathFolderForClass,0755,true);

					//Копируем файл класса
					$pathForClass = $pathFolderForClass.DIRECTORY_SEPARATOR.$className;
					//Копируем контроллер
					try
					{
						File::copy(base_path($pathForClass),$this->projectPath.DIRECTORY_SEPARATOR.$pathForClass);
					}
					catch(\Exception $e)
					{
						dd('Проблема при копировании зависимости контроллера '.$pathController. ' зависимость '.$useClass);
					}
				}
			}
		}
	}

	//Проверка на json
	public function isJson($string)
	{
		json_decode($string);
		return json_last_error() === JSON_ERROR_NONE;
	}

	//Генерация vite конфига
	public function genViteCFG()
	{
$cfg = "
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import sass from 'sass';
import fs from 'fs';

export default defineConfig({
	plugins: [
		laravel({
			input: [
				'resources/sass/app.scss',
				'resources/js/app.js',
				'resources/js/bootstrap.js',
				'resources/js/sidebar.js',
				'resources/js/posts.js',
				'resources/js/assistant.js',
				'resources/js/shop.js',
				'resources/js/intervals.js',
				'resources/js/events.js',
				'resources/js/files.js',
				'resources/js/csrf.js',
				'resources/js/preloader.js',
				'resources/js/workers.js',
				'resources/js/worker.cache.js',
				'resources/js/worker.push.js',
				'resources/js/worker.socket.js'
			],
			refresh: true,
		})
	]
});";

	$pathCFG = $this->projectPath.DIRECTORY_SEPARATOR.'vite.config.js';
	File::put($pathCFG,$cfg);
	}

	//Получить удаленный базовый путь без всякой хуйни в конце
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
}