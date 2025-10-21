<?php

namespace App\Filters;

use App\Models\Assistant\MessagesModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Helpers\Socials\SocialInterface;
use App\Jobs\AI\ProcessLlmRequest;
use App\Models\Ai\AiRequest;
use App\Helpers\Ai\AiServiceLocator;

abstract class Filter
{
    /**
     * Типы фильтров
     */
    public const TYPE_PROMPT = 'prompt';
    public const TYPE_HANDLER = 'handler';
    public const TYPE_N8N = 'n8n';

    protected int $filterId;
    protected string $filterName;
    protected array $filterConfig;

	protected array $parameters = [];

    abstract public function handle(MessagesModel $message): array;
    abstract public function processSavedData(MessagesModel $message, array $result): array;

    public const DECISION_CONTINUE = 'continue_processing';
    public const DECISION_REJECT   = 'reject';
    public const DECISION_SKIP     = 'skip_processing';
    public const DECISION_WAIT_EXTERNAL = 'wait_for_external';

    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_PENDING    = 'pending';
    public const STATUS_FAILED     = 'failed';

    public function __construct()
    {
        $this->initializeFilterData();
		$this->loadParameters();
    }

    protected function initializeFilterData(): void
    {
        $className = get_class($this);
        $handlerString = $className . '@handle';
        $this->filterConfig = $this->findFilterByHandler($handlerString);

        if ($this->filterConfig) {
            $this->filterId = $this->filterConfig['id'];
            $this->filterName = $this->filterConfig['name'] ?? 'Безымянный фильтр';
            
            Log::debug('Фильтр инициализирован', [
                'filter_id' => $this->filterId,
                'filter_name' => $this->filterName,
                'handler' => $handlerString,
                'has_parameters_in_config' => !empty($this->filterConfig['parameters']),
                'parameters_count' => count($this->filterConfig['parameters'] ?? [])
            ]);
        } else {
            Log::error('Конфигурация фильтра не найдена', ['handler' => $handlerString]);
            throw new \Exception("Конфигурация фильтра не найдена: {$handlerString}");
        }
    }

