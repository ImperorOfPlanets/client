<?php

namespace App\Http\Controllers\Control\Sync;

use App\Http\Controllers\Controller;

use DB;
use ZipArchive;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

use App\Helpers\Control\Ssh;

use App\Models\Core\Objects;
use App\Models\Core\Groups;
use App\Models\Job;
use App\Models\JobFailed;

use Jfcherng\Diff\Differ;
use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Factory\RendererFactory;
use Jfcherng\Diff\Renderer\RendererConstant;

use App\Jobs\ForControlProjects\downloadFiles;
use App\Jobs\ForControlProjects\sendFiles;
use App\Jobs\ForControlProjects\runCommandOnProject;

class SyncFilesController extends Controller
{
	//Объект проекта
	public $object;

	//Объект синхронизатора
	public $sync;

	//Команды из объекта
	public $commands = [
		'addInSkipForExport'=>[66,67],
		'addInSkipForExportNotPublish'=>[70,71],
		'addInDeleteOnProject'=>[72,73],
		'addInRequire'=>[68,69]
	];
	public $propertys;

	//Для SSH
	public $ssh;
	//Время используется при создании файлов и т д
	public $time;

	//Массив файлов синхронизатора
	public $syncFiles = [];
	//Массив наших файлов
	public $myFiles = [];
	//Массив файлов проекта
	public $remoteFiles = [];

    public function __construct()
    {
		//Дергаем синхронизатор
        $this->sync = Objects::find(1);

		//Получаем свойства команд
		$this->getPropertys();
		$this->time = time();
		$this->object = Objects::find(request()->route()->parameter(array_key_first(request()->route()->parameters())));
    }

	public function index(Request $request)
	{
		//Получаем файлы синхронизации проекта
		$this->getFilesSync($this->object->id);

		//Удаляем просроченные и старые
		$this->deleteLastExpired();

		//Проверяем результат перед выдачей
		$this->checkBeforeReturn();

		//Считываем результаты
		$result = $this->getResult();

		//Получаем свои файлы
		$this->getMyFiles();
		View::share('sync',$this);
		return view('control.sync.files.index');
	}

