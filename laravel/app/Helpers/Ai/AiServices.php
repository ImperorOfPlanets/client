<?php

namespace App\Helpers\Ai;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;

abstract class AiServices
{
    protected array $settings = [];
    protected array $capabilities = [];
    protected bool $capabilitiesLoaded = false;

    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
        $this->loadCapabilities();
    }

    /**
     * Основной метод для отправки запросов к AI сервису
     */
    abstract public function send(array $params): array;

    /**
     * Получение уникального имени сервиса
     */
    abstract public static function getName(): string;

    /**
     * Получение необходимых настроек для сервиса
     */
    abstract public static function getRequiredSettings(): array;

    /**
     * Загрузка и кеширование возможностей сервиса
     */
    protected function loadCapabilities(): void
    {
        $cacheKey = "ai_service_capabilities_" . static::getName();
        
        $this->capabilities = Cache::remember($cacheKey, 3600, function() {
            return $this->detectCapabilities();
        });
        
        $this->capabilitiesLoaded = true;
    }

    /**
     * Автоматическое определение возможностей сервиса
     */
    protected function detectCapabilities(): array
    {
        return [
            'chat_completions' => method_exists($this, 'send'),
            'embeddings' => method_exists($this, 'getEmbedding'),
            'image_generation' => method_exists($this, 'generateImage'),
            'audio_processing' => method_exists($this, 'processAudio'),
            'vision' => method_exists($this, 'analyzeImage'),
            'streaming' => $this->detectStreamingSupport(),
            'caching' => $this->detectCachingSupport(),
            'reasoning' => $this->detectReasoningSupport(),
            'tool_calling' => $this->detectToolCallingSupport(),
            'multimodal' => $this->detectMultimodalSupport(),
            'batch_processing' => $this->detectBatchProcessingSupport(),
            'fine_tuning' => $this->detectFineTuningSupport(),
        ];
    }

    /**
     * Проверка поддержки потоковой передачи
     */
    protected function detectStreamingSupport(): bool
    {
        return method_exists($this, 'handleStream');
    }

    /**
     * Проверка поддержки кеширования
     */
    protected function detectCachingSupport(): bool
    {
        return method_exists($this, 'addCacheControl') || 
               method_exists($this, 'optimizeMessages');
    }

    /**
     * Проверка поддержки reasoning токенов
     */
    protected function detectReasoningSupport(): bool
    {
        return method_exists($this, 'enableReasoning') ||
               method_exists($this, 'disableReasoning');
    }

    /**
     * Проверка поддержки вызова инструментов
     */
    protected function detectToolCallingSupport(): bool
    {
        return method_exists($this, 'callTool') ||
               method_exists($this, 'handleToolResponse');
    }

    /**
     * Проверка поддержки мультимодальности
     */
    protected function detectMultimodalSupport(): bool
    {
        return method_exists($this, 'createMultimodalMessage') ||
               method_exists($this, 'processMultimodalInput');
    }

    /**
     * Проверка поддержки пакетной обработки
     */
    protected function detectBatchProcessingSupport(): bool
    {
        return method_exists($this, 'processBatch');
    }

    /**
     * Проверка поддержки дообучения моделей
     */
    protected function detectFineTuningSupport(): bool
    {
        return method_exists($this, 'createFineTuningJob') ||
               method_exists($this, 'getFineTuningStatus');
    }

    /**
     * Получение информации о всех возможностях сервиса
     */
    public function getCapabilities(): array
    {
        if (!$this->capabilitiesLoaded) {
            $this->loadCapabilities();
        }
        
        return $this->capabilities;
    }

    /**
     * Проверка поддержки конкретной возможности
     */
    public function supports(string $capability): bool
    {
        $capabilities = $this->getCapabilities();
        return $capabilities[$capability] ?? false;
    }

    /**
     * Получение рекомендуемых параметров для оптимизации
     */
    public function getOptimizationParams(): array
    {
        return [
            'max_tokens' => 500,
            'temperature' => 0.7,
        ];
    }

    /**
     * Оптимизация запроса на основе возможностей сервиса
     */
    public function optimizeRequest(array $params): array
    {
        $optimized = $params;
        $capabilities = $this->getCapabilities();

        // Применяем базовые оптимизации
        $optimizationParams = $this->getOptimizationParams();
        $optimized = array_merge($optimizationParams, $optimized);

        // Используем кеширование если поддерживается
        if ($capabilities['caching'] && isset($optimized['messages'])) {
            $optimized['messages'] = $this->applyCacheControl($optimized['messages']);
        }

        return $optimized;
    }

    /**
     * Применение контроля кеширования к сообщениям
     */
    protected function applyCacheControl(array $messages): array
    {
        // Базовая реализация - может быть переопределена в конкретных сервисах
        return $messages;
    }

    /**
     * Валидация ответа от сервиса
     */
    protected function validateResponse(array $response): bool
    {
        return isset($response['success']) && $response['success'] === true;
    }

    /**
     * Обработка ошибок сервиса
     */
    protected function handleError(\Exception $e): array
    {
        Log::error('AI Service Error', [
            'service' => static::getName(),
            'error' => $e->getMessage(),
            'code' => $e->getCode()
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'service' => static::getName()
        ];
    }

    /**
     * Форматирование успешного ответа
     */
    protected function formatSuccessResponse(array $data, string $model, array $meta = []): array
    {
        $defaultMeta = [
            'model' => $model,
            'provider' => static::getName(),
            'timestamp' => now()->toISOString(),
        ];

        return [
            'success' => true,
            'data' => $data,
            'meta' => array_merge($defaultMeta, $meta)
        ];
    }

    /**
     * Проверка поддержки эмбеддингов
     */
    public function supportEmbeddings(): bool
    {
        return $this->supports('embeddings');
    }

    /**
     * Проверка поддержки обычных запросов
     */
    public function supportRegulars(): bool
    {
        return $this->supports('chat_completions');
    }

    /**
     * Проверка поддержки генерации изображений
     */
    public function supportImageGeneration(): bool
    {
        return $this->supports('image_generation');
    }

    /**
     * Проверка поддержки потоковой передачи
     */
    public function supportStreaming(): bool
    {
        return $this->supports('streaming');
    }

    /**
     * Получение списка дополнительных функций сервиса
     */
    public static function getAvailableFeatures(): array
    {
        $features = [];
        $reflection = new \ReflectionClass(static::class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $methodName = $method->getName();
            
            if (str_starts_with($methodName, 'feature_')) {
                $featureName = substr($methodName, 8);
                $docComment = $method->getDocComment() ?: '';
                
                $features[$featureName] = [
                    'method' => $methodName,
                    'description' => self::extractDescriptionFromDoc($docComment),
                    'parameters' => self::extractParametersFromMethod($method),
                ];
            }
        }

        return $features;
    }

    /**
     * Извлечение описания из DocComment
     */
    private static function extractDescriptionFromDoc(string $docComment): string
    {
        if (preg_match('/\/\*\*\s*\*\s*(.*?)(?:\s*\*\/|\s*\*\\n)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }
        
        return 'Функция сервиса ' . static::getName();
    }

    /**
     * Извлечение параметров из метода
     */
    private static function extractParametersFromMethod(ReflectionMethod $method): array
    {
        $parameters = [];
        
        foreach ($method->getParameters() as $param) {
            $paramName = $param->getName();
            $paramType = $param->getType();
            
            $parameters[$paramName] = [
                'type' => $paramType ? $paramType->getName() : 'mixed',
                'required' => !$param->isOptional(),
                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
            ];
        }
        
        return $parameters;
    }

    /**
     * Вызов дополнительной функции сервиса
     */
    public function callFeature(string $featureName, array $parameters = []): array
    {
        $methodName = 'feature_' . $featureName;
        
        if (!method_exists($this, $methodName)) {
            return [
                'success' => false,
                'error' => "Функция '{$featureName}' не найдена в сервисе " . static::getName(),
            ];
        }

        try {
            Log::debug('Вызов функции сервиса', [
                'service' => static::getName(),
                'feature' => $featureName,
                'parameters' => array_keys($parameters)
            ]);

            $result = $this->$methodName($parameters);

            if (!isset($result['success'])) {
                $result['success'] = true;
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Ошибка вызова функции сервиса', [
                'service' => static::getName(),
                'feature' => $featureName,
                'error' => $e->getMessage()
            ]);

            return $this->handleError($e);
        }
    }

    /**
     * Получение настроек сервиса
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Получение всех настроек сервиса
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Валидация настроек сервиса
     */
    public function validateSettings(): bool
    {
        $requiredSettings = static::getRequiredSettings();
        
        foreach ($requiredSettings as $setting) {
            if (($setting['required'] ?? false) && empty($this->settings[$setting['key']])) {
                throw new \RuntimeException("Отсутствует обязательная настройка: {$setting['key']}");
            }
        }
        
        return true;
    }

    /**
     * Очистка кеша возможностей сервиса
     */
    public function clearCapabilitiesCache(): void
    {
        $cacheKey = "ai_service_capabilities_" . static::getName();
        Cache::forget($cacheKey);
        $this->capabilitiesLoaded = false;
        $this->loadCapabilities();
    }

    /**
     * Получение информации о сервисе
     */
    public function getServiceInfo(): array
    {
        return [
            'name' => static::getName(),
            'capabilities' => $this->getCapabilities(),
            'features' => static::getAvailableFeatures(),
            'settings_configured' => !empty($this->settings),
        ];
    }
}