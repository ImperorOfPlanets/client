<?php
namespace App\Jobs\ForControlProjects;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

use Illuminate\Support\Facades\Http;

use App\Models\Core\Objects;

use App\Helpers\Logs\Logs as Logator;

class sendJobForSync implements ShouldQueue
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

		$this->logator->setAuthor('sendJobForSync');
        $this->logator->setType('success');
		$this->logator->setText('Запущен процесс отправки на синхронизацию проекту '.$this->object->id);
		$this->logator->write();

        $domainsText = $this->object->propertyById(77)->pivot->value ?? null;
        $domainsExploded = explode(',',str_replace(' ','',$domainsText));

        if(is_null($domainsText))
        {
            $this->logator->setType('danger');
            $this->logator->setText('Ошибка не указаны домены для проекта '.$this->object->id);
            $this->logator->write();
        }

        $domainsExploded = explode(',',str_replace(' ','',$domainsText));

        $url = $domainsExploded[0].'/gateway';

        //Если если есть файл
        if(isset($this->params['attach']))
        {
            $this->logator->setType('success');
            $this->logator->setText('Прикрепляем файл');
            $this->logator->write();
            $response = Http::attach('attachment',file_get_contents($this->params['attach']),$this->params['attach_name'])->post($url,$this->params['data']);
        }
        //Без файла
        else
        {
            $response = Http::post($url,$this->params['data']);
        }

        // Проверяем статус ответа
        if($response->status() == 200)
        {
            // Если статус ответа 200, значит запрос успешно отправлен
            $this->logator->setType('success');
            $this->logator->setText('Запрос успешно отправлен. Для проекта '.$this->object->id. ' данные: '.json_encode($this->params));
            $this->logator->write();
        }
        else
        {
            // В противном случае возвращаем ошибку
            $this->logator->setType('danger');
            $this->logator->setText('Запрос провален. Для проекта '.$this->object->id. ' данные: '.json_encode($this->params));
            $this->logator->write();
            dd('getResult',$response->getBody(),$response);
            $this->logator->setType('danger');
            $this->logator->setText('Запрос провален. Для проекта '.$this->object->id. ' данные: '.json_encode($this->params));
            
        }
	}
}