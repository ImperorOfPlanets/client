<?php
namespace App\Jobs\Assistant\Messages;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\Assistant\MessagesModel;
use App\Models\Settings\Keywords\KeywordsModel;

use LLPhant\OllamaConfig;
use LLPhant\Chat\OllamaChat;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGenerator;
use LLPhant\VectorStores\MemoryVectorStore;

class Search implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $params = null;
    public $message = null;
    public $chat;
    public $config;

    public function __construct($params = null)
    {
        $this->params = $params;
    }

    public function setSettings()
    {
        // Настройка Ollama
        $this->config = new OllamaConfig();
        $this->config->model = 'llama2';
        $this->chat = new OllamaChat($this->config);
    }

    public function handle()
    {
        $this->setSettings();

        // Генератор векторов
        $embeddingGenerator = new OllamaEmbeddingGenerator($this->config);

        // Получаем сообщение из базы данных
        $this->message = MessagesModel::find($this->params['message_id']);
        $text = $this->message->text;

        // Получаем список всех ключевых слов
        $keywords = KeywordsModel::all()->pluck('keyword')->toArray();

        // Создаем строку для промта
        $keywordString = implode(", ", $keywords);

        // Промт для LLama2
        $prompt = "Пожалуйста, найдите наиболее близкие ключевые слова к запросу \"$text\" из списка: $keywordString. Выведите список из 3-х ключевых слов в формате JSON {\"first_keyword\": \"...\", \"second_keyword\": \"...\", \"third_keyword\": \"...\"}. Если нет ключевых слов ближе 50%, то просто выдай ответ 'Keyword not Found'. Только JSON без лишних слов.";

        try {
            // Отправляем промт и получаем ответ
            $response = $this->chat->generateText($prompt); // Используем метод prompt()
            $result = $this->parseResponse($response);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function parseResponse($response)
    {
        // Попробуем декодировать ответ в JSON
        $jsonResponse = json_decode($response['message']['content'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $jsonResponse;
        }

        return ['found' => false, 'reason' => 'Invalid JSON format'];
    }
}