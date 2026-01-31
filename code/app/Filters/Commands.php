<?php

namespace App\Filters;

use App\Models\Assistant\MessagesModel;
use App\Jobs\AI\ProcessLlmRequest;
use App\Models\Ai\AiRequest;
use App\Helpers\Ai\AiServiceLocator;
use App\Helpers\Assistant\CommandProcessor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Commands extends Filter
{
    const REQUEST_TYPE_CHAT = 'chat';
    const REQUEST_TYPE_COMPLETION = 'completion';
    const REQUEST_TYPE_DIRECT = 'direct';

    /**
     * PROPERTY: commands
     * TYPE: array
     * PURPOSE: Список команд и их ключевых слов для идентификации
     * STRUCTURE: [['id' => int, 'keywords' => string]]
    */
    protected array $commands = [];

    /**
     * Очередь сервисов и моделей по приоритету для проверки команд
    */
    protected function getServiceQueue(): array
    {
        return [
            [
                'service_id' => 5, // PolzaAI - высший приоритет
                'models' => [ 
                    'deepseek/deepseek-chat-v3-0324',
                    'openai/gpt-4o-mini', 
                    'anthropic/claude-3-haiku',
                ],
                'name' => 'PolzaAI',
                'request_type' => self::REQUEST_TYPE_CHAT,
                'params' => [
                    'temperature' => 0.3,
                    'max_tokens' => 500
                ]
            ],
            [
                'service_id' => 3, // HuggingFace - резервный
                'models' => [
                    'deepseek/deepseek-v3-0324',
                    'mistralai/mistral-7b-instruct',
                ],
                'name' => 'HuggingFace',
                'request_type' => self::REQUEST_TYPE_CHAT,
                'params' => [
                    'temperature' => 0.3,
                    'max_tokens' => 500
                ]
            ],
            [
                'service_id' => 1, // OpenRouter - запасной вариант
                'models' => [
                    'gpt-4',
                    'gpt-3.5-turbo',
                ],
                'name' => 'OpenRouter',
                'request_type' => self::REQUEST_TYPE_CHAT,
                'params' => [
                    'temperature' => 0.3,
                    'max_tokens' => 500
                ]
            ],
        ];
    }

    /**
     * METHOD: __construct
     * PURPOSE: Инициализация фильтра команд и загрузка ключевых слов
    */
    public function __construct()
    {
        parent::__construct();
        $this->loadCommands();

        Log::info('Commands filter initialized', [
            'filter_id' => $this->getFilterId(),
            'filter_name' => $this->getFilterName(),
            'commands_count' => count($this->commands),
            'service_queue' => array_map(fn($s) => $s['name'], $this->getServiceQueue())
        ]);
    }

    /**
     * METHOD: loadCommands
     * PURPOSE: Загрузка команд и ключевых слов из JSON файла
    */
    protected function loadCommands(): void
    {
        if (!Storage::disk('local')->exists('commands/keywords.json')) {
            Log::warning('Keywords file not found');
            return;
        }

        $json = Storage::disk('local')->get('commands/keywords.json');
        $this->commands = json_decode($json, true) ?? [];

        Log::info('Commands loaded', ['count' => count($this->commands)]);
    }

    /**
     * METHOD: match
     * PURPOSE: Поиск точных совпадений текста с ключевыми словами команд
    */
    public function match(string $text)
    {
        $textLower = mb_strtolower(trim($text));
        $matchedIds = [];

        foreach ($this->commands as $cmd) {
            $keywords = array_map('trim', explode(',', $cmd['keywords'] ?? ''));
            foreach ($keywords as $word) {
                if ($word === '') continue;
                if (mb_strtolower($word) === $textLower) {
                    $matchedIds[] = $cmd['id'];
                    break;
                }
            }
        }

        return !empty($matchedIds) ? $matchedIds : false;
    }

    /**
     * METHOD: generatePrompt
     * PURPOSE: Генерация промпта для AI-анализа текста на наличие команд
    */
    protected function generatePrompt(string $userInput): string
    {
        $prompt = "Проанализируй, является ли текст пользователя командой.\n\n";
        $prompt .= "Доступные команды и их ключевые слова:\n";

        foreach ($this->commands as $cmd) {
            $prompt .= "- ID: {$cmd['id']}, Ключевые слова: {$cmd['keywords']}\n";
        }

        $prompt .= "\nТекст пользователя: \"$userInput\"\n\n";
        $prompt .= "Ответь ТОЛЬКО в формате JSON без какихких пояснений:\n";
        $prompt .= "{\n";
        $prompt .= "  \"is_command\": boolean,\n";
        $prompt .= "  \"command_id\": integer|null,\n";
        $prompt .= "  \"found_keyword\": string|null,\n";
        $prompt .= "  \"found_in_part\": string|null,\n";
        $prompt .= "  \"confidence\": float|null\n";
        $prompt .= "}\n\n";
        $prompt .= "Правила анализа:\n";
        $prompt .= "1. Учти возможность опечаток, фонетических и визуальных похожих слов\n";
        $prompt .= "2. Если слово похоже на ключевое считай это совпадением\n";
        $prompt .= "3. Для команд укажи уверенность от 0.1 до 1.0 (1.0 - точное совпадение)\n";
        $prompt .= "4. При уверенности ниже 0.7, не считай текст командой\n\n";
        $prompt .= "ПРИМЕРЫ:\n";
        $prompt .= "- Точное совпадение: {\"is_command\": true, \"command_id\": 1, \"found_keyword\": \"босс\", \"found_in_part\": \"босс\", \"confidence\": 1.0}\n";
        $prompt .= "- С опечаткой: {\"is_command\": true, \"command_id\": 1, \"found_keyword\": \"босс\", \"found_in_part\": \"боcс\", \"confidence\": 0.8}\n";
        $prompt .= "- Не команда: {\"is_command\": false, \"command_id\": null, \"found_keyword\": null, \"found_in_part\": null, \"confidence\": null}";

        return $prompt;
    }

    /**
     * METHOD: handle
     * PURPOSE: Основной метод обработки сообщения фильтром команд
    */
    public function handle(MessagesModel $message): array
    {
        $text = trim($message->text);

        $this->sendDebugMessage($message, "Обработка командного фильтра", [
            'text' => $text
        ]);

        Log::info('Обработка командного фильтра', [
            'message_id' => $message->id,
            'text' => $text,
            'filter_id' => $this->getFilterId()
        ]);

        $exactMatch = $this->match($text);

        if ($exactMatch !== false) {
            $this->sendDebugMessage($message, "Найдено точное совпадение команды", [
                'matched_ids' => $exactMatch
            ]);

            $this->processCommand($message, $exactMatch[0]);

            return $this->createResponse(false, self::DECISION_SKIP, self::STATUS_COMPLETED, [
                'reason' => 'exact_command_match',
                'matched_ids' => $exactMatch,
                'decision' => 'skip_processing'
            ]);
        }

        return $this->handleWithAiCheck($message, $text);
    }

    /**
     * METHOD: handleWithAiCheck
     * PURPOSE: Обработка сообщения с использованием AI-анализа для нечетких совпадений
    */
    protected function handleWithAiCheck(MessagesModel $message, string $text): array
    {
        // Используем очередь сервисов вместо прямого создания запроса
        $aiRequestId = $this->createCommandCheckRequest($message, $text);

        if ($aiRequestId) {
            $this->sendDebugMessage($message, "AI запрос создан и отправлен в обработку", [
                'ai_request_id' => $aiRequestId
            ]);

            ProcessLlmRequest::dispatch($aiRequestId);

            return [
                'status' => self::STATUS_PENDING,
                'ai_request_id' => $aiRequestId,
                'decision' => self::DECISION_WAIT_EXTERNAL,
                'filter_id' => $this->getFilterId(),
                'reason' => 'ai_command_check'
            ];
        }

        return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
            'reason' => 'ai_request_failed'
        ]);
    }

    /**
     * Создание AI запроса для проверки команд через очередь сервисов
    */
    protected function createCommandCheckRequest(MessagesModel $message, string $text): ?int
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
                $aiRequestId = $this->tryCreateCommandRequest($message, $text, $serviceId, $model, $serviceName, $serviceConfig);
                
                if ($aiRequestId) {
                    Log::info("Успешно создан запрос проверки команд через {$serviceName} с моделью {$model}", [
                        'ai_request_id' => $aiRequestId,
                        'service_id' => $serviceId,
                        'model' => $model
                    ]);
                    return $aiRequestId;
                }

                Log::warning("Не удалось создать запрос через {$serviceName} с моделью {$model}, пробуем следующую модель");
            }
        }

        Log::error('Все сервисы и модели недоступны для проверки команд');
        return null;
    }

    /**
     * Попытка создания запроса через конкретный сервис и модель
    */
    protected function tryCreateCommandRequest(MessagesModel $message, string $text, int $serviceId, string $model, string $serviceName, array $serviceConfig): ?int
    {
        try {
            $requestType = $serviceConfig['request_type'] ?? self::REQUEST_TYPE_CHAT;
            $serviceParams = $serviceConfig['params'] ?? [];

            $prompt = $this->generatePrompt($text);

            $requestData = [
                'prompt' => $prompt,
                'original_message' => $text,
                'response_format' => 'json',
                'model' => $model,
                'stream' => false,
                ...$serviceParams
            ];

            // Настройки в зависимости от типа запроса
            switch ($requestType) {
                case self::REQUEST_TYPE_DIRECT:
                    $requestData['max_tokens'] = 300;
                    break;
                    
                case self::REQUEST_TYPE_COMPLETION:
                    $requestData['max_tokens'] = 300;
                    break;
                    
                case self::REQUEST_TYPE_CHAT:
                    $requestData['max_tokens'] = 500;
                    $requestData['temperature'] = 0.3;
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
                        'method' => 'processAiResponse'
                    ]
                ],
                'status' => 'pending'
            ]);

            Log::info("Запрос проверки команд создан через {$serviceName}", [
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

    /**
     * Получение конфигурации сервиса по ID
    */
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

    /**
     * METHOD: extractAiJson
     * PURPOSE: Извлечение JSON данных из AI-ответа
    */
    protected function extractAiJson(array $response): ?array
    {
        $responseData = $response['response_data'] ?? $response;
        $textResponse = $responseData['text'] ?? $responseData['response'] ?? $responseData['data'] ?? null;

        if ($textResponse) {
            $clean = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/m', '', $textResponse);

            if (preg_match('/\{.*\}/s', $clean, $matches)) {
                $data = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
            }
        }

        return is_array($responseData) ? $responseData : null;
    }

    /**
     * METHOD: parseAiResponse
     * PURPOSE: Парсинг AI-ответа для определения наличия команды
    */
    protected function parseAiResponse(array $response): bool
    {
        $data = $this->extractAiJson($response);
        return (bool)($data['is_command'] ?? false);
    }

    /**
     * METHOD: parseCommandIdFromAiResponse
     * PURPOSE: Извлечение ID команды из AI-ответа
    */
    protected function parseCommandIdFromAiResponse(array $response): ?int
    {
        $data = $this->extractAiJson($response);
        return isset($data['command_id']) ? (int)$data['command_id'] : null;
    }

    /**
     * METHOD: processCommand
     * PURPOSE: Обработка идентифицированной команды
    */
    protected function processCommand(MessagesModel $message, int $commandId): void
    {
        $message->update([
            'status' => 1,
            'info->processed_as_command' => true,
            'info->command_id' => $commandId,
            'info->processed_at' => now()->toISOString()
        ]);
        $processor = new CommandProcessor();
        $processor->executeCommand($commandId, $message);
        Log::info('Команда обработана и сообщение помечено как обработанное', [
            'message_id' => $message->id,
            'command_id' => $commandId
        ]);
    }

    /**
     * METHOD: processAiResponse
     * PURPOSE: Статический метод обработки AI-ответа (callback для очереди)
    */
    public static function processAiResponse(int $aiRequestId, array $response): void
    {
        $aiRequest = AiRequest::find($aiRequestId);
        if (!$aiRequest) {
            Log::error("AI request not found for commands", ['ai_request_id' => $aiRequestId]);
            return;
        }

        $messageId = $aiRequest->metadata['message_id'] ?? null;
        $message = MessagesModel::find($messageId);
        if (!$message) {
            Log::error("Message not found for commands AI request", ['ai_request_id' => $aiRequestId]);
            $aiRequest->update(['status' => 'completed']);
            return;
        }

        $filterId = $aiRequest->metadata['filter_id'] ?? null;
        $instance = new self();

        $isCommand = $instance->parseAiResponse($response);
        $commandId = $instance->parseCommandIdFromAiResponse($response);

        if ($isCommand && $commandId) {
            if (!empty($message->info['processed_as_command'])) {
                Log::info('Команда уже обработана, пропускаем повторный запуск', [
                    'message_id' => $message->id,
                    'command_id' => $commandId
                ]);
                return;
            }
            $instance->processCommand($message, $commandId);
            Log::info('AI определил команду и вызвал процессор', [
                'message_id' => $message->id,
                'command_id' => $commandId
            ]);
        } else {
            Log::info('AI проверка: команда не определена', [
                'message_id' => $message->id,
                'response' => $response
            ]);
        }

        // КРИТИЧЕСКИ ВАЖНО: Продолжаем цепочку фильтров после AI-обработки команд
        if ($filterId) {
            \App\Helpers\Assistant\FilterProcessor::dispatchNextFilter($message, $filterId);
            Log::info('Цепочка фильтров продолжена после AI-обработки команд', [
                'message_id' => $message->id,
                'filter_id' => $filterId,
                'is_command' => $isCommand
            ]);
        }

        $aiRequest->update(['status' => 'completed']);
    }

    /**
     * METHOD: processSavedData
     * PURPOSE: Обработка сохраненных данных AI-ответа при повторной обработке
    */
    public function processSavedData(MessagesModel $message, array $result): array
    {
        Log::info('Обработка сохранённых данных в фильтре Commands', [
            'message_id' => $message->id,
            'result_keys' => array_keys($result)
        ]);

        try {
            // Извлекаем данные из AI-ответа
            $data = $this->extractAiJson($result);
            
            if (!$data) {
                Log::warning('Не удалось извлечь JSON из AI-ответа', [
                    'message_id' => $message->id,
                    'result' => $result
                ]);
                return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED);
            }

            // Основная логика обработки команд...
            if (!($data['is_command'] ?? false)) {
                Log::info('AI подтвердил: не команда', [
                    'message_id' => $message->id,
                    'command_data' => $data
                ]);
                
                return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED);
            }

            // Обработка команды...
            $commandId = $data['command_id'] ?? null;
            if ($commandId) {
                $this->processCommand($message, $commandId);
                return $this->createResponse(false, self::DECISION_SKIP, self::STATUS_COMPLETED);
            }

            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED);

        } catch (\Throwable $e) {
            Log::error('Ошибка в processSavedData Commands', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED);
        }
    }

    /**
     * Получение структуры параметров для настройки очереди сервисов
    */
    public function getParametersStructure(): array
    {
        $parentStructure = parent::getParametersStructure();
        
        return array_merge($parentStructure, [
            'service_priority' => [
                'type' => 'textarea',
                'label' => 'Приоритет сервисов и моделей',
                'description' => 'JSON массив с приоритетом сервисов и моделей для проверки команд',
                'default' => json_encode($this->getServiceQueue(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'required' => false,
                'rows' => 10
            ],
            'confidence_threshold' => [
                'type' => 'number',
                'label' => 'Порог уверенности команды',
                'description' => 'Минимальная уверенность AI для определения команды (0.1-1.0)',
                'default' => 0.7,
                'min' => 0.1,
                'max' => 1.0,
                'step' => 0.1
            ]
        ]);
    }
}