	public function store(Request $request)
	{
		if(isset($request->command))
		{
			if($request->command=='blockFile')
			{
				$filename = $this->object->id.'_R_'.$request->time.'.csv';
				$filenameNEW = $this->object->id.'_B_'.$request->time.'.csv';
				if(file_exists(storage_path('app/csvfiles/'.$filename)))
				{
					copy(storage_path('app/csvfiles/'.$filename),storage_path('app/csvfiles/'.$filenameNEW));
				}
				else
				{
					dd('Файл не найден');
				}
			}

			if($request->command=='unblockFile')
			{
				$filename = $this->object->id.'_B_'.$request->time.'.csv';
				unlink(storage_path('app/csvfiles/'.$filename));
				return redirect(request()->headers->get('referer'));
			}

			if($request->command=='createZIP')
			{
				$array = explode(',',$request->zip);
				$file= storage_path('app/csvfiles/'.$this->object->id.'_Z_'.$request->time.'.zip');
				$zip = new \ZipArchive;
				echo "zip started.\n";
				if ($zip->open($file, ZipArchive::CREATE) === TRUE)
				{
					foreach($array as $path)
					{
						$zip->addFromString($path, file_get_contents(base_path($path)));
					}
					$zip->close();
					return response()->json(['type'=>'alert','text'=>'Архив создан'],200,[],JSON_UNESCAPED_UNICODE);
				}
				else
				{
					return response()->json(['type'=>'alert','text'=>'Провал'],200,[],JSON_UNESCAPED_UNICODE);
				}
			}

			if($request->command=='sendZIP')
			{
				$this->ssh = new Ssh(['project_id'=>$this->object->id]);
				$myPath = storage_path('/app/csvfiles/'.$this->syncFiles['Z'][max(array_keys($this->syncFiles['Z']))]);
				$remotePath = $this->object->propertyById(64)->pivot->value.'storage/app/csvfiles/'.$this->syncFiles['Z'][max(array_keys($this->syncFiles['Z']))];
				//dd($remotePath);
				try{
					$this->ssh->sendFile($myPath,$remotePath);
					return response()->json(['type'=>'alert','text'=>'Архив отправлен'],200,[],JSON_UNESCAPED_UNICODE);
				}
				catch(\Exception $e)
				{
					return response()->json(['type'=>'alert','text'=>'Сбой при отправке архива'],200,[],JSON_UNESCAPED_UNICODE);
				}
			}

			if($request->command=='compare')
			{
				$tmpFilename = \Str::random(16);
				$pathMyInTMP = storage_path('tmp/'.$tmpFilename);

				$pathRemote = $this->object->propertyById(64)->pivot->value.$request->path;

				$this->ssh = new Ssh(['project_id'=>$this->object->id]);
				$this->ssh->downloadFile($pathMyInTMP,$pathRemote);

				// renderer class name:
				//     Text renderers: Context, JsonText, Unified
				//     HTML renderers: Combined, Inline, JsonHtml, SideBySide
				$rendererName = 'SideBySide';

				// the Diff class options
				$differOptions = [
					// show how many neighbor lines
					// Differ::CONTEXT_ALL can be used to show the whole file
					'context' => 3,
					// ignore case difference
					'ignoreCase' => false,
					// ignore whitespace difference
					'ignoreWhitespace' => false,
				];
				//$rendererOptions = ['detailLevel' => 'line'];
				$rendererOptions = [
					// how detailed the rendered HTML in-line diff is? (none, line, word, char)
					'detailLevel' => 'line',
					// renderer language: eng, cht, chs, jpn, ...
					// or an array which has the same keys with a language file
					// check the "Custom Language" section in the readme for more advanced usage
					'language' => 'eng',
					// show line numbers in HTML renderers
					'lineNumbers' => true,
					// show a separator between different diff hunks in HTML renderers
					'separateBlock' => true,
					// show the (table) header
					'showHeader' => true,
					// the frontend HTML could use CSS "white-space: pre;" to visualize consecutive whitespaces
					// but if you want to visualize them in the backend with "&nbsp;", you can set this to true
					'spacesToNbsp' => false,
					// HTML renderer tab width (negative = do not convert into spaces)
					'tabSize' => 4,
					// this option is currently only for the Combined renderer.
					// it determines whether a replace-type block should be merged or not
					// depending on the content changed ratio, which values between 0 and 1.
					'mergeThreshold' => 0.8,
					// this option is currently only for the Unified and the Context renderers.
					// RendererConstant::CLI_COLOR_AUTO = colorize the output if possible (default)
					// RendererConstant::CLI_COLOR_ENABLE = force to colorize the output
					// RendererConstant::CLI_COLOR_DISABLE = force not to colorize the output
					'cliColorization' => RendererConstant::CLI_COLOR_AUTO,
					// this option is currently only for the Json renderer.
					// internally, ops (tags) are all int type but this is not good for human reading.
					// set this to "true" to convert them into string form before outputting.
					'outputTagAsString' => false,
					// this option is currently only for the Json renderer.
					// it controls how the output JSON is formatted.
					// see available options on https://www.php.net/manual/en/function.json-encode.php
					'jsonEncodeFlags' => \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
					// this option is currently effective when the "detailLevel" is "word"
					// characters listed in this array can be used to make diff segments into a whole
					// for example, making "<del>good</del>-<del>looking</del>" into "<del>good-looking</del>"
					// this should bring better readability but set this to empty array if you do not want it
					'wordGlues' => [' ', '-'],
					// change this value to a string as the returned diff if the two input strings are identical
					'resultForIdenticals' => null,
					// extra HTML classes added to the DOM of the diff container
					'wrapperClasses' => ['diff-wrapper'],
				];

				$result = DiffHelper::calculateFiles(base_path($request->path),$pathMyInTMP, $rendererName, $differOptions, $rendererOptions);
				$list = scandir(storage_path('tmp'));
				unset($list[0],$list[1]);
				foreach ($list as $file)
				{
					unlink(storage_path('tmp/'.$file));
				}
				return $result;
			}

			//Пропускаемые для экспорта и прочее
			if(in_array($request->command,array_keys($this->commands)))
			{
				$property_id = null;
				if($this->checkType($request->path)>=0)
				{
					$property_id = $this->commands[$request->command][$this->checkType($request->path)];
				}
				if(is_null($property_id))
				{
					$property_id = $this->commands[$request->command][$request->type];
				}
				//Получаем свойства и папки
				$this->getPropertys();
				//Проверяем наличие уже в массиве
				$elements = (array)$this->propertys->where('id',$property_id)->first()->elements;
				if(in_array($request->path,(array)$this->propertys->where('id',$property_id)->first()->elements))
				{
					unset($elements[array_search($request->path,$elements)]);
					$text = 'Элемент удален';
				}
				else
				{
					$elements[]=$request->path;
					$text = 'Элемент добавлен';
				}
				$this->sync->propertys()->updateExistingPivot($property_id,['value'=>json_encode($elements,JSON_UNESCAPED_SLASHES)]);
				return response()->json(['type'=>'alert','text'=>$text],200,[],JSON_UNESCAPED_UNICODE);
			}
		}
	}