    protected function findFilterByHandler(string $handler): ?array
    {
        try {
            if (!Storage::disk('local')->exists('filters/filters.json')) return null;
            $filtersJson = Storage::disk('local')->get('filters/filters.json');
            $filters = json_decode($filtersJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) return null;

            foreach ($filters as $filter) {
                $filterType = isset($filter['type']) ? $filter['type'] : '';
                $filterHandler = isset($filter['parameters']['handler']) ? $filter['parameters']['handler'] : '';
                
                if ($filterType === 'handler' && $filterHandler === $handler) {
                    return $filter;
                }
            }
            return null;
        } catch (\Throwable $e) {
            Log::error('Ошибка поиска фильтра по handler', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function createResponse(
        bool $approved,
        string $decision,
        string $status = self::STATUS_COMPLETED,
        array $additionalData = []
    ): array {
        return array_merge([
            'approved' => $approved,
            'decision' => $decision,
            'status' => $status,
            'filter_id' => $this->getFilterId(),
            'filter_name' => $this->getFilterName(),
            'processed_at' => now()->toISOString()
        ], $additionalData);
    }

    public static function shouldStopChain(array $result): bool
    {
        $decision = isset($result['decision']) ? $result['decision'] : self::DECISION_CONTINUE;
        $status   = isset($result['status']) ? $result['status'] : self::STATUS_COMPLETED;
        return in_array($decision, [self::DECISION_REJECT, self::DECISION_SKIP, self::DECISION_WAIT_EXTERNAL], true)
            || $status === self::STATUS_PENDING;
    }

    // ======================== УНИВЕРСАЛЬНЫЕ ФУНКЦИИ ========================

    /**
     * Получение экземпляра соцсети по ID
     */
    protected static function getSocialInstance(MessagesModel $message): ?SocialInterface
    {
        try {
            $social = \App\Models\Socials\SocialsModel::find($message->soc);
            if (!$social) return null;
            $className = $social->propertyById(35) ? $social->propertyById(35)->pivot->value : null;
            if (!$className || !class_exists($className)) return null;
            $instance = new $className;
            return $instance instanceof SocialInterface ? $instance : null;
        } catch (\Throwable $e) {
            Log::error('Ошибка получения экземпляра соцсети', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Отправка сообщения
     */
    protected static function sendMessage(MessagesModel $message, string $text, array $params = []): bool
    {
        $social = self::getSocialInstance($message);
        if (!$social) return false;

        try {
            $result = $social->sendMessage($message->chat_id, $text, $params);
            $social->processResultSendMessage($result);
            Log::info('Сообщение отправлено', ['message_id' => $message->id]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Ошибка отправки сообщения', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Редактирование сообщения
     */
    protected static function editMessage(MessagesModel $message, string $newText, array $params = []): bool
    {
        $social = self::getSocialInstance($message);
        if (!$social || !$social->checkEditMessage()) return false;

        try {
            $messageId = isset($message->info['message_id']) ? $message->info['message_id'] : null;
            $result = $social->editMessage($message->chat_id, $messageId, $newText, $params);
            $social->processResultEditMessage($result);
            Log::info('Сообщение отредактировано', ['message_id' => $message->id]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Ошибка редактирования сообщения', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Удаление сообщения
     */
    protected static function deleteMessage(MessagesModel $message, array $params = []): bool
    {
        $social = self::getSocialInstance($message);
        if (!$social || !$social->checkDeleteMessage()) return false;

        try {
            $messageId = isset($message->info['message_id']) ? $message->info['message_id'] : null;
            $result = $social->deleteMessage($message->chat_id, $messageId, $params);
            $social->processResultDeleteMessage($result);
            Log::info('Сообщение удалено', ['message_id' => $message->id]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Ошибка удаления сообщения', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Универсальная отправка уведомления об ошибке
     */
    protected static function sendErrorNotification(MessagesModel $message, string $errorText): void
    {
        $text = "❌ Ошибка при обработке сообщения: {$errorText}";
        self::sendMessage($message, $text);
        Log::warning('Отправлено уведомление об ошибке', [
            'message_id' => $message->id,
            'error' => $errorText
        ]);
    }

    // ======================== Геттеры ========================
    public function getFilterId(): int { return $this->filterId; }
    public function getFilterName(): string { return $this->filterName; }
    public function getFilterConfig(): array { return $this->filterConfig; }

	// ======================== AI запросы ========================
	/**
     * Универсальный метод создания AI запроса
     */
    protected function createAiRequest(
        MessagesModel $message,
        array $requestData,
        string $requestType = 'completion',
        array $customMetadata = []
    ): ?int {
        // Получаем предпочтительный сервис из metadata (если есть)
        $preferredServiceId = $message->info['preferred_ai_service'] ?? null;
        
        // Используем упрощенную логику выбора сервисов
        $services = AiServiceLocator::getAllActiveServices($preferredServiceId);

        if (empty($services)) {
            Log::error('Нет доступных AI сервисов', [
                'preferred_service_id' => $preferredServiceId
            ]);
            return null;
        }

        try {
            $metadata = array_merge([
                'message_id' => $message->id,
                'filter_id' => $this->getFilterId(),
                'filter_name' => $this->getFilterName(),
                'user_id' => $message->info['from'] ?? null,
                'is_group' => $message->info['is_group'] ?? false,
                'message_info' => $message->info,
                'request_type' => $requestType,
                'preferred_service_id' => $preferredServiceId,
                'actual_service_id' => $services[0]->id,
                'available_services_count' => count($services),
                'processing_callback' => [
                    'type' => 'filter_completion',
                    'filter_class' => static::class,
                    'method' => 'processAiResponse'
                ]
            ], $customMetadata);

            $aiRequest = AiRequest::create([
                'service_id' => $services[0]->id,
                'request_data' => $requestData,
                'metadata' => $metadata,
                'status' => 'pending'
            ]);

            Log::info('AI запрос создан через универсальный метод', [
                'message_id' => $message->id,
                'ai_request_id' => $aiRequest->id,
                'filter_id' => $this->getFilterId(),
                'request_type' => $requestType,
                'service' => $services[0]::getName()
            ]);

            return $aiRequest->id;

        } catch (\Throwable $e) {
            Log::error("Ошибка создания AI запроса: " . $e->getMessage(), [
                'message_id' => $message->id,
                'filter_id' => $this->getFilterId()
            ]);
            return null;
        }
    }

	/**
     * Универсальная обработка AI ответа
     */
    public static function processAiResponse(int $aiRequestId, array $response): void
    {
        $aiRequest = AiRequest::find($aiRequestId);
        if (!$aiRequest) {
            Log::error("AI request not found", ['ai_request_id' => $aiRequestId]);
            return;
        }

        $messageId = isset($aiRequest->metadata['message_id']) ? $aiRequest->metadata['message_id'] : null;
        $message = MessagesModel::find($messageId);
        if (!$message) {
            Log::error("Message not found for AI request", ['ai_request_id' => $aiRequestId]);
            $aiRequest->update(['status' => 'completed']);
            return;
        }

        $filterId = isset($aiRequest->metadata['filter_id']) ? $aiRequest->metadata['filter_id'] : null;
        
        // Сохраняем результат в info сообщения для дальнейшей обработки
        $info = isset($message->info) ? $message->info : [];
        $info['filters'][$filterId] = [
            'ai_request_id' => $aiRequestId,
            'response' => $response,
            'processed_at' => now()->toISOString(),
            'status' => 'completed'
        ];
        
        $message->info = $info;
        $message->save();

        Log::info('AI ответ сохранен для обработки в фильтре', [
            'message_id' => $message->id,
            'filter_id' => $filterId,
            'ai_request_id' => $aiRequestId
        ]);

        $aiRequest->update(['status' => 'completed']);
    }

	// ======================== Настройки фильтра ========================

    /**
     * Загрузка параметров фильтра из свойства 102
     */
    protected function loadParameters(): void
    {
        // ПРИОРИТЕТ 1: Параметры из файла filters.json
        $fileParameters = $this->filterConfig['parameters'] ?? [];
        
        // ПРИОРИТЕТ 2: Параметры из базы данных (property_id = 102)
        $dbParameters = $this->loadParametersFromDatabase();
        
        // Объединяем с приоритетом файла (параметры из файла перезаписывают БД)
        $mergedParameters = array_merge($dbParameters, $fileParameters);
        
        $this->parameters = $this->validateParameters($mergedParameters);

        Log::debug('Параметры фильтра загружены', [
            'filter_id' => $this->filterId,
            'filter_name' => $this->filterName,
            'from_file_count' => count($fileParameters),
            'from_db_count' => count($dbParameters),
            'merged_count' => count($this->parameters),
            'parameters_keys' => array_keys($this->parameters)
        ]);
    }

    /**
     * Загрузка параметров из базы данных
     */
    protected function loadParametersFromDatabase(): array
    {
        try {
            $filterModel = \App\Models\Assistant\FiltersModel::find($this->filterId);
            if (!$filterModel) {
                return [];
            }

            $parametersProperty = $filterModel->propertyById(102);
            if (!$parametersProperty) {
                return [];
            }

            $parameters = json_decode($parametersProperty->pivot->value, true);
            return is_array($parameters) ? $parameters : [];

        } catch (\Throwable $e) {
            Log::error('Ошибка загрузки параметров фильтра из БД', [
                'filter_id' => $this->filterId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

	/**
     * Получение параметров фильтра из базы данных
     */
    protected function getFilterParameters(): array
    {
        $parametersProperty = isset($this->filterConfig['parameters_property']) ? $this->filterConfig['parameters_property'] : 113;
        
        try {
            $filterModel = \App\Models\Assistant\FiltersModel::find($this->filterId);
            if (!$filterModel) {
                return [];
            }

            $parametersProperty = $filterModel->propertyById($parametersProperty);
            if (!$parametersProperty) {
                return [];
            }

            $parameters = json_decode($parametersProperty->pivot->value, true);
            return is_array($parameters) ? $parameters : [];

        } catch (\Throwable $e) {
            Log::error('Ошибка получения параметров фильтра', [
                'filter_id' => $this->filterId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Проверка, имеет ли фильтр настраиваемые параметры
     */
    public function hasConfigurableParameters(): bool
    {
        return !empty($this->getParametersStructure());
    }

    /**
     * Получение структуры параметров для UI
     * Переопределяется в конкретных фильтрах
     */
    public function getParametersStructure(): array
    {
        return [
            'debug_enabled' => [
                'type' => 'boolean',
                'label' => 'Режим отладки',
                'description' => 'Включить отправку отладочных сообщений',
                'default' => false,
                'required' => false
            ],
            'debug_recipients' => [
                'type' => 'text',
                'label' => 'Получатели отладочных сообщений',
                'description' => 'ID пользователей или чатов через запятую. Оставьте пустым для отключения.',
                'default' => '',
                'required' => false,
                'placeholder' => '123456, 789012, 345678'
            ]
        ];
    }

    /**
     * Валидация параметров
     */
    protected function validateParameters(array $parameters): array
    {
        $structure = $this->getParametersStructure();
        $validated = [];

        foreach ($structure as $key => $config) {
            $value = isset($parameters[$key]) ? $parameters[$key] : (isset($config['default']) ? $config['default'] : null);

            // Преобразование типов
            $type = isset($config['type']) ? $config['type'] : 'text';
            switch ($type) {
                case 'number':
                    $value = (int)$value;
                    break;
                case 'boolean':
                    $value = (bool)$value;
                    break;
            }

            $validated[$key] = $value;
        }

        return $validated;
    }

	/**
     * Получение значения параметра
     */
    protected function getParameter(string $key, $default = null)
    {
        return isset($this->parameters[$key]) ? $this->parameters[$key] : $default;
    }

    /**
     * Применение параметров к логике фильтра
     */
    protected function applyParameters(array $parameters = []): void
    {
        $this->parameters = array_merge($this->getFilterParameters(), $parameters);
    }

    // ======================== ОТЛАДОЧНЫЕ СООБЩЕНИЯ ========================

    /**
     * Отправка отладочного сообщения
     */
    protected function sendDebugMessage(MessagesModel $message, string $debugText, array $additionalData = []): void
    {
        try {
            $debugEnabled = $this->getParameter('debug_enabled', false);
            $debugRecipients = $this->getParameter('debug_recipients', '');
            
            if (!$debugEnabled || empty($debugRecipients)) {
                return;
            }

            // Разбиваем получателей по запятой
            $recipientIds = array_map('trim', explode(',', $debugRecipients));
            
            // Формируем отладочное сообщение
            $userId = isset($message->info['from']) ? $message->info['from'] : 'N/A';
            $text = "🔍 **Отладка фильтра: {$this->getFilterName()}**\n\n";
            $text .= "📝 **Сообщение:** {$message->text}\n";
            $text .= "👤 **Пользователь:** {$userId}\n";
            $text .= "🆔 **ID сообщения:** {$message->id}\n";
            $text .= "⏰ **Время:** " . now()->format('H:i:s') . "\n\n";
            $text .= "💬 **Отладочная информация:**\n{$debugText}";

            if (!empty($additionalData)) {
                $text .= "\n\n📊 **Дополнительные данные:**\n```json\n" . 
                        json_encode($additionalData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . 
                        "\n```";
            }

            // Отправляем каждому получателю
            foreach ($recipientIds as $recipientId) {
                if (!empty(trim($recipientId))) {
                    $this->sendDebugToRecipient($message, $recipientId, $text);
                }
            }

            Log::debug('Отладочное сообщение отправлено', [
                'filter_id' => $this->getFilterId(),
                'filter_name' => $this->getFilterName(),
                'message_id' => $message->id,
                'recipients_count' => count($recipientIds)
            ]);

        } catch (\Throwable $e) {
            Log::error('Ошибка отправки отладочного сообщения', [
                'filter_id' => $this->getFilterId(),
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Отправка отладочного сообщения конкретному получателю
     */
    protected function sendDebugToRecipient(MessagesModel $originalMessage, string $recipientId, string $debugText): void
    {
        try {
            // Создаем фиктивное сообщение для отправки отладки
            $debugMessage = new MessagesModel();
            $debugMessage->chat_id = $recipientId; // Отправляем в чат получателя
            $debugMessage->soc = $originalMessage->soc; // Та же социальная сеть
            $debugMessage->info = $originalMessage->info;
            
            // Используем существующий метод отправки
            self::sendMessage($debugMessage, $debugText);

        } catch (\Throwable $e) {
            Log::error('Ошибка отправки отладки получателю', [
                'recipient_id' => $recipientId,
                'error' => $e->getMessage()
            ]);
        }
    }

    // ======================== N8N ФУНКЦИОНАЛ ========================

    /**
     * Отправка запроса в n8n
     */
    protected function sendN8nRequest(array $payload, array $config = []): array
    {
        $webhookUrl = $config['webhook_url'] ?? $this->getParameter('n8n_webhook_url');
        $timeout = $config['timeout'] ?? $this->getParameter('n8n_timeout', 30);
        $method = $config['method'] ?? $this->getParameter('n8n_http_method', 'POST');
        $headers = $config['headers'] ?? $this->getN8nHeaders();

        if (!$webhookUrl) {
            throw new \Exception('N8n webhook URL not configured');
        }

        try {
            $client = Http::timeout($timeout)
                ->withHeaders($headers);

            $response = $method === 'GET' 
                ? $client->get($webhookUrl, $payload)
                : $client->post($webhookUrl, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'status' => $response->status()
                ];
            }

            return [
                'success' => false,
                'error' => 'HTTP ' . $response->status() . ': ' . $response->body(),
                'status' => $response->status()
            ];

        } catch (\Throwable $e) {
            Log::error('N8n request failed', [
                'url' => $webhookUrl,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Получение заголовков для n8n запроса
     */
    protected function getN8nHeaders(): array
    {
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'AssistantBot/1.0',
            'X-Filter-ID' => (string)$this->getFilterId(),
            'X-Filter-Name' => $this->getFilterName(),
        ];

        $customHeaders = $this->getParameter('n8n_headers', '{}');
        $customHeaders = json_decode($customHeaders, true) ?? [];

        return array_merge($defaultHeaders, $customHeaders);
    }

    /**
     * Подготовка payload для n8n
     */
    protected function prepareN8nPayload(MessagesModel $message, array $customData = []): array
    {
        $template = $this->getParameter('n8n_payload_template', '{}');
        $template = json_decode($template, true) ?? [];

        if (empty($template)) {
            // Стандартный шаблон
            $template = [
                'message' => [
                    'id' => $message->id,
                    'text' => $message->text,
                    'chat_id' => $message->chat_id,
                    'social_id' => $message->soc,
                    'created_at' => $message->created_at?->toISOString(),
                ],
                'user' => [
                    'id' => $message->info['from'] ?? null,
                    'name' => $message->info['name'] ?? 'Unknown',
                    'is_group' => $message->info['is_group'] ?? false,
                ],
                'filter' => [
                    'id' => $this->getFilterId(),
                    'name' => $this->getFilterName(),
                ],
                'timestamp' => now()->toISOString(),
            ];
        } else {
            // Заменяем плейсхолдеры в кастомном шаблоне
            $template = $this->processN8nTemplate($template, $message);
        }

        return array_merge($template, $customData);
    }

    /**
     * Обработка шаблона с плейсхолдерами
     */
    protected function processN8nTemplate(array $template, MessagesModel $message): array
    {
        $placeholders = [
            '{{message.id}}' => $message->id,
            '{{message.text}}' => $message->text,
            '{{message.chat_id}}' => $message->chat_id,
            '{{message.social_id}}' => $message->soc,
            '{{user.id}}' => $message->info['from'] ?? null,
            '{{user.name}}' => $message->info['name'] ?? 'Unknown',
            '{{filter.id}}' => $this->getFilterId(),
            '{{filter.name}}' => $this->getFilterName(),
            '{{timestamp}}' => now()->toISOString(),
        ];

        return $this->replacePlaceholdersRecursive($template, $placeholders);
    }

    /**
     * Рекурсивная замена плейсхолдеров в массиве
     */
    protected function replacePlaceholdersRecursive(array $data, array $placeholders): array
    {
        array_walk_recursive($data, function (&$value) use ($placeholders) {
            if (is_string($value)) {
                $value = str_replace(array_keys($placeholders), array_values($placeholders), $value);
            }
        });

        return $data;
    }

    /**
     * Парсинг ответа от n8n
     */
    protected function parseN8nResponse(array $n8nResponse): array
    {
        $mappingConfig = $this->getParameter('n8n_response_mapping', '{}');
        $mapping = json_decode($mappingConfig, true) ?? [];

        // Если маппинг не настроен, используем стандартные поля
        if (empty($mapping)) {
            return [
                'approved' => $n8nResponse['approved'] ?? true,
                'decision' => $this->mapN8nDecision($n8nResponse['decision'] ?? 'continue'),
                'reason' => $n8nResponse['reason'] ?? 'n8n_processed',
                'confidence' => $n8nResponse['confidence'] ?? 1.0,
                'external_id' => $n8nResponse['external_id'] ?? null,
                'wait_timeout' => $n8nResponse['wait_timeout'] ?? null,
            ];
        }

        // Применяем кастомный маппинг
        return [
            'approved' => $this->getMappedValue($n8nResponse, $mapping['approved'] ?? 'approved'),
            'decision' => $this->mapN8nDecision(
                $this->getMappedValue($n8nResponse, $mapping['decision'] ?? 'decision')
            ),
            'reason' => $this->getMappedValue($n8nResponse, $mapping['reason'] ?? 'reason'),
            'confidence' => (float) $this->getMappedValue($n8nResponse, $mapping['confidence'] ?? 'confidence'),
            'external_id' => $this->getMappedValue($n8nResponse, $mapping['external_id'] ?? 'external_id'),
            'wait_timeout' => $this->getMappedValue($n8nResponse, $mapping['wait_timeout'] ?? 'wait_timeout'),
        ];
    }

    /**
     * Получение значения по пути в массиве (dot notation)
     */
    protected function getMappedValue(array $data, string $path, $default = null)
    {
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                return $default;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Маппинг решения n8n на внутренние константы
     */
    protected function mapN8nDecision(string $n8nDecision): string
    {
        $mapping = [
            'approve' => self::DECISION_CONTINUE,
            'reject' => self::DECISION_REJECT,
            'skip' => self::DECISION_SKIP,
            'wait' => self::DECISION_WAIT_EXTERNAL,
            'continue' => self::DECISION_CONTINUE,
        ];

        return $mapping[$n8nDecision] ?? self::DECISION_CONTINUE;
    }

    /**
     * Обработка ошибок n8n
     */
    protected function handleN8nError(string $error): array
    {
        $strategy = $this->getParameter('n8n_error_handling', 'continue');

        Log::warning('N8n error handled', [
            'error' => $error,
            'strategy' => $strategy,
            'filter_id' => $this->getFilterId()
        ]);

        switch ($strategy) {
            case 'reject':
                return $this->createResponse(false, self::DECISION_REJECT, self::STATUS_COMPLETED, [
                    'reason' => 'n8n_error_reject',
                    'error' => $error
                ]);

            case 'skip':
                return $this->createResponse(false, self::DECISION_SKIP, self::STATUS_COMPLETED, [
                    'reason' => 'n8n_error_skip',
                    'error' => $error
                ]);

            case 'continue':
            default:
                return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
                    'reason' => 'n8n_error_continue',
                    'error' => $error
                ]);
        }
    }

    /**
     * Повторная попытка запроса к n8n
     */
    protected function retryN8nRequest(callable $requestFunction, array $config = []): array
    {
        $maxAttempts = $config['attempts'] ?? $this->getParameter('n8n_retry_attempts', 3);
        $delay = $config['delay'] ?? 1000; // ms

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $result = $requestFunction();
                
                if ($result['success']) {
                    return $result;
                }

                Log::warning("N8n request attempt {$attempt} failed", [
                    'error' => $result['error'] ?? 'Unknown error',
                    'filter_id' => $this->getFilterId()
                ]);

                if ($attempt < $maxAttempts) {
                    usleep($delay * 1000);
                    $delay *= 2; // Exponential backoff
                }

            } catch (\Throwable $e) {
                Log::warning("N8n request attempt {$attempt} exception", [
                    'error' => $e->getMessage(),
                    'filter_id' => $this->getFilterId()
                ]);

                if ($attempt === $maxAttempts) {
                    throw $e;
                }

                usleep($delay * 1000);
                $delay *= 2;
            }
        }

        return [
            'success' => false,
            'error' => 'All retry attempts failed'
        ];
    }

    /**
     * Создание callback URL для асинхронных n8n workflow
     */
    protected function getN8nCallbackUrl(MessagesModel $message): string
    {
        $baseUrl = $this->getParameter('n8n_callback_url');
        
        if (!$baseUrl) {
            $baseUrl = config('app.url') . '/api/n8n/callback';
        }

        $params = http_build_query([
            'message_id' => $message->id,
            'filter_id' => $this->getFilterId(),
            'token' => $this->generateCallbackToken($message)
        ]);

        return $baseUrl . '?' . $params;
    }

    /**
     * Генерация токена для callback
     */
    protected function generateCallbackToken(MessagesModel $message): string
    {
        return hash_hmac('sha256', $message->id . '|' . $this->getFilterId(), config('app.key'));
    }

    /**
     * Валидация callback токена
     */
    protected function validateCallbackToken(string $token, MessagesModel $message): bool
    {
        $expected = $this->generateCallbackToken($message);
        return hash_equals($expected, $token);
    }
}