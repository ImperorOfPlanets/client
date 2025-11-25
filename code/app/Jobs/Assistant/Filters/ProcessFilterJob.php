<?php

namespace App\Jobs\Assistant\Filters;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use App\Models\Assistant\FiltersModel;
use App\Models\Ai\AiRequest;
use App\Jobs\AI\ProcessLlmRequest;

class ProcessFilterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $filterId,
        public int $messageId,
        public array $messageData,
        public ?string $callbackClass = null,
        public ?string $callbackMethod = null
    ) {}

    public function handle()
    {
        // Получаем настройки фильтра из JSON конфигурации
        $filterConfig = $this->getFilterConfig($this->filterId);
        
        if (!$filterConfig) {
            Log::error("ProcessFilter: конфигурация фильтра не найдена", ['filter_id' => $this->filterId]);
            return;
        }

        $type = $filterConfig['type'];
        $parameters = $filterConfig['parameters'] ?? [];

        Log::debug("Обработка фильтра", [
            'filter_id' => $this->filterId,
            'type' => $type,
            'parameters' => array_keys($parameters)
        ]);

        // Обрабатываем в зависимости от типа фильтра
        if ($type === 'prompt') {
            $prompt = $parameters['prompt'] ?? '';
            $this->processPromptFilter($filterConfig, $prompt);
        } elseif ($type === 'handler') {
            $handler = $parameters['handler'] ?? '';
            $this->processHandlerFilter($filterConfig, $handler);
        } else {
            Log::error("ProcessFilter: неизвестный тип фильтра", [
                'filter_id' => $this->filterId,
                'type' => $type
            ]);
        }
    }

    private function getFilterConfig(int $filterId): ?array
    {
        try {
            if (!Storage::disk('local')->exists('filters/filters.json')) {
                return null;
            }

            $filtersJson = Storage::disk('local')->get('filters/filters.json');
            $filters = json_decode($filtersJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            // Ищем фильтр по ID
            foreach ($filters as $filter) {
                if ($filter['id'] === $filterId) {
                    return $filter;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Ошибка получения конфигурации фильтра', [
                'filter_id' => $filterId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function processPromptFilter(array $filterConfig, string $promptTemplate)
    {
        try {
            // Формируем промпт с подстановкой данных
            $prompt = $this->compilePrompt($promptTemplate, $this->messageData);
            
            // Создаем запрос к LLM
            $aiRequest = AiRequest::create([
                'service_id' => 1,
                'request_data' => [
                    'prompt' => $prompt,
                    'temperature' => 0.7,
                    'max_tokens' => 1000
                ],
                'status' => 'pending',
                'metadata' => [
                    'filter_id' => $filterConfig['id'],
                    'filter_name' => $filterConfig['name'],
                    'message_id' => $this->messageId,
                    'message_data' => $this->messageData,
                    'processing_callback' => [
                        'type' => 'filter_completion',
                        'filter_class' => $this->callbackClass,
                        'method' => $this->callbackMethod
                    ]
                ]
            ]);

            ProcessLlmRequest::dispatch($aiRequest->id);

            Log::info("ProcessFilter: LLM запрос создан", [
                'filter_id' => $filterConfig['id'],
                'filter_name' => $filterConfig['name'],
                'ai_request_id' => $aiRequest->id
            ]);

        } catch (\Exception $e) {
            Log::error("ProcessFilter: ошибка создания LLM запроса", [
                'filter_id' => $filterConfig['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    // В методе processHandlerFilter:
    private function processHandlerFilter(array $filterConfig, string $handlerDefinition)
    {
        try {
            $handlerParts = explode('@', $handlerDefinition);
            
            if (count($handlerParts) !== 2) {
                Log::error("ProcessFilter: неверный формат handler", [
                    'filter_id' => $filterConfig['id'],
                    'handler' => $handlerDefinition
                ]);
                return;
            }

            $handlerClass = $handlerParts[0];
            $handlerMethod = $handlerParts[1];

            // Загружаем модель сообщения
            $message = \App\Models\Assistant\MessagesModel::find($this->messageId);
            
            if (!$message) {
                Log::error("ProcessFilter: сообщение не найдено", [
                    'filter_id' => $filterConfig['id'],
                    'message_id' => $this->messageId
                ]);
                return;
            }

            if (class_exists($handlerClass)) {
                // Проверяем, является ли класс фильтром (наследуется от Filter)
                $reflection = new \ReflectionClass($handlerClass);
                if ($reflection->isSubclassOf(\App\Filters\Filter::class)) {
                    // Для фильтров используем конструктор без параметров
                    $handler = new $handlerClass();
                } else {
                    // Для legacy-классов используем старый подход
                    $handler = new $handlerClass();
                }
                
                if (method_exists($handler, $handlerMethod)) {
                    // Передаем только MessagesModel
                    $result = $handler->$handlerMethod($message);
                    
                    // Добавляем filter_id в результат, если его там нет
                    if (!isset($result['filter_id']) && method_exists($handler, 'getFilterId')) {
                        $result['filter_id'] = $handler->getFilterId();
                    }
                    
                    // Вызываем callback с результатом
                    if ($this->callbackClass && $this->callbackMethod) {
                        call_user_func(
                            [$this->callbackClass, $this->callbackMethod],
                            $filterConfig['id'],
                            $this->messageId,
                            $result
                        );
                    }
                    
                    Log::info("ProcessFilter: handler выполнен", [
                        'filter_id' => $filterConfig['id'],
                        'filter_name' => $filterConfig['name'],
                        'handler' => $handlerDefinition
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("ProcessFilter: ошибка выполнения handler", [
                'filter_id' => $filterConfig['id'],
                'handler' => $handlerDefinition,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function compilePrompt(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $template = str_replace("{{$key}}", (string)$value, $template);
            }
        }
        return $template;
    }
}