	//Генерирует патчи
	public function genParentFolders($path)
	{
		//Получаем все патчи
		$explodes = explode('/',$path);
		if(strpos(end($explodes), ".") !== false)
		{
			array_pop($explodes);
		}
		$paths = [];
		for($i=count($explodes)-1;$i>-1;$i--)
		{
			$paths[]=implode('/',$explodes);
			array_pop($explodes);
		}
		return $paths;
	}

	//Получаем все свойства с папками и файлами
	public function getPropertys()
	{
		$propertyIDS = [];
		foreach($this->commands as $command=>$values)
		{
			foreach($values as $key=>$value)
			{
				$propertyIDS[] = $value;
			}
		}

		$this->propertys = $this->sync->propertys()->whereIn('property_id',$propertyIDS)->get();

		foreach($this->propertys as $keyProperty=>$property)
		{
			if($property->pivot->value==null || empty($property->pivot->value))
			{
				//dd('null or empty');
			}
			else
			{
				$elements = json_decode($property->pivot->value);
			}
			$this->propertys[$keyProperty]->elements=$elements;
		}
	}

	//Создает все файлы для синхронизации
	public function createFilesForSend()
	{
		//Получаем свойства
		foreach($this->propertys as $property)
		{
			//Генерирует названия файлов для отправки
				//IDPROJECT
				//SEND OR RESULT
				//DATE GENERATE
				//PROPERTY_ID
			if(in_array($property->id,[68,69,70,71]))
			{
				continue;
			}
			$filename=$this->object->id.'_S_'.$this->time.'_'.$property->id.'.csv';

			//Сразу наполняем массив syncFiles
			if(!isset($this->syncFiles[$this->object->id])){$this->syncFiles[$this->object->id]=[];}
			if(!isset($this->syncFiles[$this->object->id]['S'])){$this->syncFiles[$this->object->id]['S']=[];}
			if(!isset($this->syncFiles[$this->object->id]['S'][$this->time])){$this->syncFiles[$this->object->id]['S'][$this->time]=[];}
			$this->syncFiles[$this->object->id]['S'][$this->time][]=$filename;

			//Записываем в файл
			$fp = fopen(storage_path('app/csvfiles/'.$filename),'w');
			fputcsv($fp,$property->elements);
			fclose($fp);
		}
	}

