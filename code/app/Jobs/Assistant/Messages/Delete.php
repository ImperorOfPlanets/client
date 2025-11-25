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

class Delete implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public $params = null;
	public $soc = null;
	public $logator;

	public function __construct($params = null)
	{
		$this->params = $params;
    }

	public function handle()
	{
		$message = MessagesModel::find($this->params['message_id']);
		$this->logator = new Logator;
		$this->logator->setType('success');
		$this->logator->setText("Удаляю сообщение");
		$this->logator->write();

		//Получаем объект социальной сети
		$social = SocialsModel::find($message->soc);

		//Получаем путь до класса социальной сети
		$p35 = $social->propertyById(35)->pivot->value ?? null;
		if(!is_null($p35)){$initializedSocials[$message->soc] = new $p35;}else{dd('Ошибка');}

		if($initializedSocials[$message->soc]->checkDeleteMessage())
		{
			$resultDelete = $initializedSocials[$message->soc]->deleteMessage($message->chat_id,$message->info['message_id']);
			if($resultDelete)
			{
				$info = $message->info;
				$info['deleted'] = true;
				$message->info = $info;
				$message->status = 1;
				$message->save();
			}
		}
	}
}