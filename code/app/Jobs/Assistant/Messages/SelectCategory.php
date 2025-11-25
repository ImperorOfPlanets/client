<?php

namespace App\Jobs\Assistant\Messages;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Http\File;
use Illuminate\Support\Facades\Http;

use App\Models\Assistant\MessagesModel;
use App\Models\Socials\SocialsModel;
use OpenAI\Enums\Moderations\Category;

use LLPhant\OllamaConfig;
use LLPhant\Chat\OllamaChat;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGenerator;
use LLPhant\VectorStores\MemoryVectorStore;

class SelectCategory implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public $params = null;
	public $message = null;
    public $initializedSocials = [];
    public $chat;
    public $config;

	public function __construct($params = null)
	{
		$this->params = $params;
    }

	public function handle()
	{
        // Получаем сообщение из базы данных
		$this->message = MessagesModel::find($this->params['message_id']);
		$info = $this->message->info;
        $text = $this->message->text;

        //Надо сделать получение доступных категорий
        $categories = [
            "Товары","Услуги","События","Прочее"
        ];
        
        //Проверяем инициализацию социальной сети
        if(!isset($this->initializedSocials[$this->message->soc]))
        {
            //Получаем объект социальной сети
            $social = SocialsModel::find($this->message->soc);

            //Получаем путь до класса социальной сети
            $p35 = $social->propertyById(35)->pivot->value ?? null;
            if(!is_null($p35)){$this->initializedSocials[$this->message->soc] = new $p35;}else{dd('Ошибка');}
        }

        $answer = "Произвожу поиск подходящей категории. Доступные категории: 1.Товары 2.Услуги 3.События 4.Прочее";

        $result = $this->initializedSocials[$this->message->soc]->sendMessage($this->message->chat_id,$answer,$info);

        $resultAfterSocialProcess = $this->initializedSocials[$this->message->soc]->processResultSendMessage($result);
        //var_dump($resultAfterSocialProcess);
        echo "Запрос пользователя:$text\n";
        // Создаем инструкцию для Llama 2
        $instruction = "
        Вы - ассистент, который отвечает одним словом из списка категорий. Список категорий: [" . implode(', ', $categories) . "].
        Ваш ответ должен быть одним словом из этого списка. Не добавляйте никаких дополнительных слов или фраз.
        ";

        // Формируем промпт для Llama 2
        $prompt = "[INST]
        ".$instruction."
        Пожалуйста, найдите наиболее близкую категорию к запросу \"$text\" из списка выше. Ответ дайте одним словом из списка.
        ";
        //dd(777);
        $this->setSettings();
        try {
            // Отправляем промт и получаем ответ
            $response = $this->chat->generateText($prompt); // Используем метод prompt()
            //var_dump($response)
            $response = "Определенная категория: ".preg_replace('/[^\p{L}\p{N}\s]/ui', '', $response);
            //Проверяем если поддерживает реждактирование сообщений
            if($this->initializedSocials[$this->message->soc]->checkEditMessage())
            {
                echo "Редактирование сообщения - поддерживается\n";
                echo "Текст который должен появиться: $response\n";
                var_dump($resultAfterSocialProcess['message_id']);
                $result = $this->initializedSocials[$this->message->soc]->editMessage($this->message->chat_id,$resultAfterSocialProcess['message_id'],$response,$info);
            }
            else
            {
                echo "Редактирование сообщения - Не поддерживается\n";
            }
            //Редактируем сообщение указывая какая категория
            //return response()->json($result);
        } catch (\Exception $e) {
            var_dump(json_encode($e->getMessage()));
            //return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function setSettings()
    {
        // Настройка Ollama
        $this->config = new OllamaConfig();
        $this->config->model = 'llama3.2';
        $this->config->url = 'http://192.168.0.170:11434/api/';
        $this->chat = new OllamaChat($this->config);
    }
}