	//Получает список файлов уже в папке синхронизации
	public function getFilesSync($project_id)
	{
		//Если файлы
		$items = @scandir(storage_path('app/csvfiles'));
		foreach($items as $item)
		{
			if(($item == '.') || ($item == '..')) continue;
			$explodes = explode('_',$item);
			//Если это результаты
			if($explodes[1]=='R')
			{
				if(!isset($this->syncFiles[$explodes[0]])){$this->syncFiles[$explodes[0]]=[];}
				if(!isset($this->syncFiles[$explodes[0]][$explodes[1]])){$this->syncFiles[$explodes[0]][$explodes[1]]=[];}
				if(!isset($this->syncFiles[$explodes[0]][$explodes[1]][$explodes[2]])){$this->syncFiles[$explodes[0]][$explodes[1]][str_replace(".csv","",$explodes[2])]=$item;}
			}
			//Блокированные
			else if($explodes[1]=='B')
			{
				if(!isset($this->syncFiles[$explodes[0]])){$this->syncFiles[$explodes[0]]=[];}
				if(!isset($this->syncFiles[$explodes[0]][$explodes[1]])){$this->syncFiles[$explodes[0]][$explodes[1]]=[];}
				if(!isset($this->syncFiles[$explodes[0]][$explodes[1]][$explodes[2]])){$this->syncFiles[$explodes[0]][$explodes[1]][str_replace(".csv","",$explodes[2])]=$item;}
			}
			//Подготовленные архивы
			else if($explodes[1]=='Z')
			{
				if(!isset($this->syncFiles[$explodes[0]])){$this->syncFiles[$explodes[0]]=[];}
				if(!isset($this->syncFiles[$explodes[0]][$explodes[1]])){$this->syncFiles[$explodes[0]][$explodes[1]]=[];}
				if(!isset($this->syncFiles[$explodes[0]][$explodes[1]][$explodes[2]])){$this->syncFiles[$explodes[0]][$explodes[1]][str_replace(".zip","",$explodes[2])]=$item;}
			}
			//На отправку
			else
			{
				if(!isset($this->syncFiles[$explodes[0]])){$this->syncFiles[$explodes[0]]=[];}
				if(!isset($this->syncFiles[$explodes[0]][$explodes[1]])){$this->syncFiles[$explodes[0]][$explodes[1]]=[];}
				if(!isset($this->syncFiles[$explodes[0]][$explodes[1]][$explodes[2]])){$this->syncFiles[$explodes[0]][$explodes[1]][$explodes[2]]=[];}
				$this->syncFiles[$explodes[0]][$explodes[1]][$explodes[2]][]=$item;
			}
		}
		//Выполняем первичная проверка на существование
		if(!isset($this->syncFiles[$project_id]))
		{
			//Проектных файлов нет сразу создаем
			$this->createFilesForSend();

			//Оставляем файлы тока нужного проекта
			$this->syncFiles = $this->syncFiles[$project_id];

			//Даем задания все исполнить
			$this->createBus();

			dd('Файлы синхронизации проекта отсутствовали вообще! Они созданы и все задания отправлены');
		}
		else
		{
			$this->syncFiles = $this->syncFiles[$project_id];
		}
	}

	//Проверяем результат перед выдачей
	public function checkBeforeReturn()
	{
		//Если есть блокированные файлы просто пропускаем
		if(isset($this->syncFiles['B']))
		{return 0;}
		//Есть на отправку
		if(isset($this->syncFiles['S']))
		{
			//Удостоверяем что они точно есть
			if(count($this->syncFiles['S'])>0)
			{
				//Если отсуствует результат
				if(!isset($this->syncFiles['R']))
				{
					try{
						$findValue = storage_path('app/csvfiles/'.$this->syncFiles['S'][max(array_keys($this->syncFiles['S']))][0]);
					}
					catch(\Exception $e)
					{
						dd('Старое удалил, новое не добавил! Обновись');
					}
					//Индекс отваливается когда старое удалил и новое еще не добавилось!
					
					
					
					//Получаем задания
					$jobsInDB = Job::where('queue','syncFiles')->get();
					$jobsInDB = $this->getChains($jobsInDB);
					$findedInJobs = $this->searchVariable($jobsInDB,'myPath',$findValue);
					if($findedInJobs)
					{
						dd('Задание еще в очереди');
					}
					
					//Проверяем разбитые автобусы
					$jobsFailed = JobFailed::where('queue','syncFiles')->get();
					$jobsFailed = $this->getChains($jobsFailed);
					$findedInJobsFailed = $this->searchVariable($jobsFailed,'myPath',$findValue);
					if($findedInJobs)
					{
						dd('Задание провалилось');
					}
				}
			}
			//Файлов на отправку нет смотрим результаты
			else
			{
				//Если отсуствует результат
				if(!isset($this->syncFiles['R']))
				{
					$this->createFilesForSend();
					$this->createBus();
					dd('Файлы создал, задания раздал');
				}
			}
		}
		//Нет на отправку
		else
		{
			//Если старые файлы удалил, но результат еще есть часовой
			if(!isset($this->syncFiles['R']))
			{
				$this->createFilesForSend();
				$this->createBus();
				dd('Файлы создал, задания раздал');
			}
			//Есть результат
			else
			{
				//dd('Есть результат');
			}
		}
	}

