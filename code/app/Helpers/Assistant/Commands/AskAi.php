<?php

namespace App\Helpers\Assistant\Commands;

use App\Models\Assistant\MessagesModel;
use App\Filters\Commands;
use Illuminate\Support\Facades\Log;

class AskAi
{
    /**
     * Основной метод команды.
     * Получает сообщение и возвращает результат.
     */
    public function run(MessagesModel $message): array
    {
        $text = trim($message->text);

        // Обрезаем ключевое слово "Хочу ответ" и пробелы
        $question = preg_replace('/^хочу ответ\s+/iu', '', $text);

        if (empty($question)) {
            return [
                'error' => 'empty_question',
                'message' => 'Пожалуйста, напишите вопрос после команды "Хочу ответ!"'
            ];
        }

        try {
            // Создаём AI-запрос через Commands фильтр
            $commandsFilter = new Commands();
            $aiRequestId = $commandsFilter->createAiRequest($message, $question);

            if (!$aiRequestId) {
                return [
                    'error' => 'ai_request_failed',
                    'message' => 'Не удалось создать запрос к ИИ'
                ];
            }

            // Возвращаем ID AI-запроса и вопрос
            return [
                'ai_request_id' => $aiRequestId,
                'question' => $question
            ];
        } catch (\Throwable $e) {
            Log::error("AskAiCommand: ошибка при создании AI-запроса", [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);

            return [
                'error' => 'exception',
                'message' => 'Произошла ошибка при обработке команды'
            ];
        }
    }
}
