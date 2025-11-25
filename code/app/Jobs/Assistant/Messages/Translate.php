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

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

use App\Jobs\Assistant\Messages\Delete;

class Translate implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public $params = null;
	public $soc = null;
	public $message = null;
	public $logator;

    public $initializedSocials = [];

	public function __construct($params = null)
	{
		$this->params = $params;
    }

	public function handle()
	{
        //Получаем сообщение которое отреагировало на команду
		$this->message = MessagesModel::find($this->params['message_id']);

        $info = $this->message->info;
        //Проверяем инициализацию социальной сети
        if(!isset($this->initializedSocials[$this->message->soc]))
        {
            //Получаем объект социальной сети
            $social = SocialsModel::find($this->message->soc);

            //Получаем путь до класса социальной сети
            $p35 = $social->propertyById(35)->pivot->value ?? null;
            if(!is_null($p35)){$this->initializedSocials[$this->message->soc] = new $p35;}else{dd('Ошибка');}
        }

        //Ответ
        $answer = "Отправляю сообщение на перевод";
        //Получаем результат отправки
        $result = $this->initializedSocials[$this->message->soc]->sendMessage($this->message->chat_id,$answer,$info);

        //Обратываем
        $resultAfterSocialProcess = $this->initializedSocials[$this->message->soc]->processResultSendMessage($result);

        //Добавляем обновление в общий список событий
        $resUpdate = $this->initializedSocials[$this->message->soc]->setUpdate([
            'chat_id'=>$this->message->chat_id,
            'message_id'=>$resultAfterSocialProcess['message_id'],
            'text'=>$answer,
            'event'=>'new_message'
        ]);

        //echo "Удаляем на которое отреагировало";
        Delete::dispatch(['message_id' => $this->message->id]);

        //var_dump($resultAfterSocialProcess);
        //Фиксируем какое сообщение нужно будет редактировать после прихода результата
        //Log::info(json_encode($resultAfterSocialProcess));
        $info['for_edit'] = $resultAfterSocialProcess['message_id'];
        $this->message->info = $info;
        $this->message->save();

        //Получаем сообщение для перевода
        $messageForTranslate = MessagesModel::where('info->message_id', $this->message->info['reply_to'])
            ->orderBy('id', 'desc')
            ->first();

        if($messageForTranslate)
        {
            $data = [
				'text' => $messageForTranslate->text,
                //ID сообщения которое надо перевести
				'message_id'=> $this->params['message_id'],
                //ID сообщения с уведомлением о переводе
                'message_answer_id'=>$resUpdate['message_id'],
				'callbackUrl' => env('APP_URL')
			];
            var_dump($data);
            $response = Http::post('voice.myidon.site/translate',$data);
            var_dump($response->body());
        }
        else
        {

        }
	}
}