<?php

namespace App\Filters;

use App\Models\Assistant\MessagesModel;
use App\Jobs\AI\ProcessLlmRequest;
use App\Models\Ai\AiRequest;
use App\Helpers\Ai\AiServiceLocator;
use Illuminate\Support\Facades\Log;
use App\Jobs\Assistant\Messages\SendTextToSpeech;

class VoiceAnswerGenerator extends Filter
{
    public function handle(MessagesModel $message): array
    {
        $text = trim($message->text);
        
        Log::info('Обработка генератора голосового ответа', [
            'message_id' => $message->id,
            'text' => $text,
            'filter_id' => $this->getFilterId()
        ]);

        // Пропускаем, если это команда или служебное сообщение
        if ($this->shouldSkipProcessing($message)) {
            Log::debug('Пропуск генерации голосового ответа', [
                'message_id' => $message->id,
                'reason' => 'should_skip'
            ]);
            return $this->createResponse(true, self::DECISION_CONTINUE);
        }

        // Проверяем, не был ли уже сгенерирован голосовой ответ
        if ($this->isVoiceAnswerAlreadyGenerated($message)) {
            Log::debug('Голосовой ответ уже был сгенерирован ранее', [
                'message_id' => $message->id
            ]);
            return $this->createResponse(true, self::DECISION_CONTINUE);
        }

        // Создаем AI-запрос для генерации ответа
        $aiRequestId = $this->createVoiceAnswerRequest($message, $text);
        
        if ($aiRequestId) {
            ProcessLlmRequest::dispatch($aiRequestId);
            
            return [
                'status' => self::STATUS_PENDING,
                'ai_request_id' => $aiRequestId,
                'decision' => self::DECISION_WAIT_EXTERNAL,
                'filter_id' => $this->getFilterId(),
                'reason' => 'voice_answer_generation'
            ];
        }
        
        return $this->createResponse(true, self::DECISION_CONTINUE);
    }