	//Удаляем просроченные
	public function deleteLastExpired()
	{
		//Перебираем типы
		foreach($this->syncFiles as $type=>$arrayTimes)
		{
			foreach($arrayTimes as $time=>$files)
			{
				if($type=='B') continue;

				if($type=='S')
				{
					//Если разница больше 2 минут
					if($this->getDiffInMinutes($time)>2)
					{
						foreach($files as $indexFile=>$file)
						{
							unlink(storage_path('app/csvfiles/'.$this->syncFiles[$type][$time][$indexFile]));
							unset($this->syncFiles[$type][$time][$indexFile]);
						}
					}
				}

				if($type=='R')
				{
					//Прошло 20 минут
					if($this->getDiffInMinutes($time)>20)
					{
						unlink(storage_path('app/csvfiles/'.$this->syncFiles[$type][$time]));
						unset($this->syncFiles[$type][$time]);
					}
				}
			}
		}
	}

	//Создаем автобус со списокм заданий на отправкуЮ, запуск команды и загрузку обратно
	public function createBus()
	{
		//Для отправки файлов
		$paramsSendFile = [];
		$paramsSendFile['ids']=$this->object->id;
		$paramsSendFile['myPath'] = [];
		foreach($this->syncFiles['S'][max(array_keys($this->syncFiles['S']))] as $file)
		{
			$paramsSendFile['myPath'][] = storage_path('app/csvfiles/'.$file);
			$paramsSendFile['remotePath'][] = $this->object->propertyById(64)->pivot->value.'storage/app/csvfiles/'.$file;
		}

		//Для скачивания файлов
		$paramsDownloadFile = [];
		$paramsDownloadFile['ids']=$this->object->id;
		$paramsDownloadFile['myPath'] = storage_path('app/csvfiles/'.$this->object->id.'_R_'.$this->time.'.csv');
		$paramsDownloadFile['remotePath'] = $this->object->propertyById(64)->pivot->value.'storage/app/csvfiles/R_'.$this->time.'.csv';
		foreach($this->syncFiles['S'][max(array_keys($this->syncFiles['S']))] as $file)
		{
			$paramsSendFile['myPath'][] = storage_path('app/csvfiles/'.$file);
			$paramsSendFile['remotePath'][] = $this->object->propertyById(64)->pivot->value.'storage/app/csvfiles/'.$file;
		}
		Bus::chain([
			//Отправляем файл для проверки
			new sendFiles($paramsSendFile),
			//Запускаем на проекте проверку
			new runCommandOnProject(['ids'=>$this->object->id,'command'=>'checkFilesInProject']),
			//Скачиваем файл
			new downloadFiles($paramsDownloadFile)
		])->onQueue('syncFiles')->dispatch();
	}

	//Получить разницу в минутах
	public function getDiffInMinutes($time)
	{
		$now = time();
		$diffSeconds = $now-$time;
		$diffMinutes = $diffSeconds/60;
		return $diffMinutes;
	}

