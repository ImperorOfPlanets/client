<?php

namespace App\Jobs\Assistant\Messages;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\Assistant\MessagesModel;
use App\Models\Socials\SocialsModel;

use App\Helpers\Logs\Logs as Logator;

class Download implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public $params = null;
	public $soc = null;
	public $message = null;
	public $logator;

	public function __construct($params = null)
	{
		$this->params = $params;
    }

	public function handle()
	{
		$this->message = MessagesModel::find($this->params['message_id']);
		$this->logator = new Logator;
		$this->logator->setType('success');
		$this->logator->setText("Скачиваю голосовое сообщение");
		$this->logator->write();

		//Получаем объект социальной сети
		$social = SocialsModel::find($this->message->soc);

		//Получаем путь до класса социальной сети
		$p35 = $social->propertyById(35)->pivot->value ?? null;
		if(!is_null($p35)){$initializedSocials[$this->message->soc] = new $p35;}else{dd('Ошибка');}

		$result = $initializedSocials[$this->message->soc]->getVoiceMessage($this->message->info);
	}
}