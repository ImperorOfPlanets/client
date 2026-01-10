<?php

namespace App\Helpers\Ai\Services;

use App\Helpers\Ai\AiServices;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Exception;

class PolzaAiService extends AiServices
{
    public $id = 5;
    private string $baseUrl = 'https://api.polza.ai/api/v1';

    /**
     * Основной метод для отправки запросов к Polza.ai API
     */
    public function send(array $params): array
    {
        Log::info('PolzaAiService: Starting request', ['params' => $params]);
        $this->validateSettings();

        try {
            $apiKey = $this->getSetting('api_key');
            $defaultModel = $this->getSetting('default_model', 'deepseek/deepseek-r1-0528-qwen3-8b');

            // Оптимизируем запрос
            $optimizedParams = $this->optimizeRequest($params);
            
            $requestData = array_merge([
                'model' => $defaultModel,
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ], $optimizedParams);

            // Явно отключаем reasoning для всех запросов
            $requestData['reasoning'] = ['enabled' => false];

            // Преобразование prompt в messages если нужно
            if (isset($requestData['prompt']) && !isset($requestData['messages'])) {
                $requestData['messages'] = [
                    ['role' => 'user', 'content' => $requestData['prompt']]
                ];
                unset($requestData['prompt']);
            }

            $headers = ['Content-Type' => 'application/json'];
            
            if (isset($requestData['stream']) && $requestData['stream'] === true) {
                $headers['Accept'] = 'text/event-stream';
            }

            $response = Http::withHeaders($headers)
                ->withToken($apiKey)
                ->timeout(300)
                ->post("{$this->baseUrl}/chat/completions", $requestData);

            if (!$response->successful()) {
                throw new Exception(
                    "API request failed: {$response->status()} - {$response->body()}",
                    $response->status()
                );
            }

            $data = $response->json();

            Log::info('PolzaAiService: Successful response', [
                'model' => $requestData['model'],
                'status' => $response->status()
            ]);

            return $this->formatSuccessResponse($data, $requestData['model']);

        } catch (Exception $e) {
            Log::error('PolzaAiService: Request failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            return $this->handleError($e);
        }
    }

    /**
     * Получение эмбеддингов текста
     */
    public function getEmbedding(array $params): array
    {
        Log::info('PolzaAiService: Getting embeddings', ['params' => $params]);
        $this->validateSettings();

        try {
            $apiKey = $this->getSetting('api_key');
            $embeddingModel = $this->getSetting('embedding_model', 'text-embedding-3-large');

            $requestData = [
                'model' => $params['model'] ?? $embeddingModel,
                'input' => $params['text'] ?? $params['input'],
            ];

            if (isset($params['encoding_format'])) {
                $requestData['encoding_format'] = $params['encoding_format'];
            }

            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post("{$this->baseUrl}/embeddings", $requestData);

            if (!$response->successful()) {
                throw new Exception(
                    "Embeddings API failed: {$response->status()} - {$response->body()}",
                    $response->status()
                );
            }

            $data = $response->json();
            $embeddings = $data['data'] ?? [];

            Log::info('PolzaAiService: Embeddings generated', [
                'model' => $requestData['model'],
                'count' => count($embeddings)
            ]);

            return $this->formatEmbeddingResponse($embeddings, $requestData['model']);

        } catch (Exception $e) {
            Log::error('PolzaAiService: Embedding generation failed', [
                'error' => $e->getMessage()
            ]);
            
            return $this->handleError($e);
        }
    }

    /**
     * Генерация изображений
     */
    public function generateImage(array $params): array
    {
        Log::info('PolzaAiService: Generating image', ['params' => $params]);
        $this->validateSettings();

        try {
            $apiKey = $this->getSetting('api_key');
            $imageModel = $this->getSetting('image_model', 'nano-banana');

            $requestData = array_merge([
                'model' => $imageModel,
            ], $params);

            $response = Http::withToken($apiKey)
                ->timeout(300)
                ->post("{$this->baseUrl}/images/generations", $requestData);

            if (!$response->successful()) {
                throw new Exception(
                    "Image generation failed: {$response->status()} - {$response->body()}",
                    $response->status()
                );
            }

            $data = $response->json();
            $requestId = $data['requestId'] ?? null;

            Log::info('PolzaAiService: Image generation started', [
                'request_id' => $requestId,
                'model' => $requestData['model']
            ]);

            return $this->formatSuccessResponse([
                'request_id' => $requestId,
                'status' => 'processing'
            ], $requestData['model'], [
                'provider' => 'Polza.ai'
            ]);

        } catch (Exception $e) {
            Log::error('PolzaAiService: Image generation failed', [
                'error' => $e->getMessage()
            ]);
            
            return $this->handleError($e);
        }
    }

    /**
     * Проверка статуса генерации изображения
     */
    public function getImageStatus(string $requestId): array
    {
        Log::info('PolzaAiService: Checking image status', ['request_id' => $requestId]);
        $this->validateSettings();

        try {
            $apiKey = $this->getSetting('api_key');

            $response = Http::withToken($apiKey)
                ->get("{$this->baseUrl}/images/{$requestId}");

            if (!$response->successful()) {
                throw new Exception(
                    "Status check failed: {$response->status()} - {$response->body()}",
                    $response->status()
                );
            }

            $data = $response->json();

            return $this->formatSuccessResponse([
                'status' => $data['status'] ?? 'unknown',
                'url' => $data['url'] ?? null
            ], '', [
                'request_id' => $requestId,
                'provider' => 'Polza.ai'
            ]);

        } catch (Exception $e) {
            Log::error('PolzaAiService: Status check failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage()
            ]);
            
            return $this->handleError($e);
        }
    }