	//Получаем свои файлы
	public function getMyFiles()
	{
		//Генерирует патчи
		$genParentFolders = function ($path)
		{
			//Получаем все патчи
			$explodes = explode('/',$path);
			if(strpos(end($explodes), ".") !== false)
			{
				array_pop($explodes);
			}
			$parentPaths = [];
			for($i=count($explodes)-1;$i>-1;$i--)
			{
				$paths[]=implode('/',$explodes);
				array_pop($explodes);
			}
			return $paths;
		};

		//Поиск в массиве
		$searchInPropertys = function($item)
		{
			foreach($this->propertys as $property)
			{
				if(in_array($item,(array)$property->elements))
				{
					return $property->id;
				}
			}
			return null;
		};

		//Рекурсивная функция
		$recScan = function ($path=null) use (&$recScan, &$result,&$searchInPropertys,&$genParentFolders)
		{
			$items = @scandir(base_path($path));
			foreach ($items as $item)
			{
				// Отбрасываем текущий и родительский каталог
				if(($item == '.') || ($item == '..')) continue;
				$pathForPropertys = null;
				$pathForFileSystem = null;
				$paths = null;
				//Если корневая указываем патч напрямую иначе с путем
				if($path==null)
				{
					$pathForPropertys = $item;
					$pathForFileSystem = base_path($item);
				}
				else
				{
					$pathForPropertys = $path.'/'.$item;
					$pathForFileSystem = base_path($path.'/'.$item);
				}
				//Ищем сам элемент для начала
				$result = $searchInPropertys($pathForPropertys);
				if(is_null($result))
				{
					//Сам элемент не найден ищем корневые
					if($path!=null)
					{
						//Получаем родителей
						$parents = $genParentFolders($pathForPropertys);
						$resultParents = $searchInPropertys($pathForPropertys);
						if(!is_null($resultParents))
						{
							dd('Найден не нулевой результат');
						}
					}
				}
				else
				{
					//Сам элемент найден
					if($result==66)
					{
						//echo $pathForPropertys. ' - папка пропущена! Свойство '.$result."\n";
						continue;
					}
					if($result==67)
					{
						//echo $pathForPropertys. ' - пропущен! Свойство '.$result."\n";
						continue;
					}
					if($result==68)
					{
						//echo $pathForPropertys. ' - папка пропущена! Свойство '.$result."\n";
						//continue;
					}
					if($result==69)
					{
						//echo $pathForPropertys. ' - пропущен! Свойство '.$result."\n";
						//continue;
					}
					if($result==70)
					{
						//echo $pathForPropertys. ' - папка пропущена! Свойство '.$result."\n";
						continue;
					}
					if($result==71)
					{
						//echo $pathForPropertys. ' - пропущен! Свойство '.$result."\n";
						continue;
					}
					if($result==72)
					{
						//echo $pathForPropertys. ' - ! Свойство '.$result."\n";
						//continue;
					}
					if($result==73)
					{
						//echo $pathForPropertys. ' - ! Свойство '.$result."\n";
						//continue;
					}
				}

				if(is_file($pathForFileSystem))
				{
					$this->myFiles[]=[
						$pathForPropertys,md5_file($pathForFileSystem)
					];
				}
				else
				{
					//это директория
					
					//Сканируем внутрение папки
					$recScan($pathForPropertys);
				}
			}
		};

		//Получаем список файлов и т д
		$recScan();
	}

	//Получить результат
	public function getResult()
	{
		//Есть есть заблокированный	 файл то работаем с ним
		if(isset($this->syncFiles['B']))
		{
			$FH = fopen(storage_path('app/csvfiles/'.$this->syncFiles["B"][max(array_keys($this->syncFiles['B']))]),'r');
			$this->syncBlock = true;
			$this->time=max(array_keys($this->syncFiles['B']));
		}
		else
		{
			if(isset($this->syncFiles['R']))
			{
				if(count($this->syncFiles['R'])>0)
				{
					$FH = fopen(storage_path('app/csvfiles/'.$this->syncFiles["R"][max(array_keys($this->syncFiles['R']))]),'r');
				}
				else
				{
					dd('Результаты не скачались');
				}
			}
			else
			{
				dd('Результаты не скачались');
			}
		}
		while(!feof($FH))
		{
			$values= fgetcsv($FH,1000,',');
			if($values)
			{
				$this->remoteFiles[]=$values;
			}
		}
		fclose($FH);

		//Записываем время для блокировки
		if(!isset($this->syncBlock)) $this->time=max(array_keys($this->syncFiles['R']));
	}

	//Найти файл в списке результатов
	public function searchInRemoteFiles($path)
	{
		foreach($this->remoteFiles as $key=>$array)
		{
			try{
				if($array[0]==$path)
				{
					$hash = $array[1];
					//Удаляем найденные
					unset($this->remoteFiles[$key]);
					return $hash;
				}
				else
				{
					//dd($array);
				}	
			}
			catch(\Exception)
			{
				dd($this->remoteFiles);
			}
		}
		return null;
	}