    /**
     * Обработка сохраненных данных (обязательный метод)
    */
    public function processSavedData(MessagesModel $message, array $result): array
    {
        Log::info('Обработка сохраненных данных в фильтре VoiceAnswerGenerator', [
            'message_id' => $message->id,
            'result_keys' => array_keys($result),
            'filter_id' => $this->getFilterId()
        ]);

        // Проверяем, не было ли уже отправлено приветствие через processVoiceAnswerResponse
        $info = $message->info ?? [];
        if ($info['voice_answer_generated'] ?? false) {
            Log::info('Голосовой ответ уже был сгенерирован ранее - пропускаем отправку', [
                'message_id' => $message->id
            ]);
            return $this->createResponse(false, self::DECISION_SKIP, self::STATUS_COMPLETED, [
                'reason' => 'voice_answer_already_generated'
            ]);
        }

        try {
            $answerText = $this->extractAnswerFromAiResponse($result);
            
            if (!empty($answerText) && strlen($answerText) > 5) {
                $this->synthesizeVoiceAnswer($message, $answerText);

                $message->update([
                    'info->voice_answer_generated' => true,
                    'info->voice_answer_text' => $answerText,
                    'info->voice_answer_generated_at' => now()->toISOString()
                ]);

                Log::info('Голосовой ответ успешно обработан из сохраненных данных', [
                    'message_id' => $message->id,
                    'answer_length' => strlen($answerText)
                ]);

                return $this->createResponse(false, self::DECISION_SKIP, self::STATUS_COMPLETED, [
                    'reason' => 'voice_answer_from_saved_data',
                    'answer_length' => strlen($answerText)
                ]);
            } else {
                Log::warning('Извлеченный ответ слишком короткий или пустой', [
                    'message_id' => $message->id,
                    'answer_length' => strlen($answerText ?? '')
                ]);
                
                $this->sendFallbackAnswer($message);
                
                return $this->createResponse(false, self::DECISION_SKIP, self::STATUS_COMPLETED, [
                    'reason' => 'fallback_answer_sent'
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('Ошибка обработки сохраненных данных VoiceAnswerGenerator', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
            
            $this->sendFallbackAnswer($message);
            return $this->createResponse(false, self::DECISION_SKIP, self::STATUS_COMPLETED);
        }
    }

    /**
     * Проверяет, нужно ли пропускать обработку
    */
    protected function shouldSkipProcessing(MessagesModel $message): bool
    {
        $info = $message->info ?? [];
        
        // Пропускаем если:
        // 1. Уже обработано как команда
        if ($info['processed_as_command'] ?? false) {
            return true;
        }
        
        // 2. Уже сгенерировано приветствие
        if ($info['greeting_generated'] ?? false) {
            return true;
        }
        
        // 3. Сообщение слишком короткое
        if (mb_strlen(trim($message->text)) < 3) {
            return true;
        }
        
        // 4. Служебные команды
        $serviceKeywords = ['/', '!', '#', 'команда', 'настройка'];
        $textLower = mb_strtolower($message->text);
        foreach ($serviceKeywords as $keyword) {
            if (mb_strpos($textLower, $keyword) === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Проверяет, не был ли уже сгенерирован голосовой ответ
    */
    protected function isVoiceAnswerAlreadyGenerated(MessagesModel $message): bool
    {
        $info = $message->info ?? [];
        return $info['voice_answer_generated'] ?? false;
    }

    /**
     * Создание AI-запроса для генерации голосового ответа
    */
    protected function createVoiceAnswerRequest(MessagesModel $message, string $text): ?int
    {
        $services = AiServiceLocator::getAllActiveServices();
        
        if (empty($services)) {
            Log::error('Нет активных AI сервисов для генерации голосового ответа');
            return null;
        }

        $prompt = $this->generateVoiceAnswerPrompt($text, $message->info);
        
        try {
            $aiRequest = AiRequest::create([
                'service_id' => $services[0]->id,
                'request_data' => [
                    'prompt' => $prompt,
                    'original_message' => $text,
                    'user_info' => $message->info,
                    'response_format' => 'text',
                    'temperature' => 0.7,
                    'max_tokens' => 300
                ],
                'metadata' => [
                    'message_id' => $message->id,
                    'filter_id' => $this->getFilterId(),
                    'user_id' => $message->info['from'] ?? null,
                    'user_name' => $message->info['name'] ?? 'Пользователь',
                    'is_group' => $message->info['is_group'] ?? false,
                    'message_info' => $message->info,
                    'processing_callback' => [
                        'type' => 'filter_completion',
                        'filter_class' => self::class,
                        'method' => 'processVoiceAnswerResponse'
                    ]
                ],
                'status' => 'pending'
            ]);

            Log::info('Создан AI запрос для генерации голосового ответа', [
                'ai_request_id' => $aiRequest->id,
                'message_id' => $message->id
            ]);

            return $aiRequest->id;

        } catch (\Throwable $e) {
            Log::error("Ошибка создания AI запроса для голосового ответа: " . $e->getMessage(), [
                'message_id' => $message->id,
                'filter_id' => $this->getFilterId()
            ]);
            return null;
        }
    }

    /**
     * Генерация промпта для голосового ответа
    */
    protected function generateVoiceAnswerPrompt(string $userMessage, array $userInfo): string
    {
        $userName = $userInfo['name'] ?? 'друг';
        $isGroup = $userInfo['is_group'] ?? false;
        
        $context = $isGroup ? 
            "Ты общаешься в групповом чате. Пользователь {$userName} написал сообщение." :
            "Пользователь {$userName} написал тебе личное сообщение.";
        
        $prompt = "{$context}\n\n";
        $prompt .= "СООБЩЕНИЕ ПОЛЬЗОВАТЕЛЯ: \"{$userMessage}\"\n\n";
        $prompt .= "Сгенерируй естественный, разговорный ответ. Ответ должен быть:\n";
        $prompt .= "- Кратким (1-3 предложения)\n";
        $prompt .= "- Естественным, как в живой беседе\n";
        $prompt .= "- Уместным по контексту\n";
        $prompt .= "- Дружелюбным и поддерживающим\n";
        $prompt .= "- Подходящим для озвучки голосом\n\n";
        $prompt .= "Избегай:\n";
        $prompt .= "- Слишком длинных сложных фраз\n";
        $prompt .= "- Формального или официального тона\n";
        $prompt .= "- Специальных символов или эмодзи\n\n";
        $prompt .= "ОТВЕТ:";

        return $prompt;
    }

    /**
     * Обработка ответа от AI
    */
    public static function processVoiceAnswerResponse(int $aiRequestId, array $response): void
    {
        $aiRequest = AiRequest::find($aiRequestId);
        if (!$aiRequest) {
            Log::error("AI request not found for voice answer", ['ai_request_id' => $aiRequestId]);
            return;
        }

        $messageId = $aiRequest->metadata['message_id'] ?? null;
        $message = MessagesModel::find($messageId);
        if (!$message) {
            Log::error("Message not found for voice answer AI request", ['ai_request_id' => $aiRequestId]);
            $aiRequest->update(['status' => 'completed']);
            return;
        }

        $instance = new self();
        $answerText = $instance->extractAnswerFromAiResponse($response);
        
        if ($answerText && strlen($answerText) > 5) {
            $instance->synthesizeVoiceAnswer($message, $answerText);
            
            Log::info('Голосовой ответ сгенерирован', [
                'message_id' => $message->id,
                'answer_length' => strlen($answerText),
                'ai_request_id' => $aiRequestId
            ]);
        } else {
            Log::warning('Не удалось извлечь ответ из AI response', [
                'message_id' => $message->id,
                'response' => $response
            ]);
            
            $instance->sendFallbackAnswer($message);
        }

        $aiRequest->update(['status' => 'completed']);
        
        // Помечаем сообщение как обработанное с голосовым ответом
        $message->update([
            'info->voice_answer_generated' => true,
            'info->voice_answer_text' => $answerText,
            'info->voice_answer_generated_at' => now()->toISOString()
        ]);
    }

    /**
     * Извлечение ответа из AI response
    */
    protected function extractAnswerFromAiResponse(array $response): string
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
     * Синтез голосового ответа через Job
    */
    protected function synthesizeVoiceAnswer(MessagesModel $message, string $answerText): void
    {
        try {
            SendTextToSpeech::dispatch([
                'message_id' => $message->id,
                'text' => $answerText,
                'voice_params' => [
                    'speed' => 1.0,
                    'pitch' => 1.0,
                    'model' => 'ru_vits'
                ]
            ]);
            
            Log::info('Job для синтеза голосового ответа запущен', [
                'message_id' => $message->id,
                'answer_length' => strlen($answerText)
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Ошибка запуска Job для синтеза речи', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
            
            // При ошибке отправляем текстовый ответ
            $this->sendTextFallback($message, $answerText);
        }
    }

    /**
     * Отправка запасного ответа
    */
    protected function sendFallbackAnswer(MessagesModel $message): void
    {
        try {
            $fallbackAnswer = "Извините, я вас понял, но не смог сгенерировать ответ. Попробуйте задать вопрос по-другому.";
            
            self::sendMessage($message, $fallbackAnswer, [
                'reply_for' => $message->info['message_id'] ?? null
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Ошибка отправки запасного ответа', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Отправка текстового ответа при ошибке синтеза
    */
    protected function sendTextFallback(MessagesModel $message, string $answerText): void
    {
        try {
            self::sendMessage($message, "🎤 [Голосовой ответ]: {$answerText}", [
                'reply_for' => $message->info['message_id'] ?? null
            ]);
            
            Log::info('Текстовый ответ отправлен (fallback)', [
                'message_id' => $message->id
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Ошибка отправки текстового ответа (fallback)', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}