    /**
     * Обработка потокового ответа
     */
    public function handleStream(Response $response): \Generator
    {
        $stream = $response->body();
        
        foreach (explode("\n", $stream) as $chunk) {
            if (strpos($chunk, 'data: ') === 0) {
                $data = substr($chunk, 6);
                
                if ($data === '[DONE]') {
                    break;
                }
                
                $decoded = json_decode($data, true);
                if ($decoded && isset($decoded['choices'][0]['delta']['content'])) {
                    yield $decoded['choices'][0]['delta']['content'];
                }
            }
        }
    }

    /**
     * Создание мультимодального сообщения
     */
    public function createMultimodalMessage(
        string $text, 
        array $imageUrls = [], 
        string $detail = 'auto'
    ): array {
        $content = [['type' => 'text', 'text' => $text]];
        
        foreach ($imageUrls as $url) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $url,
                    'detail' => $detail
                ]
            ];
        }
        
        return [
            'role' => 'user',
            'content' => $content
        ];
    }

    public static function getName(): string
    {
        return 'PolzaAi';
    }

    public static function getRequiredSettings(): array
    {
        return [
            [
                'key' => 'api_key',
                'label' => 'API Key',
                'description' => 'Ваш API ключ от Polza.ai',
                'required' => true
            ],
            [
                'key' => 'default_model',
                'label' => 'Модель по умолчанию',
                'description' => 'Модель для чат-запросов',
                'required' => false,
                'default' => 'openai/gpt-4o'
            ],
            [
                'key' => 'embedding_model',
                'label' => 'Модель для эмбеддингов',
                'description' => 'Модель для векторных представлений',
                'required' => false,
                'default' => 'text-embedding-3-large'
            ],
            [
                'key' => 'image_model',
                'label' => 'Модель для изображений',
                'description' => 'Модель для генерации изображений',
                'required' => false,
                'default' => 'nano-banana'
            ]
        ];
    }

    /**
     * Получение баланса аккаунта
     */
    public function feature_balance(): array
    {
        Log::info('PolzaAiService: Getting balance via feature');
        $this->validateSettings();

        try {
            $apiKey = $this->getSetting('api_key');

            $response = Http::withToken($apiKey)
                ->get("{$this->baseUrl}/balance");

            if (!$response->successful()) {
                throw new Exception(
                    "Balance check failed: {$response->status()} - {$response->body()}",
                    $response->status()
                );
            }

            $data = $response->json();

            return $this->formatSuccessResponse([
                'balance' => $data['amount'] ?? '0',
                'spent' => $data['spentAmount'] ?? '0',
                'currency' => 'RUB'
            ], '', [
                'provider' => 'Polza.ai',
                'timestamp' => now()->toISOString()
            ]);

        } catch (Exception $e) {
            Log::error('PolzaAiService: Balance check failed', [
                'error' => $e->getMessage()
            ]);
            
            return $this->handleError($e);
        }
    }

    /**
     * Получение списка доступных моделей с полной информацией о ценах
     */
    public function feature_models(): array
    {
        Log::info('PolzaAiService: Getting available models with full pricing data');

        try {
            $response = Http::get("{$this->baseUrl}/models");

            if (!$response->successful()) {
                throw new Exception(
                    "Models fetch failed: {$response->status()} - {$response->body()}",
                    $response->status()
                );
            }

            $data = $response->json();
            
            // Логируем полный результат от API
            Log::info('PolzaAiService: Raw API response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $data
            ]);

            $models = $data['data'] ?? [];
            Log::info('PolzaAiService: Retrieved models count', ['count' => count($models)]);

            // Дополнительное логирование структуры первой модели для отладки
            if (!empty($models)) {
                Log::info('PolzaAiService: First model structure sample', [
                    'first_model' => $models[0]
                ]);
            }

            // Обрабатываем каждую модель с полными данными из API
            $processedModels = [];
            foreach ($models as $model) {
                $provider = explode('/', $model['id'])[0] ?? 'other';
                
                // Добавляем цены за миллион токенов
                $pricingPerMillion = [];
                foreach ($model['pricing'] ?? [] as $key => $value) {
                    $pricePerToken = floatval($value);
                    $pricePerMillion = $pricePerToken * 1000000;
                    $pricingPerMillion[$key] = number_format($pricePerMillion, 10, '.', '');
                    $pricingPerMillion[$key . '_formatted'] = number_format($pricePerMillion, 10) . ' руб/Мтокен';
                }
                
                $processedModels[] = [
                    // Основные поля модели
                    'id' => $model['id'],
                    'canonical_slug' => $model['canonical_slug'] ?? '',
                    'name' => $model['name'] ?? $model['id'],
                    'created' => $model['created'] ?? 0,
                    'context_length' => $model['context_length'] ?? 0,
                    
                    // Архитектура модели
                    'architecture' => [
                        'input_modalities' => $model['architecture']['input_modalities'] ?? [],
                        'output_modalities' => $model['architecture']['output_modalities'] ?? [],
                        'tokenizer' => $model['architecture']['tokenizer'] ?? '',
                        'instruct_type' => $model['architecture']['instruct_type'] ?? '',
                    ],
                    
                    // Цены за токен (оригинальные значения из API)
                    'pricing' => [
                        'prompt' => $model['pricing']['prompt'] ?? '0',
                        'completion' => $model['pricing']['completion'] ?? '0',
                        'image' => $model['pricing']['image'] ?? '0',
                        'request' => $model['pricing']['request'] ?? '0',
                        'web_search' => $model['pricing']['web_search'] ?? '0',
                        'internal_reasoning' => $model['pricing']['internal_reasoning'] ?? '0',
                        'input_cache_read' => $model['pricing']['input_cache_read'] ?? '0',
                        'input_cache_write' => $model['pricing']['input_cache_write'] ?? '0',
                    ],
                    
                    // Цены за миллион токенов
                    'pricing_per_million' => $pricingPerMillion,
                    
                    // Информация о лучшем провайдере
                    'top_provider' => [
                        'context_length' => $model['top_provider']['context_length'] ?? 0,
                        'max_completion_tokens' => $model['top_provider']['max_completion_tokens'] ?? 0,
                        'is_moderated' => $model['top_provider']['is_moderated'] ?? false,
                    ],
                    
                    // Дополнительные характеристики
                    'per_request_limits' => $model['per_request_limits'] ?? null,
                    'supported_parameters' => $model['supported_parameters'] ?? [],
                    
                    // Дополнительное поле для группировки
                    'provider' => $provider,
                ];
            }

            // Логируем обработанные модели
            Log::info('PolzaAiService: Processed models count', [
                'processed_count' => count($processedModels),
                'providers_found' => array_unique(array_column($processedModels, 'provider'))
            ]);

            // Группируем модели по провайдеру для удобства
            $groupedModels = [];
            foreach ($processedModels as $model) {
                $groupedModels[$model['provider']][] = $model;
            }

            // Сортируем модели внутри каждой группы по цене (prompt tokens)
            foreach ($groupedModels as &$providerModels) {
                usort($providerModels, function($a, $b) {
                    $priceA = floatval($a['pricing']['prompt'] ?? 0);
                    $priceB = floatval($b['pricing']['prompt'] ?? 0);
                    return $priceA <=> $priceB;
                });
            }

            // Логируем финальный результат перед возвратом
            Log::info('PolzaAiService: Final grouped models structure', [
                'providers' => array_keys($groupedModels),
                'models_per_provider' => array_map('count', $groupedModels),
                'total_models' => count($processedModels)
            ]);

            return $this->formatSuccessResponse([
                'models' => $groupedModels,
                'models_flat' => $processedModels, // Полный плоский список для поиска
                'total_count' => count($models),
                'providers' => array_keys($groupedModels),
            ], 'Models retrieved successfully', [
                'provider' => 'Polza.ai',
                'timestamp' => now()->toISOString(),
                'pricing_currency' => 'RUB',
                'pricing_unit' => 'per_token',
                'pricing_precision' => 10, // До 10 знаков после запятой
            ]);

        } catch (Exception $e) {
            Log::error('PolzaAiService: Models fetch failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->handleError($e);
        }
    }

    /**
     * Проверка статуса API
     */
    public function feature_status(): array
    {
        Log::info('PolzaAiService: Checking API status via feature');

        try {
            $startTime = microtime(true);
            
            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/models");

            $responseTime = microtime(true) - $startTime;

            return $this->formatSuccessResponse([
                'status' => 'operational',
                'response_time' => round($responseTime, 3)
            ], '', [
                'provider' => 'Polza.ai',
                'timestamp' => now()->toISOString(),
                'api_version' => 'v1'
            ]);

        } catch (Exception $e) {
            return $this->formatSuccessResponse([
                'status' => 'unavailable',
                'error' => $e->getMessage()
            ], '', [
                'provider' => 'Polza.ai',
                'timestamp' => now()->toISOString()
            ]);
        }
    }

    /**
     * Получение статистики использования
     */
    public function feature_usage(array $params = []): array
    {
        Log::info('PolzaAiService: Getting usage statistics via feature');
        $this->validateSettings();

        try {
            $apiKey = $this->getSetting('api_key');
            $days = $params['days'] ?? 7;

            $response = Http::withToken($apiKey)
                ->get("{$this->baseUrl}/usage", [
                    'days' => $days
                ]);

            if (!$response->successful()) {
                throw new Exception(
                    "Usage stats failed: {$response->status()} - {$response->body()}",
                    $response->status()
                );
            }

            $data = $response->json();

            return $this->formatSuccessResponse([
                'usage' => $data
            ], '', [
                'provider' => 'Polza.ai',
                'period_days' => $days,
                'timestamp' => now()->toISOString()
            ]);

        } catch (Exception $e) {
            Log::error('PolzaAiService: Usage stats failed', [
                'error' => $e->getMessage()
            ]);
            
            return $this->handleError($e);
        }
    }

    /**
     * Переопределение оптимизации запросов для PolzaAI
     */
    public function getOptimizationParams(): array
    {
        return [
            'max_tokens' => 500,
            'temperature' => 0.7,
            'reasoning' => ['enabled' => false],
            'stream' => false,
        ];
    }

    /**
     * Применение контроля кеширования для PolzaAI
     */
    protected function applyCacheControl(array $messages): array
    {
        foreach ($messages as &$message) {
            if ($message['role'] === 'system' && is_string($message['content'])) {
                if (strlen($message['content']) > 100) {
                    $message['content'] = [
                        [
                            'type' => 'text',
                            'text' => $message['content'],
                            'cache_control' => ['type' => 'ephemeral']
                        ]
                    ];
                }
            }
        }

        return $messages;
    }

    /**
     * Форматирование ответа с эмбеддингами
     */
    private function formatEmbeddingResponse(array $embeddings, string $model): array
    {
        if (count($embeddings) === 1) {
            $embedding = $embeddings[0];
            return $this->formatSuccessResponse([
                'embedding' => $embedding['embedding'] ?? [],
                'vector_id' => 'polza_' . md5(json_encode($embedding['embedding'] ?? ''))
            ], $model, [
                'dimensions' => count($embedding['embedding'] ?? []),
                'provider' => 'Polza.ai',
                'index' => $embedding['index'] ?? 0
            ]);
        }

        $results = [];
        foreach ($embeddings as $embedding) {
            $results[] = $this->formatSuccessResponse([
                'embedding' => $embedding['embedding'] ?? [],
                'vector_id' => 'polza_' . md5(json_encode($embedding['embedding'] ?? ''))
            ], $model, [
                'dimensions' => count($embedding['embedding'] ?? []),
                'provider' => 'Polza.ai',
                'index' => $embedding['index'] ?? 0
            ]);
        }

        return $results;
    }

    /**
     * Переопределение форматирования успешного ответа
     */
    protected function formatSuccessResponse(array $data, string $model, array $meta = []): array
    {
        // Для чат-запросов обрабатываем reasoning
        if (isset($data['choices'])) {
            $choice = $data['choices'][0] ?? [];
            $message = $choice['message'] ?? [];
            $usage = $data['usage'] ?? [];

            $content = $message['content'] ?? '';
            
            // Очистка от reasoning
            if (!empty($message['reasoning'])) {
                Log::warning('PolzaAI вернул reasoning несмотря на отключение', [
                    'model' => $model,
                    'reasoning_length' => strlen($message['reasoning'])
                ]);
            }

            $responseData = [
                'data' => $content,
                'reasoning' => null,
                'tool_calls' => $message['tool_calls'] ?? null,
            ];

            $defaultMeta = [
                'model' => $model,
                'provider' => 'Polza.ai',
                'finish_reason' => $choice['finish_reason'] ?? null,
                'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                'completion_tokens' => $usage['completion_tokens'] ?? 0,
                'total_tokens' => $usage['total_tokens'] ?? 0,
                'cost' => $usage['cost'] ?? 0,
                'reasoning_tokens' => 0
            ];
        } else {
            // Для других типов запросов
            $responseData = $data;
            $defaultMeta = [
                'model' => $model,
                'provider' => 'Polza.ai'
            ];
        }

        return parent::formatSuccessResponse(
            $responseData, 
            $model, 
            array_merge($defaultMeta, $meta)
        );
    }

    /**
     * Парсинг ответа для команд (уже есть - переименуем для соответствия паттерну)
     */
    public function parseCommandsResponse(array $response): array
    {
        Log::info('PolzaAiService: Парсинг ответа для командного фильтра', [
            'response_keys' => array_keys($response),
            'has_data' => isset($response['data'])
        ]);

        $result = [
            'success' => false,
            'is_command' => false,
            'command_id' => null,
            'found_keyword' => null,
            'found_in_part' => null,
            'confidence' => null,
            'raw_text' => '',
            'parsed_data' => null,
            'filter_type' => 'commands',
            'provider' => self::getName()
        ];

        try {
            // Проверяем успешность запроса
            if (!isset($response['success']) || $response['success'] !== true) {
                Log::warning('PolzaAI ответ не успешен', [
                    'success' => $response['success'] ?? 'unknown'
                ]);
                return $result;
            }

            // Извлекаем текст из структуры Polza AI
            $textResponse = '';
            
            // Структура 1: {"data": {"data": "текст"}}
            if (isset($response['data']['data']) && is_string($response['data']['data'])) {
                $textResponse = $response['data']['data'];
            }
            // Структура 2: {"data": "текст"}
            elseif (isset($response['data']) && is_string($response['data'])) {
                $textResponse = $response['data'];
            }
            // Структура 3: вложенные массивы
            elseif (isset($response['data'][0]['content']) && is_string($response['data'][0]['content'])) {
                $textResponse = $response['data'][0]['content'];
            }
            // Структура 4: choices format
            elseif (isset($response['choices'][0]['message']['content'])) {
                $textResponse = $response['choices'][0]['message']['content'];
            }

            if (empty($textResponse)) {
                Log::warning('Не удалось извлечь текст из ответа PolzaAI', [
                    'response_structure' => $response
                ]);
                return $result;
            }

            $result['raw_text'] = $textResponse;
            $result['success'] = true;

            // Парсим JSON из текста
            $parsedData = $this->parseJsonFromText($textResponse);
            
            if ($parsedData) {
                $result['parsed_data'] = $parsedData;
                $result['is_command'] = $parsedData['is_command'] ?? false;
                $result['command_id'] = $parsedData['command_id'] ?? null;
                $result['found_keyword'] = $parsedData['found_keyword'] ?? null;
                $result['found_in_part'] = $parsedData['found_in_part'] ?? null;
                $result['confidence'] = $parsedData['confidence'] ?? null;
            }

            Log::info('PolzaAiService: Результат парсинга команд', [
                'is_command' => $result['is_command'],
                'command_id' => $result['command_id'],
                'confidence' => $result['confidence'],
                'text_length' => strlen($textResponse)
            ]);

            return $result;

        } catch (\Throwable $e) {
            Log::error('PolzaAiService: Ошибка парсинга ответа для команд', [
                'error' => $e->getMessage(),
                'response' => $response
            ]);
            
            $result['success'] = false;
            return $result;
        }
    }

    /**
     * Парсинг ответа для генерации приветствий
     */
    public function parseGreetingResponse(array $response): array
    {
        Log::info('PolzaAiService: Парсинг ответа для генератора приветствий');
        
        $result = [
            'success' => false,
            'text' => '',
            'filter_type' => 'greeting',
            'provider' => self::getName()
        ];

        try {
            if (!isset($response['success']) || $response['success'] !== true) {
                return $result;
            }

            // Извлекаем текст приветствия
            $text = $this->extractGreetingText($response);
            
            if (!empty($text)) {
                $result['success'] = true;
                $result['text'] = $text;
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error('PolzaAiService: Ошибка парсинга приветствия', [
                'error' => $e->getMessage()
            ]);
            return $result;
        }
    }

    /**
     * Парсинг ответа для политического фильтра
     */
    public function parsePoliticalResponse(array $response): array
    {
        Log::info('PolzaAiService: Парсинг ответа для политического фильтра');
        
        $result = [
            'success' => false,
            'approved' => true,
            'confidence' => null,
            'risk_level' => 'low',
            'filter_type' => 'political',
            'provider' => self::getName()
        ];

        try {
            if (!isset($response['success']) || $response['success'] !== true) {
                return $result;
            }

            // Извлекаем и парсим JSON с политическим анализом
            $textResponse = $this->extractTextFromResponse($response);
            $parsedData = $this->parseJsonFromText($textResponse);
            
            if ($parsedData) {
                $result['success'] = true;
                $result['approved'] = $parsedData['approved'] ?? true;
                $result['confidence'] = $parsedData['confidence'] ?? null;
                $result['risk_level'] = $parsedData['risk_level'] ?? 'low';
                $result['analysis'] = $parsedData;
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error('PolzaAiService: Ошибка парсинга политического ответа', [
                'error' => $e->getMessage()
            ]);
            return $result;
        }
    }

    /**
     * Вспомогательный метод для извлечения текста приветствия
     */
    private function extractGreetingText(array $response): string
    {
        $text = '';
        
        // Аналогично методу extractGreetingFromAiResponse из GreetingGenerator
        if (isset($response['data']) && is_string($response['data'])) {
            $text = $response['data'];
        } 
        elseif (isset($response['data']) && is_array($response['data'])) {
            $data = $response['data'];
            
            if (isset($data['data']) && is_string($data['data'])) {
                $text = $data['data'];
            }
            else {
                $text = 
                    $data['text'] ??
                    $data['response'] ??
                    $data['choices'][0]['message']['content'] ??
                    $data['choices'][0]['text'] ??
                    '';
            }
        }
        else {
            $text =
                $response['text'] ??
                $response['response'] ??
                $response['choices'][0]['message']['content'] ??
                $response['choices'][0]['text'] ??
                '';
        }

        // Очистка текста
        $cleanText = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/m', '', $text);
        $cleanText = preg_replace('/^(Ответ|Приветствие|ПРИВЕТСТВИЕ)[:\s]*/i', '', $cleanText);
        
        return trim($cleanText);
    }

    /**
     * Вспомогательный метод для извлечения текста из ответа
     */
    private function extractTextFromResponse(array $response): string
    {
        if (isset($response['data']) && is_string($response['data'])) {
            return $response['data'];
        }
        elseif (isset($response['data']['data']) && is_string($response['data']['data'])) {
            return $response['data']['data'];
        }
        elseif (isset($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        }
        
        return '';
    }