	/*
		//есть задача
		//echo 'jobid '.$job->id."\n";
		/*
		Есть задача
		У нее есть payload
			+"uuid": "ebd12016-6dec-4103-b53f-588f5a595042"
			+"displayName": "App\Jobs\ForControlProjects\sendFiles"
			+"job": "Illuminate\Queue\CallQueuedHandler@call"
			+"maxTries": null
			+"maxExceptions": null
			+"failOnTimeout": false
			+"backoff": null
			+"timeout": null
			+"retryUntil": null
			+"data":
				В data есть
				+"commandName": "App\Jobs\ForControlProjects\sendFiles"
				+"command":
				В command есть unserialize(Данные)

				  +params: array:3 [▼
					"ids" => 18
					"myPath" => array:12 [▶]
					"remotePath" => array:12 [▶]
				  ] - это передаваеммые данные

				  +object: null
				  +ssh: null
				  +job: null
				  +connection: null
				  +queue: "syncFiles"
				  +chainConnection: null
				  +chainQueue: "syncFiles"
				  +chainCatchCallbacks: []
				  +delay: null
				  +afterCommit: null
				  +middleware: []

				  +chained: array:2 [▶]
				  Это массив следущих заданий
		*/

				//Преобразовать команды в автобусах
				
				/*Проверить цикл на вложеность*/
				
	public function getChains($jobs)
	{
		//Перебираем работников
		foreach($jobs as $key=>$job)
		{
			if(isset($job->payload))
			{
				$jobClass = unserialize($job->payload->data->command);
				if(count($jobClass->chained)>0)
				{
					$collectionJobs = collect();
					foreach($jobClass->chained as $keyChain=>$chainJob)
					{
						$chainJobClass = unserialize($chainJob);
						$collectionJobs->push($chainJobClass);
					}
					$jobs[$key]->childrens = $collectionJobs;
					$this->getChains($jobs[$key]->childrens);
				}
			}
		}
		return $jobs;
	}

	//Найти переменную
	public function searchVariable($jobs,$name,$value)
	{
		//echo count($jobs);
		foreach($jobs as $keyJob=>$job)
		{
			if(isset($job->payload))
			{
				$command = unserialize($job->payload->data->command);
				if(isset($command->params))
				{
					if(isset($command->params[$name]))
					{
						if(is_array($command->params[$name]))
						{
							foreach($command->params[$name] as $valueforCheck)
							{
								if(in_array($value,$command->params[$name]))
								{
									
									return true;
								}
								else
								{
									return $command->params[$name]==$valueforCheck;
								}
							}
						}
						else
						{
							
						}
					}
				}
				else
				{
					
				}
			}
			//Если есть параметры
			if(isset($job->params))
			{
				if(isset($job->params[$name]))
				{
					if(is_array($job->params[$name]))
					{
						dd(777);
					}
					echo $job->params[$name];
					echo $job->params[$name] == $value;
					if($job->params[$name]==$value)
					{
						/*                               ПРОВЕРИТЬ ЗНАЧЕНИЕ ВЕДЬ ОНО В МаССИВЕ */
						$job->params[$name]==$value;
						dd($value);
						dd('finish HIM');
					}
				}
			}
			if(isset($job->childrens))
			{
				//dd($job->childrens[0]->params);
				//echo $value=$value."+1";
				$this->searchVariable($job->childrens,$name,$value);
			}
		}
	}

	//Поиск в массиве свойств
	public function searchInPropertys($path)
	{
		foreach($this->propertys as $property)
		{
			if(is_array($path))
			{
				foreach($path as $keyParent=>$parent)
				{
					//echo "Ищу родителя $parent в свойстве $property->id\n";
					if(in_array($parent,$property->elements))
					{
						return [$property->id,$parent];
					}
				}
			}
			else
			{
				if(in_array($path,$property->elements))
				{
					return $property->id;
				}
			}
		}
		return null;
	}

	//Возвращает тип файл или директория
	public function checkType($path)
	{
		if(is_file(base_path($path)))
		{
			return 1;
		}
		elseif(is_dir(base_path($path)))
		{
			return 0;
		}
		else{
			return -1;
		}
	}

}