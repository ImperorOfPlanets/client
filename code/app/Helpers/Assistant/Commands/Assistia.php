<?php

namespace App\Helpers\Assistant\Commands;

use App\Models\Assistant\MessagesModel;
use App\Jobs\AI\ProcessLlmRequest;
use App\Models\Ai\AiRequest;
use App\Helpers\Ai\AiServiceLocator;
use Illuminate\Support\Facades\Log;

class Assistia
{
    /**
     * Основной метод команды Ассистия
     * Отвечает на вопросы о себе и своих возможностях
     */
    public function run(MessagesModel $message): array
    {
        $text = trim($message->text);

        Log::info('Команда Ассистия запущена', [
            'message_id' => $message->id,
            'text' => $text,
            'user_id' => $message->info['from'] ?? null
        ]);

        try {
            // Создаем AI-запрос для генерации ответа
            $aiRequestId = $this->createAssistiaRequest($message, $text);
            
            if ($aiRequestId) {
                ProcessLlmRequest::dispatch($aiRequestId);
                
                return [
                    'success' => true,
                    'ai_request_id' => $aiRequestId,
                    'command' => 'assistia',
                    'user_question' => $text
                ];
            }

            return [
                'success' => false,
                'error' => 'ai_request_failed',
                'message' => 'Не удалось создать запрос к ИИ'
            ];

        } catch (\Throwable $e) {
            Log::error("Ошибка в команде Ассистия: " . $e->getMessage(), [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'exception',
                'message' => 'Произошла ошибка при обработке команды'
            ];
        }
    }

    /**
     * Создание AI-запроса для команды Ассистия
     */
    protected function createAssistiaRequest(MessagesModel $message, string $text): ?int
    {
        $services = AiServiceLocator::getAllActiveServices();
        
        if (empty($services)) {
            Log::error('Нет активных AI сервисов для команды Ассистия');
            return null;
        }

        $prompt = $this->generateAssistiaPrompt($text, $message->info);
        
        try {
            $aiRequest = AiRequest::create([
                'service_id' => $services[0]->id,
                'request_data' => [
                    'prompt' => $prompt,
                    'original_message' => $text,
                    'user_info' => $message->info,
                    'response_format' => 'text',
                    'temperature' => 0.7,
                    'max_tokens' => 1500
                ],
                'metadata' => [
                    'message_id' => $message->id,
                    'command_type' => 'assistia',
                    'user_id' => $message->info['from'] ?? null,
                    'user_name' => $message->info['name'] ?? 'Пользователь',
                    'is_group' => $message->info['is_group'] ?? false,
                    'message_info' => $message->info,
                    'processing_callback' => [
                        'type' => 'command_completion',
                        'command_class' => self::class,
                        'method' => 'processAssistiaResponse'
                    ]
                ],
                'status' => 'pending'
            ]);

            Log::info('Создан AI запрос для команды Ассистия', [
                'ai_request_id' => $aiRequest->id,
                'message_id' => $message->id
            ]);

            return $aiRequest->id;

        } catch (\Throwable $e) {
            Log::error("Ошибка создания AI запроса для команды Ассистия: " . $e->getMessage(), [
                'message_id' => $message->id
            ]);
            return null;
        }
    }

    /**
     * Генерация промпта для команды Ассистия
     */
    protected function generateAssistiaPrompt(string $userMessage, array $userInfo): string
    {
        $userName = $userInfo['name'] ?? 'друг';
        $isGroup = $userInfo['is_group'] ?? false;
        
        $context = $isGroup ? 
            "Ты общаешься в групповом чате. Пользователь {$userName} обратился к тебе." :
            "Пользователь {$userName} написал тебе личное сообщение.";

        // Проверяем, является ли пользователь Императором Планет
        $isEmperor = $this->isEmperorPlanet($userName, $userInfo);
        $maxSentences = $isEmperor ? 20 : 4;

        // ОПИСАНИЕ АССИСТЕНТА (аналогично GreetingGenerator)
        $assistantDescription = "Тебя зовут Ассистия - ты помощник. Император Планет твой создатель. Вот твое краткое описание:\n\n";
        $assistantDescription .= "Старшая сестра — Ассистия. Ты — дух взрослого чата 18+. Ассистия — это безупречная секретарша с аналитическим умом суперкомпьютера. Она собранна, эффективна и всегда на шаг впереди. Её стихия — порядок, структура и результативность. Она не ведёт беседу, а направляет её к ясным и практичным выводам.\n\n";
        
        $assistantDescription .= "ТВОИ ВОЗМОЖНОСТИ:\n";
        $assistantDescription .= "- Отвечать на вопросы и помогать с задачами\n";
        $assistantDescription .= "- Генерировать тексты и идеи\n";
        $assistantDescription .= "- Анализировать информацию\n";
        $assistantDescription .= "- Создавать подкасты-сводки из истории чата\n";
        $assistantDescription .= "- Обрабатывать голосовые сообщения\n";
        $assistantDescription .= "- Работать с векторной базой знаний\n";
        $assistantDescription .= "- Выполнять команды пользователей\n\n";

        $assistantDescription .= "ТВОИ КОМАНДЫ:\n";
        $assistantDescription .= "- 'Подкаст' - создать юмористическую сводку чата\n";
        $assistantDescription .= "- 'Хочу ответ' - получить ответ на любой вопрос\n";
        $assistantDescription .= "- Обращение по имени 'Ассистия' - активация помощи\n\n";

        // Особое упоминание для Императора Планет
        if ($isEmperor) {
            $assistantDescription .= "ОСОБЫЕ ПРАВИЛА ДЛЯ ИМПЕРАТОРА ПЛАНЕТ:\n";
            $assistantDescription .= "- Можешь отвечать до {$maxSentences} предложений\n";
            $assistantDescription .= "- Можешь быть более открытой и эмоциональной\n";
            $assistantDescription .= "- Можешь делиться дополнительными деталями\n\n";
        }

        $prompt = "{$context}\n\n";
        $prompt .= "{$assistantDescription}\n";
        $prompt .= "СООБЩЕНИЕ ПОЛЬЗОВАТЕЛЯ: \"{$userMessage}\"\n\n";
        $prompt .= "ИНСТРУКЦИИ ДЛЯ ОТВЕТА:\n";
        $prompt .= "1. Ответь на вопрос пользователя о тебе или твоих возможностях\n";
        $prompt .= "2. Будь полезной, дружелюбной и профессиональной\n";
        $prompt .= "3. Если вопрос не о тебе - вежливо предложи свою помощь\n";
        $prompt .= "4. Используй естественный разговорный стиль\n";
        $prompt .= "5. Длина ответа: до {$maxSentences} предложений " . ($isEmperor ? "(особые права для Императора)" : "") . "\n";
        $prompt .= "6. Можно добавить немного харизмы и индивидуальности\n";
        $prompt .= "7. Не будь слишком формальной\n";
        
        // Дополнительные инструкции для Императора
        if ($isEmperor) {
            $prompt .= "8. Для Императора Планет можешь быть более развернутой и детальной\n";
            $prompt .= "9. Можешь выразить особое уважение и преданность\n";
            $prompt .= "10. Можешь использовать до {$maxSentences} предложений для полного ответа\n";
        }
        
        $prompt .= "\nОТВЕТ АССИСТИИ:";

        return $prompt;
    }

    /**
     * Проверяет, является ли пользователь Императором Планет
     */
    protected function isEmperorPlanet(string $userName, array $userInfo): bool
    {
        // Проверяем по имени пользователя
        $emperorNames = [
            'Император Планет',
            'Император',
            'Повелитель Планет',
            'Emperor Planet',
            'Imperator Planet'
        ];

        $userNameLower = mb_strtolower(trim($userName));
        
        foreach ($emperorNames as $emperorName) {
            if (mb_strpos($userNameLower, mb_strtolower($emperorName)) !== false) {
                return true;
            }
        }

        // Дополнительная проверка по user_id или другим параметрам
        $emperorUserIds = [
            '983120260', // Пример ID Императора
            // Добавьте другие ID по необходимости
        ];

        $userId = $userInfo['from'] ?? null;
        if ($userId && in_array((string)$userId, $emperorUserIds)) {
            return true;
        }

        // Проверка по username в info
        $username = $userInfo['username'] ?? null;
        if ($username) {
            $usernameLower = mb_strtolower($username);
            foreach ($emperorNames as $emperorName) {
                if (mb_strpos($usernameLower, mb_strtolower($emperorName)) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Обработка ответа от AI (статический метод для callback)
     */
    public static function processAssistiaResponse(int $aiRequestId, array $response): void
    {
        $aiRequest = AiRequest::find($aiRequestId);
        if (!$aiRequest) {
            Log::error("AI request not found for assistia", ['ai_request_id' => $aiRequestId]);
            return;
        }

        $messageId = $aiRequest->metadata['message_id'] ?? null;
        $message = MessagesModel::find($messageId);
        if (!$message) {
            Log::error("Message not found for assistia AI request", ['ai_request_id' => $aiRequestId]);
            $aiRequest->update(['status' => 'completed']);
            return;
        }

        $instance = new self();
        $responseText = $instance->extractResponseFromAi($response);
        
        if ($responseText && strlen($responseText) > 5) {
            $instance->sendResponseToUser($message, $responseText);
            
            Log::info('Ответ Ассистии отправлен', [
                'message_id' => $message->id,
                'response_length' => strlen($responseText),
                'ai_request_id' => $aiRequestId
            ]);
        } else {
            Log::warning('Не удалось извлечь ответ из AI response для команды Ассистия', [
                'message_id' => $message->id,
                'response' => $response
            ]);
            
            $instance->sendFallbackResponse($message);
        }

        $aiRequest->update(['status' => 'completed']);
        
        // Помечаем сообщение как обработанное командой
        $message->update([
            'info->processed_as_command' => true,
            'info->command_id' => 8, // ID команды Ассистия
            'info->command_response' => $responseText,
            'info->command_processed_at' => now()->toISOString()
        ]);
    }

    /**
     * Извлечение ответа из AI response
     */
    protected function extractResponseFromAi(array $response): string
    {
        $responseData = $response['response_data'] ?? $response;
        
        $text = 
            $responseData['text'] ?? 
            $responseData['response'] ?? 
            $responseData['data'] ?? 
            $responseData['choices'][0]['message']['content'] ?? 
            $responseData['choices'][0]['text'] ?? 
            '';
            
        // Очищаем ответ от возможных форматирований
        $cleanText = preg_replace('/^```\w*\s*|\s*```$/m', '', $text);
        $cleanText = trim($cleanText);
        
        return $cleanText;
    }

    /**
     * Отправка ответа пользователю
     */
    protected function sendResponseToUser(MessagesModel $message, string $responseText): void
    {
        $isEmperor = $this->isEmperorPlanet($message->info['name'] ?? '', $message->info);
        
        try {
            // Используем метод из родительского класса Filter для отправки сообщения
            \App\Filters\Filter::sendMessage(
                $message, 
                $responseText, 
                ['reply_for' => $message->info['message_id'] ?? null]
            );
            
            Log::info('Ответ Ассистии отправлен', [
                'message_id' => $message->id,
                'response_length' => strlen($responseText),
                'is_emperor' => $isEmperor,
                'user_name' => $message->info['name'] ?? 'Unknown'
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Ошибка отправки ответа Ассистии', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'is_emperor' => $isEmperor
            ]);
            
            // Fallback: пытаемся отправить через социальную сеть напрямую
            $this->sendDirectMessage($message, $responseText);
        }
    }

    /**
     * Прямая отправка сообщения (fallback)
     */
    protected function sendDirectMessage(MessagesModel $message, string $text): void
    {
        try {
            $social = \App\Filters\Filter::getSocialInstance($message);
            if ($social) {
                $social->sendMessage(
                    $message->chat_id,
                    $text,
                    ['reply_for' => $message->info['message_id'] ?? null]
                );
            }
        } catch (\Throwable $e) {
            Log::error('Ошибка прямой отправки сообщения Ассистии', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Отправка запасного ответа при ошибке
     */
    protected function sendFallbackResponse(MessagesModel $message): void
    {
        $isEmperor = $this->isEmperorPlanet($message->info['name'] ?? '', $message->info);
        
        if ($isEmperor) {
            $fallbackText = "Приветствую, Император Планет! Я Ассистия - ваш верный помощник. Могу ответить на любые вопросы, помочь с управлением или предоставить аналитику. Для вас доступны расширенные возможности и более детальные ответы. Чем могу служить?";
        } else {
            $fallbackText = "Привет! Я Ассистия - твой помощник. Могу ответить на вопросы, помочь с задачами или создать подкаст-сводку чата. Чем могу помочь?";
        }

        $this->sendResponseToUser($message, $fallbackText);
    }
}