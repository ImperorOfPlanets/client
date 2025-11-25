<?php
namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

use App\Jobs\Sync\ExportDB;
use App\Jobs\Sync\GetHashesFiles;
use App\Jobs\Assistant\Messages\ProcessingResult;

use App\Helpers\Logs\Logs as Logator;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class GatewayController extends Controller
{
	public $logator;

	//Для пропуска
	public $arrayForSkip = [
		'app/Http/Controllers/Controller.php',
		'bootstrap/cache',
		'node_modules',
		'public/build',
		'storage/framework',
		'storage/sync',
		'tests',
		'vendor'
	];

	//Для пропуска
	public $forZip = [];

	public function index(){
	}

	public function store(Request $request){

		//Логатор
		$this->logator = new Logator;

		$this->logator->setAuthor('Gateway');
		$this->logator->setType('success');
		$this->logator->setText('Пришел запрос '.json_encode($request->all()));
		$this->logator->write();

		//Синхронизация
		if(isset($request->command))
		{
			if($request->command=='sync')
			{  
				if($request->type=='db')
				{
					//Добавляем JOB БД для экспорта БД
					$this->logator->setType('success');
					$this->logator->setText('Добавляем задание экспорта БД');
					$this->logator->write();
					dispatch(new ExportDB())->onQueue('sync');
				}
				elseif($request->type=='files')
				{
					//Добавляем JOB БД для получения хешей файлов
					$this->logator->setType('success');
					$this->logator->setText('Добавляем задание создания хешей файлов');
					$this->logator->write();
					dispatch(new GetHashesFiles())->onQueue('sync');
				}
				elseif($request->type=='start')
				{
					//Добавляем JOB БД для получения хешей файлов
					$this->logator->setType('success');
					$this->logator->setText('Запускаем процесс синхронизации');
					$this->logator->write();

					//Добавляем JOB БД для получения хешей файлов
					$this->logator->setType('success');
					$this->logator->setText('Создаем архив со старыми файлами');
					$this->logator->write();

					//Определяем имя фалов для всех будущих фалов в папках
					$filename = date('Y-m-d H-i');

					$this->scanPath(base_path());

					//Создаем папку архивов до копирования
					$pathBefore = storage_path('sync/before');
					if(!is_dir($pathBefore))
					{
						mkdir($pathBefore, 0777, true);
					}

					//Создаем архив
					$this->createZIP($pathBefore.DIRECTORY_SEPARATOR.$filename);

					//Получаем архив с файлом в паппку forCopy и распаковываем в этужу папку
					$unzip = false;
					if($request->file('attachment')->isValid())
					{
						
						$this->logator->setType('success');
						$this->logator->setText('Cкачиваем файл');
						$this->logator->write();
						$path = storage_path('sync/forCopy/');
						if(!is_dir($path))
						{
							mkdir($path, 0777, true);
						}
						$request->file('attachment')->move($path,$filename.'.zip');

						$fullPath = $path.DIRECTORY_SEPARATOR.$filename;

						$this->logator->setType('success');
						$this->logator->setText('Создаем папку для распаковки');
						$this->logator->write();
						if(!is_dir($fullPath))
						{
							mkdir($fullPath, 0777, true);
						}

						$this->logator->setType('success');
						$this->logator->setText('Файл скачан - начинаем распаковку');
						$this->logator->write();

						// Распаковываем архив
						if(extension_loaded('zip') && file_exists($fullPath.'.zip'))
						{
							$zip = new \ZipArchive();
							if ($zip->open($fullPath.'.zip') === TRUE)
							{
								$zip->extractTo($fullPath);
								$zip->close();
								$unzip = true;
								$this->logator->setType('success');
								$this->logator->setText('Архив успешно распакован.');
								$this->logator->write();
							}
							else
							{
								$this->logator->setType('danger');
								$this->logator->setText('Не удалось открыть архив.');
								$this->logator->write();
							}
						}
						else
						{
							$this->logator->setType('danger');
							$this->logator->setText('Функция zip не загружена или архив не существует.');
							$this->logator->write();
						}
					}

					//Удаляем файлы если есть
					$this->logator->setType('success');
					$this->logator->setText('Удаляем файлы.');
					$this->logator->write();

					//Удаляем файлы
					$work = json_decode($request->work,true);
					foreach($work['delete'] as $path)
					{
						$bPath = base_path($path);
						if(is_dir($bPath))
						{
							$result = shell_exec("rm -rf {$bPath}");
						}
						else
						{
							if(file_exists($bPath))
							{
								unlink($bPath);
							}
						}
					}

					//Копируем файлы
					foreach($work['copy'] as $path)
					{
						$fromPath = $fullPath.DIRECTORY_SEPARATOR.$path;
						$toPath = base_path($path);
						copy($fromPath,$toPath);
					}
				}
			}
			//Вовзрашаеит результаты
			elseif($request->command=='get')
			{

				if($request->type=='db')
				{
					//Проверяем существование последнего файла
					$path = storage_path('sync/db');
				}
				elseif($request->type=='files')
				{
					//Проверяем существование последнего файла
					$path = storage_path('sync/hashes');
				}
				elseif($request->type=='zip')
				{
					//Проверяем существование последнего файла
					$path = storage_path('sync/archives');
				}

				//Получаем список файлов
				$files = glob($path.'/*');

				//Сортируем
				usort($files,array($this,'compareDates'));
				//Получаем последнее значение массива
				$lastElement = $files[count($files) - 1];

				if(file_exists($lastElement))
				{
					return response()->download($lastElement);
				}
				else
				{
					return response()->json(['error' => 'File not found.'], 404);
				}
			}
			//Технические работы
			elseif($request->command=='down')
			{
				if($request->type=='on')
				{
					if(isset($request->secret))
					{
						Artisan::call('down',[
							'--refresh'=>15,
							'--render'=>"errors::down",
							'--secret'=>$request->secret
						]);
					}
					else
					{
						Artisan::call('down',[
							'--refresh'=>15,
							'--render'=>"errors::down"
						]);
					}
				}
				elseif($request->type=='off')
				{
					Artisan::call('up');
				}
			}
			elseif($request->command=='messages')
			{
				Bus::chain([
					//Обрабатываем резульбтати ответа от сервера
					new	ProcessingResult($request->all())
				])->dispatch();
			}
		}
		else
		{
			$this->logator->setType('danger');
			$this->logator->setText('Пришел запрос без команды');
			$this->logator->write();
		}
	}

	// Функция для сравнения двух строк, представляющих даты и время
	private function compareDates($file1,$file2)
	{
		$dateTime1 = \DateTime::createFromFormat('Y-m-d H-i', basename($file1));
		$dateTime2 = \DateTime::createFromFormat('Y-m-d H-i', basename($file2));
		return $dateTime1 > $dateTime2;
	}

	//Функция полного копирования файлов
	private function copyFullDirectory($sourceDir,$targetDir) 
	{
		//Проверяем, существует ли целевая директория
		if (!is_dir($targetDir))
		{
			mkdir($targetDir, 0777, true);
		}

		// Получаем список файлов и поддиректорий в исходной директории
		$files = scandir($sourceDir);
	
		foreach ($files as $file)
		{
			if ($file == '.' || $file == '..') continue; // Пропускаем точки
	
			$sourceFile = $sourceDir . '/' . $file;
			$targetFile = $targetDir . '/' . $file;
	
			if (is_dir($sourceFile))
			{
				// Если это директория, копируем её рекурсивно
				$this->copyFullDirectory($sourceFile, $targetFile);
			}
			else
			{
				// Если это файл, копируем его
				copy($sourceFile, $targetFile);
			}
		}
	}

	//Функция создания zip архива
	public function createZIP($zipName)
	{
		$zip = new \ZipArchive();
		if ($zip->open($zipName, \ZipArchive::CREATE) === TRUE) {
			// Добавляем каждый файл в архив
			foreach ($this->forZip as $file) {
				$zip->addFile($file);
			}
			// Закрываем архив
			$zip->close();
			$this->logator->setType('success');
			$this->logator->setText("Архив '{$zipName}' успешно создан.");
			$this->logator->write();
		} else
		{
			$this->logator->setType('danger');
			$this->logator->setText('Не удалось создать архив.');
			$this->logator->write();
		}
	}

	//Сканировать путь
	public function scanPath($path)
	{
		$skip = false;
		foreach($this->arrayForSkip as $skipFolder)
		{
			if(str_contains($path,$skipFolder))
			{
				//echo "Пропускаем: ".$path.' содержит '. $skipFolder."\n";
				$skip = true;
				continue;
			}
		}
		if($skip){return;}

		if (is_file($path))
		{
			$this->forZip[] = $path;
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
					$this->scanPath($itemPath);
				}
				else
				{
					$this->forZip[] = $itemPath;
				}
			}
		}
	}
}