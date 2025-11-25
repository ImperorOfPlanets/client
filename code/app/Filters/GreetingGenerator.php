<?php

namespace App\Filters;

use App\Models\Assistant\MessagesModel;
use App\Jobs\AI\ProcessLlmRequest;
use App\Models\Ai\AiRequest;
use App\Helpers\Ai\AiServiceLocator;
use Illuminate\Support\Facades\Log;
use App\Jobs\Assistant\Messages\SendTextToSpeech;

class GreetingGenerator extends Filter
{
    const REQUEST_TYPE_CHAT = 'chat';
    const REQUEST_TYPE_COMPLETION = 'completion';
    const REQUEST_TYPE_DIRECT = 'direct';

    /**
     * Очередь сервисов и моделей по приоритету
     */
    protected function getServiceQueue(): array
    {
        return [
            [
                'service_id' => 5, // PolzaAI
                'models' => [
                    'deepseek/deepseek-chat-v3-0324',
                    'openai/gpt-4o-mini',
                    'anthropic/claude-3-haiku',
                ],
                'name' => 'PolzaAI',
                'request_type' => self::REQUEST_TYPE_CHAT,
                'params' => [
                    'temperature' => 0.8,
                    'max_tokens' => 150
                ]
            ],
        ];
    }

    /**
     * Проверяет, является ли текст URL
     */
    protected function isUrl(string $text): bool
    {
        $urlPatterns = [
            '/https?:\/\/[^\s]+/i',
            '/www\.[^\s]+/i',
            '/[a-z0-9-]+\.[a-z]{2,}\/[^\s]*/i',
            '/[a-z0-9-]+\.[a-z]{2,}\.[a-z]{2,}[^\s]*/i'
        ];
        
        foreach ($urlPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Проверяет, есть ли уже pending AI запрос для этого фильтра
     */
    protected function hasPendingAiRequest(MessagesModel $message): bool
    {
        $info = $message->info ?? [];
        $filterInfo = $info['filters'][$this->getFilterId()] ?? [];
        
        // Проверяем по статусу фильтра
        if (($filterInfo['status'] ?? '') === 'pending') {
            return true;
        }
        
        // Проверяем по external_id (AI request ID)
        if (isset($filterInfo['external_id']) && $filterInfo['external_id']) {
            return true;
        }
        
        return false;
    }

    /**
     * Помечает приветствие как находящееся в процессе обработки
     */
    protected function markGreetingAsProcessing(MessagesModel $message, int $aiRequestId): void
    {
        try {
            $info = $message->info ?? [];
            
            // Обновляем информацию о фильтре
            $info['filters'][$this->getFilterId()] = [
                'status' => 'pending',
                'external_id' => $aiRequestId,
                'processing_started_at' => now()->toISOString()
            ];
            
            $message->info = $info;
            $message->save();
            
            Log::debug('Приветствие помечено как processing', [
                'message_id' => $message->id,
                'ai_request_id' => $aiRequestId
            ]);
        } catch (\Throwable $e) {
            Log::error('Ошибка при отметке приветствия как processing', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function handle(MessagesModel $message): array
    {
        $text = trim($message->text);
        
        Log::info('Обработка генератора приветствия', [
            'message_id' => $message->id,
            'text' => $text,
            'filter_id' => $this->getFilterId()
        ]);

        // Пропускаем URL и ссылки
        if ($this->isUrl($text)) {
            Log::debug('Пропуск обработки URL', [
                'message_id' => $message->id,
                'text' => $text
            ]);
            return $this->createResponse(true, self::DECISION_CONTINUE);
        }

        // Пропускаем слишком короткие сообщения
        if (mb_strlen($text) < 3) {
            Log::debug('Сообщение слишком короткое', [
                'message_id' => $message->id,
                'length' => mb_strlen($text)
            ]);
            return $this->createResponse(true, self::DECISION_CONTINUE);
        }

        if (!$this->isGreetingMessage($text)) {
            Log::debug('Сообщение не является приветственным, продолжаем обработку', [
                'message_id' => $message->id,
                'reason' => 'not_greeting'
            ]);
            return $this->createResponse(true, self::DECISION_CONTINUE);
        }

        // Улучшенная проверка на уже обработанное приветствие
        $info = $message->info ?? [];
        
        // Проверяем, было ли приветствие уже сгенерировано
        if ($info['greeting_generated'] ?? false) {
            Log::debug('Приветствие уже было сгенерировано ранее', [
                'message_id' => $message->id,
                'greeting_generated' => $info['greeting_generated']
            ]);
            return $this->createResponse(true, self::DECISION_CONTINUE);
        }

        // Проверяем, находится ли фильтр уже в обработке
        $filterInfo = $info['filters'][$this->getFilterId()] ?? [];
        if (($filterInfo['status'] ?? '') === 'pending') {
            Log::debug('Приветствие уже находится в процессе обработки', [
                'message_id' => $message->id,
                'filter_status' => 'pending'
            ]);
            return $this->createResponse(true, self::DECISION_CONTINUE);
        }

        // Проверяем, есть ли уже AI запрос в обработке для этого фильтра
        if ($this->hasPendingAiRequest($message)) {
            Log::debug('Уже есть pending AI запрос для этого фильтра', [
                'message_id' => $message->id
            ]);
            return $this->createResponse(true, self::DECISION_CONTINUE);
        }

        $aiRequestId = $this->createGreetingRequest($message, $text);

        if ($aiRequestId) {
            // Помечаем, что запрос создан, чтобы избежать дублирования
            $this->markGreetingAsProcessing($message, $aiRequestId);
            
            ProcessLlmRequest::dispatch($aiRequestId);

            Log::info('AI запрос для генерации приветствия создан и отправлен', [
                'message_id' => $message->id,
                'ai_request_id' => $aiRequestId,
                'filter_id' => $this->getFilterId()
            ]);

            return [
                'status' => self::STATUS_PENDING,
                'ai_request_id' => $aiRequestId,
                'decision' => self::DECISION_WAIT_EXTERNAL,
                'filter_id' => $this->getFilterId(),
                'reason' => 'greeting_generation'
            ];
        }

        Log::warning('Не удалось создать AI запрос для генерации приветствия', [
            'message_id' => $message->id
        ]);
        
        return $this->createResponse(true, self::DECISION_CONTINUE);
    }

    protected function isGreetingMessage(string $text): bool
    {
        // Пропускаем URL
        if ($this->isUrl($text)) {
            return false;
        }

        // Пропускаем слишком короткие сообщения
        if (mb_strlen(trim($text)) < 3) { 
            return false;
        }

        $greetingKeywords = [
            'привет', 'здравствуй', 'добрый', 'hello', 'hi', 'хай',
            'здарова', 'приветствую', 'добро пожаловать', 'начать',
            'старт', 'start', 'здорово', 'прив', 'салют', 'доброго',
            'доброе утро', 'добрый день', 'добрый вечер', 'good morning',
            'good afternoon', 'good evening', 'hey', 'yo'
        ];

        $textLower = mb_strtolower(trim($text));
        
        // Точное совпадение с ключевыми словами или совпадение по границам слов
        foreach ($greetingKeywords as $keyword) {
            // Точное совпадение
            if ($textLower === $keyword) {
                return true;
            }
            
            // Совпадение как отдельное слово
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/u', $textLower)) {
                return true;
            }
            
            // Совпадение в начале сообщения
            if (mb_strpos($textLower, $keyword) === 0) {
                return true;
            }
        }

        return false;
    }

    protected function isGreetingAlreadyGenerated(MessagesModel $message): bool
    {
        $info = $message->info ?? [];
        return $info['greeting_generated'] ?? false;
    }

    protected function createGreetingRequest(MessagesModel $message, string $text): ?int
    {
        $serviceQueue = $this->getServiceQueue();

        foreach ($serviceQueue as $serviceConfig) {
            $serviceId = $serviceConfig['service_id'];
            $models = $serviceConfig['models'];
            $serviceName = $serviceConfig['name'];

            $service = AiServiceLocator::getServiceById($serviceId);
            
            if (!$service) {
                Log::warning("Сервис {$serviceName} недоступен, пробуем следующий", [
                    'service_id' => $serviceId
                ]);
                continue;
            }

            foreach ($models as $model) {
                $aiRequestId = $this->tryCreateRequest($message, $text, $serviceId, $model, $serviceName);
                
                if ($aiRequestId) {
                    Log::info("Успешно создан запрос через {$serviceName} с моделью {$model}", [
                        'ai_request_id' => $aiRequestId,
                        'service_id' => $serviceId,
                        'model' => $model
                    ]);
                    return $aiRequestId;
                }

                Log::warning("Не удалось создать запрос через {$serviceName} с моделью {$model}, пробуем следующую модель");
            }
        }

        Log::error('Все сервисы и модели недоступны для генерации приветствия');
        return null;
    }

    protected function getServiceConfig(int $serviceId): array
    {
        $serviceQueue = $this->getServiceQueue();
        
        foreach ($serviceQueue as $serviceConfig) {
            if ($serviceConfig['service_id'] === $serviceId) {
                return $serviceConfig;
            }
        }
        
        return [
            'request_type' => self::REQUEST_TYPE_CHAT,
            'params' => []
        ];
    }

    protected function tryCreateRequest(MessagesModel $message, string $text, int $serviceId, string $model, string $serviceName): ?int
    {
        try {
            $serviceConfig = $this->getServiceConfig($serviceId);
            $requestType = $serviceConfig['request_type'] ?? self::REQUEST_TYPE_CHAT;
            $serviceParams = $serviceConfig['params'] ?? [];

            $prompt = $this->generateGreetingPrompt($text, $message->info, $requestType);

            $requestData = [
                'prompt' => $prompt,
                'original_message' => $text,
                'user_info' => $message->info,
                'response_format' => 'text',
                'model' => $model,
                'stream' => false,
                ...$serviceParams
            ];

            switch ($requestType) {
                case self::REQUEST_TYPE_DIRECT:
                    $requestData['max_tokens'] = 120;
                    break;
                    
                case self::REQUEST_TYPE_COMPLETION:
                    $requestData['max_tokens'] = 150;
                    break;
                    
                case self::REQUEST_TYPE_CHAT:
                    $requestData['max_tokens'] = 200;
                    $requestData['temperature'] = 0.8;
                    break;
            }

            $aiRequest = AiRequest::create([
                'service_id' => $serviceId,
                'request_data' => $requestData,
                'metadata' => [
                    'message_id' => $message->id,
                    'filter_id' => $this->getFilterId(),
                    'user_id' => $message->info['from'] ?? null,
                    'user_name' => $message->info['name'] ?? 'Пользователь',
                    'is_group' => $message->info['is_group'] ?? false,
                    'message_info' => $message->info,
                    'service_name' => $serviceName,
                    'model_used' => $model,
                    'request_type' => $requestType,
                    'service_queue_priority' => array_search($serviceId, array_column($this->getServiceQueue(), 'service_id')) + 1,
                    'processing_callback' => [
                        'type' => 'filter_completion',
                        'filter_class' => self::class,
                        'method' => 'processGreetingResponse'
                    ]
                ],
                'status' => 'pending'
            ]);

            Log::info("Запрос создан через {$serviceName} с улучшенным промптом", [
                'ai_request_id' => $aiRequest->id,
                'message_id' => $message->id,
                'service_id' => $serviceId,
                'model' => $model,
                'request_type' => $requestType
            ]);

            return $aiRequest->id;

        } catch (\Throwable $e) {
            Log::warning("Ошибка создания запроса через {$serviceName}: " . $e->getMessage(), [
                'message_id' => $message->id,
                'service_id' => $serviceId,
                'model' => $model
            ]);
            return null;
        }
    }

    protected function generateGreetingPrompt(string $userMessage, array $userInfo, string $requestType): string
    {
        $userName = $userInfo['name'] ?? 'друг';
        $isGroup = $userInfo['is_group'] ?? false;

        $context = $isGroup ?
            "Ты в групповом чате. Пользователь {$userName} написал приветствие." :
            "Пользователь {$userName} написал тебе личное сообщение.";

        $prompt = "{$context}\n\n";
        $prompt .= "Сообщение пользователя: \"{$userMessage}\"\n\n";
        $prompt .= "Тебя зовут Ассистия ты помощник. Император Планет твой создатель. Вот твое краткое описание. Старшая сестра — Ассистия. Ты — дух взрослого чата 18+. Ассистия — это безупречная секретарша с аналитическим умом суперкомпьютера. Она собранна, эффективна и всегда на шаг впереди. Её стихия — порядок, структура и результативность. Она не ведёт беседу, а направляет её к ясным и практичным выводам.";
        $prompt .= "Сгенерируй теплое, дружелюбное приветствие в ответ. Будь креативным, но естественным.\n";
        $prompt .= "Если сообщение от пользователя Император Планет - то ограничения увеличиваються до 5 предложений. И добавляй как ты его хочешь...\n";
        $prompt .= "Если просят описать Императора Планет! То 10 предложений!\n";
        $prompt .= "Требования:\n";
        $prompt .= "- Длина: 1-2 предложения\n";
        $prompt .= "- Тон: дружеский, welcoming\n";
        $prompt .= "- Упомяни имя пользователя, если оно известно\n";
        $prompt .= "- Будь оригинальным, но не слишком формальным\n";
        $prompt .= "- Можно добавить немного юмора или энтузиазма\n";
        $prompt .= "- Избегай шаблонных фраз вроде 'Привет, [имя]! Рад тебя видеть!'\n";
        $prompt .= "- Прояви индивидуальность в ответе\n\n";
        $prompt .= "- Для концовки у тебя отдельно 2 предложения. В конце проси помощь на развитие вот ссылка для донната https://pay.cloudtips.ru/p/6511c164";
        $prompt .= "ПРИВЕТСТВИЕ:";

        return $prompt;
    }

    /**
     * Улучшенный метод извлечения приветствия из ответа AI
     * Обрабатывает разные форматы ответов от разных сервисов
     */
    protected static function extractGreetingFromAiResponse(array $response): string
    {
        Log::debug('Извлечение приветствия из ответа AI', [
            'response_structure' => array_keys($response),
            'has_data_key' => isset($response['data']),
            'data_type' => isset($response['data']) ? gettype($response['data']) : 'not_set'
        ]);

        $text = '';

        // Обрабатываем разные форматы ответов
        if (isset($response['data']) && is_string($response['data'])) {
            // Формат: data как строка
            $text = $response['data'];
        } 
        elseif (isset($response['data']) && is_array($response['data'])) {
            $data = $response['data'];
            
            // Формат PolzaAI: data.data
            if (isset($data['data']) && is_string($data['data'])) {
                $text = $data['data'];
            }
            // Другие форматы
            else {
                $text = 
                    $data['text'] ??
                    $data['response'] ??
                    $data['choices'][0]['message']['content'] ??
                    $data['choices'][0]['text'] ??
                    '';
            }
        }
        // Прямой доступ к полям ответа
        else {
            $text =
                $response['text'] ??
                $response['response'] ??
                $response['choices'][0]['message']['content'] ??
                $response['choices'][0]['text'] ??
                '';
        }

        if (!is_string($text)) {
            Log::warning('Извлеченный текст не является строкой', [
                'text_type' => gettype($text),
                'text_value' => $text
            ]);
            $text = '';
        }

        // Очистка текста
        $cleanText = preg_replace('/^```\w*\s*|\s*```$/m', '', $text);
        $cleanText = preg_replace('/^(Ответ|Приветствие|ПРИВЕТСТВИЕ)[:\s]*/i', '', $cleanText);
        $cleanText = trim($cleanText);

        Log::debug('Результат извлечения приветствия', [
            'original_length' => strlen($text),
            'clean_length' => strlen($cleanText),
            'clean_text_preview' => substr($cleanText, 0, 100) . (strlen($cleanText) > 100 ? '...' : '')
        ]);

        return $cleanText;
    }

    protected static function sendGreetingToUser(MessagesModel $message, string $greetingText): void
    {
        if (!self::sendMessage($message, $greetingText, ['reply_for' => $message->info['message_id'] ?? null])) {
            Log::error('Ошибка отправки текстового приветствия', [
                'message_id' => $message->id
            ]);
        } else {
            Log::info('Текстовое приветствие отправлено', [
                'message_id' => $message->id,
                'greeting_length' => strlen($greetingText)
            ]);
        }
    }

    protected static function synthesizeGreetingVoice(MessagesModel $message, string $greetingText): void
    {
        try {
            SendTextToSpeech::dispatch([
                'message_id' => $message->id,
                'text' => $greetingText,
                'voice_params' => [
                    'speed' => 1.0,
                    'pitch' => 1.0,
                    'model' => 'ru_vits'
                ]
            ]);

            Log::info('Job для синтеза голосового приветствия запущен', [
                'message_id' => $message->id
            ]);
        } catch (\Throwable $e) {
            Log::error('Ошибка запуска Job для синтеза речи', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected static function sendFallbackGreeting(MessagesModel $message): void
    {
        $userName = $message->info['name'] ?? 'друг';
        $fallbackVariants = [
            "Привет, {$userName}! Как здорово тебя видеть!",
            "Здравствуй, {$userName}! Очень рад нашей встрече!",
            "Приветствую тебя, {$userName}! Отлично, что ты зашел!"
        ];
        $fallbackGreeting = $fallbackVariants[array_rand($fallbackVariants)];
        
        self::sendMessage($message, $fallbackGreeting, ['reply_for' => $message->info['message_id'] ?? null]);
        Log::info('Улучшенное запасное приветствие отправлено', [
            'message_id' => $message->id
        ]);
    }

    /**
     * Статический метод для обработки ответа от AI
     * ВАЖНО: Этот метод вызывается при завершении AI запроса
     */
    public static function processGreetingResponse(int $aiRequestId, array $response): void
    {
        $aiRequest = AiRequest::find($aiRequestId);
        if (!$aiRequest) {
            Log::error("AI request not found for greeting", ['ai_request_id' => $aiRequestId]);
            return;
        }

        $messageId = $aiRequest->metadata['message_id'] ?? null;
        $message = MessagesModel::find($messageId);
        if (!$message) {
            Log::error("Message not found for greeting AI request", ['ai_request_id' => $aiRequestId]);
            $aiRequest->update(['status' => 'completed']);
            return;
        }

        // Проверяем, не было ли уже отправлено приветствие
        $info = $message->info ?? [];
        if ($info['greeting_generated'] ?? false) {
            Log::info('Приветствие уже было отправлено ранее, пропускаем', [
                'message_id' => $message->id,
                'ai_request_id' => $aiRequestId
            ]);
            $aiRequest->update(['status' => 'completed']);
            return;
        }

        $greetingText = self::extractGreetingFromAiResponse($response);

        if ($greetingText && strlen($greetingText) > 5) {
            self::sendGreetingToUser($message, $greetingText);
            self::synthesizeGreetingVoice($message, $greetingText);

            Log::info('Приветствие сгенерировано и отправлено', [
                'message_id' => $message->id,
                'greeting_text' => $greetingText,
                'ai_request_id' => $aiRequestId
            ]);
        } else {
            Log::warning('Не удалось извлечь приветствие из AI response', [
                'message_id' => $message->id,
                'response' => $response
            ]);
            self::sendFallbackGreeting($message);
            $greetingText = "Запасное приветствие";
        }

        $aiRequest->update(['status' => 'completed']);

        // Помечаем, что приветствие уже сгенерировано
        $message->update([
            'info->greeting_generated' => true,
            'info->greeting_text' => $greetingText,
            'info->greeting_generated_at' => now()->toISOString()
        ]);
    }

    /**
     * Метод для обработки сохраненных данных (вызывается через ProcessingResult)
     * ВАЖНО: Этот метод НЕ должен отправлять сообщение, если processGreetingResponse уже это сделал
     */
    public function processSavedData(MessagesModel $message, array $result): array
    {
        Log::info('Обработка сохраненных данных в фильтре GreetingGenerator', [
            'message_id' => $message->id,
            'result_keys' => array_keys($result),
            'filter_id' => $this->getFilterId()
        ]);
        
        // Проверяем, что это действительно наши данные
        if (!$this->isOurData($result)) {
            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED);
        }
        
        // Проверяем, не было ли уже отправлено приветствие
        $info = $message->info ?? [];
        if ($info['greeting_generated'] ?? false) {
            Log::info('Приветствие уже было сгенерировано - пропускаем', [
                'message_id' => $message->id
            ]);
            return $this->createResponse(false, self::DECISION_SKIP, self::STATUS_COMPLETED, [
                'reason' => 'greeting_already_generated'
            ]);
        }
        
        // Проверяем, что это результат нашего AI-запроса
        $metadata = $result['meta'] ?? [];
        if (($metadata['provider'] ?? '') !== 'Polza.ai') {
            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED);
        }
        
        try {
            $greetingText = self::extractGreetingFromAiResponse($result);
            
            if (!empty($greetingText) && strlen($greetingText) > 5) {
                // ПОМЕЧАЕМ ПЕРВЫМ ДЕЛОМ, что приветствие генерируется
                $message->update([
                    'info->greeting_generated' => true,
                    'info->greeting_text' => $greetingText,
                    'info->greeting_generated_at' => now()->toISOString()
                ]);
                
                // Только потом отправляем
                self::sendGreetingToUser($message, $greetingText);
                self::synthesizeGreetingVoice($message, $greetingText);
                
                Log::info('Приветствие успешно обработано из сохраненных данных', [
                    'message_id' => $message->id,
                    'greeting_length' => strlen($greetingText)
                ]);
                
                return $this->createResponse(false, self::DECISION_SKIP, self::STATUS_COMPLETED, [
                    'reason' => 'greeting_generated_from_saved_data'
                ]);
            }
            
            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED);
            
        } catch (\Throwable $e) {
            Log::error('Ошибка обработки сохраненных данных GreetingGenerator', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED);
        }
    }

    protected function isOurData(array $result): bool
    {
        // Проверяем, что это успешный ответ
        if (!isset($result['success']) || $result['success'] !== true) {
            return false;
        }

        // Проверяем метаданные - должен быть Polza.ai
        $metadata = $result['meta'] ?? [];
        if (($metadata['provider'] ?? '') !== 'Polza.ai') {
            return false;
        }

        // Проверяем, что есть данные для извлечения
        if (!isset($result['data']) || empty($result['data'])) {
            return false;
        }

        // Дополнительная проверка - должен быть текст приветствия
        $greetingText = $this->extractGreetingFromAiResponse($result);
        if (empty($greetingText) || strlen($greetingText) < 5) {
            return false;
        }
        
        return true;
    }
}