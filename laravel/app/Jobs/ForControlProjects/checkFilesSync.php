<?php
namespace App\Jobs\ForControlProjects;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

use App\Models\Core\Objects;

use App\Http\Controllers\Control\Sync\SyncDBController;

use App\Helpers\Logs\Logs as Logator;

class checkFilesSync implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable;

	public $params = null;

	public $object = null;

	//Массив местный
	public $hashes = [];

	//Массив клиента
	public $remoteArray = [];

	public $logator;

	public $work = [
		'delete' => [],
		'copy'=> [],
		'create'=>[]
	];

    public $skipForDelete = [
        'artisan',
		'app/Http/Controllers/Controller.php',
        'bootstrap/cache',
        'bootstrap/providers.php',
        'composer.json',
		'composer.lock',
        'config',
        'database',
        'node_modules',
		'package-lock.json',
		'package.json',
		'phpunit.xml',
        'public/build',
        'public/favicon.ico',
        'public/hot',
        'public/index.php',
        'public/robots.txt',
        'resources/css',
        'storage/app',
        'storage/framework',
        'storage/logs',
        'storage/sync',
        'tests',
        'vendor'
    ];

	public function __construct($params = null)
	{
		$this->params = $params;
	}

	public function handle()
	{
		$this->object = Objects::find($this->params['id']);

				//Логатор
		$this->logator = new Logator;

		$this->logator->setAuthor('checkFilesSync');
		$this->logator->setType('success');
		$this->logator->setText('Запущен процесс проверки файлов проекта '.$this->object->id);
		$this->logator->write();

				//Получаем последние файлы по дате и получаем строки
		$pathFolder = storage_path('sync/'.$this->params['id'].'/hashes');

				//Получаем список файлов
		try
		{
			$files = glob($pathFolder.'/*');
		}
		catch(\Exception $e)
		{
			$this->logator->setType('danger');
			$this->logator->setText('Попытка провести синхронизацию файлов без скаченных результатов для проекта '.$this->object->id);
			$this->logator->write();
			exit;
		}

		//Сортируем
		usort($files,array($this,'compareDates'));

		//Получаем последнее значение массива
		$lastElement = $files[count($files) - 1];

		//Получаем строки
		$lastStrings = file_get_contents($lastElement);
		$stringsArray = explode("\n",$lastStrings);

		//Переводим в массив для сравнения
		foreach($stringsArray as $string)
		{
			if(!str_contains($string,":"))
			{
				//echo "Пропускаем строку ".$string." т. к. не содердит двоеточие\n";
				continue;
			}
			$exploded = explode(":",trim($string));
			$this->remoteArray[$exploded[0]] = $exploded[1];
		}

		//Генерируем основные файлы
		$SyncController = new SyncDBController($this->params['id']);
		$SyncController->genFiles();

		//Получаем сгенерированные в папках
		$pathFolder = storage_path('projects'.DIRECTORY_SEPARATOR.$this->params['id']);
		$this->scanPath($pathFolder);


		//Начинаем сравнивание перебираем внутриние файлы
		foreach($this->hashes as $path=>$hash)
		{
			//echo "$path - $hash ";
			if($hash=='dir')
			{
				//echo "Это папка. ";
				//Проверяем папку на удаленном
				if(isset($this->remoteArray[$path]))
				{
					//echo "Существует на удаленном \n";
					unset($this->hashes[$path]);
					unset($this->remoteArray[$path]);
				}
				else
				{
					//echo "Отсуствует на удаленном - Создаем\n";
					$this->work['create'][]  =  $path;
					unset($this->hashes[$path]);
					unset($this->remoteArray[$path]);
				}
			}
			else
			{
				//echo "это файл \n";
				if(isset($this->remoteArray[$path]))
				{
					//Сравниваем хеши
					//echo "Сравниваем хеши $hash - ".$this->remoteArray[$path]."\n";
					//echo "Результат сравнения - ";
					if($this->hashes[$path] == $this->remoteArray[$path])
					{
						//echo "Хеши совпали - Ничего не надо\n";
						unset($this->hashes[$path]);
						unset($this->remoteArray[$path]);
					}
					else
					{
						//echo "Хеши разные - Добавляем файл в копирование\n";
						$this->work['copy'][] = $path;
						unset($this->hashes[$path]);
						unset($this->remoteArray[$path]);
					}
				}
				else
				{
					//echo "Файл отсуствует на удаленном добавляем в копирование\n";
					$this->work['copy'][] = $path;
					unset($this->hashes[$path]);
					unset($this->remoteArray[$path]);
				}
			}
			//echo "\n";
			//dd($path,$hash);
		}

        //Перебираем остатки на удаление
		foreach($this->remoteArray as $path=>$hash)
		{
			$skip = false;
            foreach($this->skipForDelete as $skipPath)
            {
				echo "Проверяем: ".$path.' содержит ли '. $skipPath." - ".str_contains($path,$skipPath)."\n";
                if(str_contains($path,$skipPath))
                {
                    //echo "Пропускаем: ".$path.' содержит '. $skipPath."\n";
                    $skip = true;
                    continue;
                }
            }
            if($skip){continue;}
			$this->work['delete'][]= $path;
		};

        $file_name = date('Y-m-d H-i');

        //Создаем файл результатов проверки
        $pathDirResults = storage_path('sync/results/'.$this->object->id.DIRECTORY_SEPARATOR.'files');
		if(!is_dir($pathDirResults))
		{
			mkdir($pathDirResults, 0777, true);
		}

        //Сериализуем остатки на ядре
        $serializedCore = serialize($this->hashes);
        $serializedClient = serialize($this->remoteArray);
        $serializedWork = serialize($this->work);

        //Сохраняем
        $file_handle = fopen($pathDirResults.DIRECTORY_SEPARATOR.$file_name, 'w+');
        fwrite($file_handle,'core->'.$serializedCore."\n");
        fwrite($file_handle,'client->'.$serializedClient."\n");
        fwrite($file_handle,'work->'.$serializedWork."\n");
        fclose($file_handle);

        //Создаем папку для и архив с фалами для копирования
        $pathForCopy = storage_path('sync/'.$this->object->id.DIRECTORY_SEPARATOR.'forCopy');

        //Проверяем папку
        if(!is_dir($pathForCopy))
        {
            mkdir($pathForCopy, 0777, true);
        }
        $this->createZIP($this->work['copy'],$pathForCopy,$file_name);

		dispatch(new sendJobForSync([
			'id'=>$this->object->id,
			'data'=>[
				'command'=>'sync',
				'type'=>'start',
				'work'=>json_encode($this->work,JSON_UNESCAPED_SLASHES)
			],
			'attach'=>$pathForCopy.DIRECTORY_SEPARATOR.$file_name,
			'attach_name'=>$file_name
		]))->onQueue('sync');



	}

	// Функция для сравнения двух строк, представляющих даты и время
	private static function compareDates($file1,$file2)
	{
		$dateTime1 = \DateTime::createFromFormat('Y-m-d H-i', basename($file1));
		$dateTime2 = \DateTime::createFromFormat('Y-m-d H-i', basename($file2));
		return $dateTime1 > $dateTime2;
	}

	// Функция для сравнения массивов
	private function compareArrays($array1, $array2)
	{
		$result = [];

		// Перебираем ключи первого массива
		foreach ($array1 as $key => $value)
		{
			// Проверяем, существует существует ли ключ во втором массиве
			if (array_key_exists($key, $array2))
			{
				// Проверяем, совпадают ли значения
				if ($value === $array2[$key]) {
					$result['files_exist'][] = $key;
					$result['hashes_match'][] = $key;
				} else {
					$result['files_exist'][] = $key;
					$result['hashes_dont_match'][] = $key;
				}
			}
			else
			{
				$result['files_exist'][] = $key;
				$result['hashes_dont_match'][] = $key;
			}
		}

		// Перебираем ключи второго массива
		foreach ($array2 as $key => $value)
		{
			// Проверяем, существует ли ключ в первом массиве
			if (!array_key_exists($key, $array1)) {
				$result['files_dont_exist'][] = $key;
			}
		}
		
		return $result;
	}

	//Сканировать путь
	public function scanPath($path)
	{
		if (is_file($path))
		{
			$this->getFileHash($path);
		}
		else
		{
			$items = scandir($path);

			foreach($items as $item)
			{
				if (in_array($item,array('.', '..'))) continue;
				$itemPath = $path.DIRECTORY_SEPARATOR.$item;
				if (is_dir($itemPath))
				{
					$this->addDir($itemPath);
					$this->scanPath($itemPath);
				}
				else
				{
					$this->getFileHash($itemPath);
				}
			}
		}
	}

	// Функция для получения хеша файла
	public function getFileHash($filePath)
	{
		// Получаем содержимое файла
		$content = file_get_contents($filePath);
		//$this->hashes[str_replace(DIRECTORY_SEPARATOR,'/',$filePath)]
		$hash = hash('sha256', $content);
		$this->hashes[str_replace('storage'.DIRECTORY_SEPARATOR.'projects'.DIRECTORY_SEPARATOR.$this->params['id'].DIRECTORY_SEPARATOR,'',str_replace(DIRECTORY_SEPARATOR,'/',str_replace(base_path(DIRECTORY_SEPARATOR),'',$filePath)))] = $hash;
	}

	//Функция добавляет директорию
	public function addDir($dirPath)
	{
		$this->hashes[str_replace('storage'.DIRECTORY_SEPARATOR.'projects'.DIRECTORY_SEPARATOR.$this->params['id'].DIRECTORY_SEPARATOR,'',str_replace(DIRECTORY_SEPARATOR,'/',str_replace(base_path(DIRECTORY_SEPARATOR),'',$dirPath)))] = 'dir';
	}

    //Функция создания архива
	function createZIP($array,$path,$name)
	{
		$zipPath = $path.DIRECTORY_SEPARATOR.$name; 
		$zip = new \ZipArchive;
		if($zip->open($zipPath, \ZipArchive::CREATE) === TRUE)
		{
			foreach($array as $path)
			{
				$newPath = storage_path('projects'.DIRECTORY_SEPARATOR.$this->object->id.DIRECTORY_SEPARATOR.$path);
				if(!is_file($newPath)){continue;}
				$zip->addFromString($path, file_get_contents($newPath));
			}
			$zip->close();
		}
	}
}