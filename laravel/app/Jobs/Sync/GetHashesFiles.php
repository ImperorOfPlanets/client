<?php
namespace App\Jobs\Sync;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

use App\Helpers\Logs\Logs as Logator;

class GetHashesFiles implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable;

	protected $hashes = [];
	protected $forZip = [];

	public $logator;

	//Для пропуска
	public $arrayForSkip = [
		'node_modules',
		'public/build',
		'storage/app',
		'storage/logs',
		'storage/framework',
		'storage/sync',
		'tests',
		'vendor'
	];

	public function handle()
	{
		//Папки для сравнения hash
		/*$arrayForHashes = [
			'app',
			'lang',
			'storage',
			'bootstrap/app.php',
			'bootstrap/providers.php',
			'public',
			'resources',
			'routes'
		];*/

					//Логатор
		$this->logator = new Logator;

		$this->logator->setAuthor('GetHashesFiles');
		$this->logator->setType('success');
		$this->logator->setText('Запущен процесс получения хешей файлов');
		$this->logator->write();

		$this->scanPath(base_path());

		//Перебираем папки для сканирования - формирует массив для хешей и массив для архивирования
		/*foreach($arrayForHashes as $item)
		{
			$itemPath = base_path(0);
			$this->scanPath($itemPath);
		}*/

				//Проверяем папку хешей
		$pathHashes = storage_path('sync/hashes');
		if(!is_dir($pathHashes))
		{
			mkdir($pathHashes, 0777, true);
		}

		//Создаем файл с хешами
		$file_name = $pathHashes.DIRECTORY_SEPARATOR.date('Y-m-d H-i');
		$file_handle = fopen($file_name, 'w+');
		fwrite($file_handle,implode("\n",$this->hashes));
		fclose($file_handle);

		$this->logator->setType('success');
		$this->logator->setText('Процесс получения хешей файлов окончен');
		$this->logator->write();
		unset($this->hashes);



					//Создаем Архив файлов
		$this->logator->setType('success');
		$this->logator->setText('Запускаю процесс создания архива файлов');
		$this->logator->write();

					//Проверяем папку
		$pathArchives = storage_path('sync/archives');
		$file_name = $pathArchives.DIRECTORY_SEPARATOR.date('Y-m-d H-i');
		if(!is_dir($pathArchives))
		{
			mkdir($pathArchives, 0777, true);
		}
		$this->createZIP($file_name);
	}

	//Сканировать путь
	public function scanPath($path)
	{
		$skip = false;
		foreach($this->arrayForSkip as $skipFolder)
		{
			if(str_contains($path,$skipFolder))
			{
				echo "Пропускаем: ".$path.' содержит '. $skipFolder."\n";
				$skip = true;
				continue;
			}
		}
		if($skip){return;}

		echo "Проверяю путь: ".$path."\n";
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
		$this->hashes[]=str_replace(DIRECTORY_SEPARATOR,'/',str_replace(base_path(DIRECTORY_SEPARATOR),'',$filePath)).':'.$hash;
		$this->forZip[]=$filePath;
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

	//Функция добавляет директорию
	public function addDir($dirPath)
	{
		$this->hashes[]=str_replace(DIRECTORY_SEPARATOR,'/',str_replace(base_path(DIRECTORY_SEPARATOR),'',$dirPath)).':dir';
	}
}