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
     * Получение списка доступных моделей
     */
    public function feature_models(): array
    {
        Log::info('PolzaAiService: Getting available models via feature');

        try {
            $response = Http::get("{$this->baseUrl}/models");

            if (!$response->successful()) {
                throw new Exception(
                    "Models fetch failed: {$response->status()} - {$response->body()}",
                    $response->status()
                );
            }

            $data = $response->json();
            $models = $data['data'] ?? [];

            // Группируем модели по провайдеру
            $groupedModels = [];
            foreach ($models as $model) {
                $provider = explode('/', $model['id'])[0] ?? 'other';
                $groupedModels[$provider][] = [
                    'id' => $model['id'],
                    'name' => $model['name'] ?? $model['id'],
                    'description' => $model['description'] ?? '',
                ];
            }

            return $this->formatSuccessResponse([
                'models' => $groupedModels,
                'total_count' => count($models)
            ], '', [
                'provider' => 'Polza.ai',
                'timestamp' => now()->toISOString()
            ]);

        } catch (Exception $e) {
            Log::error('PolzaAiService: Models fetch failed', [
                'error' => $e->getMessage()
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
}