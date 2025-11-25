<?php

namespace App\Jobs\Assistant\Messages;

use App\Models\Assistant\MessagesModel;
use App\Models\Socials\SocialsModel;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;

use App\Jobs\Assistant\Messages\Download;
use App\Jobs\Assistant\Messages\SendVoice;
use App\Jobs\Assistant\Messages\LanguageDefinition;
use App\Jobs\Assistant\Messages\Recognize;
use App\Jobs\Assistant\Messages\Translate;
use App\Jobs\Assistant\Messages\AiRequest;

class ProcessMessageJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $message;

    public function __construct(MessagesModel $message)
    {
        $this->message = $message;
    }

    public function handle()
    {
        $message = $this->message;
        $info = $message->info;

        // Инициализация социальной сети
        $social = SocialsModel::find($message->soc);
        $p35 = $social->propertyById(35)->pivot->value ?? null;
        if (!$p35) {
            Log::error("Ошибка инициализации социальной сети для сообщения: " . $message->id);
            return;
        }

        $initializedSocial = new $p35;

        // Проверка на группу
        $isGroup = $initializedSocial->isGroup($message);

        //Подготовка сообщения
        $messageForProcess = trim($message->text);
        $$messageForProcess = mb_strtolower($messageForProcess);

        // Проверка длины сообщения
        if (iconv_strlen($messageForProcess) > 250) {
            $info['errors'] = ['Запрос более 250 символов'];
            $message->status = 1;
            $message->info = $info;
            $message->save();

            $initializedSocial->sendMessage($message->chat_id, "Ваш запрос более 250 символов", $info);
            return;
        }

        // Обработка текстового сообщения
        if (!$isGroup) {
            Bus::chain([
                new AiRequest(['message_id' => $message->id]),
            ])->onQueue('default')->dispatch();
        }

        $message->status = 1;
        $message->info = $info;
        $message->save();
    }
}
