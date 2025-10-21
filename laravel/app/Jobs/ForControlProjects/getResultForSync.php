<?php
namespace App\Jobs\ForControlProjects;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

use Illuminate\Support\Facades\Http;

use App\Models\Core\Objects;

use App\Jobs\ForControlProjects\checkFilesSync;
use App\Jobs\ForControlProjects\checkDBSync;

use App\Helpers\Logs\Logs as Logator;

class getResultForSync implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable;

	public $params = null;

	public $object = null;

	public $logator;

	public function __construct($params = null)
	{
		$this->params = $params;
	}

	public function handle()
	{
		$this->object = Objects::find($this->params['id']);

				//Логатор
		$this->logator = new Logator;

		$this->logator->setAuthor('getResultForSync');
		$this->logator->setType('success');
		$this->logator->setText('Запущен процесс получения результатов синхронизации с проекта '.$this->object->id);
		$this->logator->write();

		$domainsText = $this->object->propertyById(77)->pivot->value ?? null;
		$domainsExploded = explode(',',str_replace(' ','',$domainsText));

		if(is_null($domainsText))
		{
			$this->logator->setType('danger');
			$this->logator->setText('Ошибка не указаны домены для проекта '.$this->object->id);
			$this->logator->write();
			exit;
		}

		$domainsExploded = explode(',',str_replace(' ','',$domainsText));

		$url = $domainsExploded[0].'/gateway';

		$response = Http::post($url,$this->params['data']);

		// Проверяем статус ответа
		if($response->status() == 200)
		{
			//Получаем данные файла
			$fileContent = $response->getBody();
			$file_name = date('Y-m-d H-i');

			//Определяем путь до папки
			if($this->params['data']['type'] == 'db')
			{
				$pathFolder = storage_path('sync/'.$this->params['id'].'/db');
			}
			elseif($this->params['data']['type']=='files')
			{
				$pathFolder = storage_path('sync/'.$this->params['id'].'/hashes');
			}
			elseif($this->params['data']['type']=='zip')
			{
				$pathFolder = storage_path('sync/'.$this->params['id'].'/archives');
			}

			//Проверяем папку
			if(!is_dir($pathFolder))
			{
				mkdir($pathFolder, 0777, true);
			}

			file_put_contents($pathFolder.DIRECTORY_SEPARATOR.$file_name,$fileContent);
			//$file_handle = fopen($pathFolder.DIRECTORY_SEPARATOR.$file_name, 'w+');
			//fwrite($file_handle,$fileContent);
			//fclose($file_handle);

			//Определяем дополнительные работы после выполнения
			if($this->params['data']['type'] == 'db')
			{
			}
			elseif($this->params['data']['type']=='files')
			{
				$this->logator->setType('success');
				$this->logator->setText('Добавляем задание на загрузку архива файлов для проекта '.$this->params['id']);
				$this->logator->write();
				dispatch(new $this([
					'id'=>$this->params['id'],
					'data'=>[
						'command'=>'get',
						'type'=>'zip'
					]
				]))->onQueue('sync');
			}
			elseif($this->params['data']['type']=='zip')
			{
				$this->logator->setType('success');
				$this->logator->setText('Добавляем задание на проверку файлов '.$this->params['id']);
				$this->logator->write();
				dispatch(new checkFilesSync(['id'=>$this->params['id']]))->onQueue('sync');
			}

			// Если статус ответа 200, значит запрос успешно отправлен
			$this->logator->setType('success');
			$this->logator->setText('Запрос успешно отправлен. Для проекта '.$this->object->id. ' данные: '.json_encode($this->params));
			$this->logator->write();
		}
		else
		{
			// В противном случае возвращаем ошибку
			$this->logator->setType('danger');
			$this->logator->setText('Запрос получения результатов провален. Для проекта '.$this->object->id. ' данные: '.json_encode($this->params));
			$this->logator->write();
			dd('getResult',$response->getBody(),$response);
			return $response->error();
		}
	}
}