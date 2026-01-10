<?php

namespace App\Jobs\AI;

use App\Models\Ai\AiRequest;
use App\Helpers\Ai\AiServiceLocator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLlmRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $requestId) {}

    public function handle()
    {
        Log::info('Starting LLM request job', ['request_id' => $this->requestId]);
        
        $aiRequest = AiRequest::findOrFail($this->requestId);
        
        if ($aiRequest->status === 'completed') {
            Log::warning('LLM request already completed', ['request_id' => $aiRequest->id]);
            return;
        }

        $aiRequest->update(['status' => 'processing']);

        try {
            $service = AiServiceLocator::getServiceById($aiRequest->service_id);
            
            if (!$service->supportRegulars()) {
                throw new \RuntimeException("Service {$service::getName()} doesn't support regular requests");
            }

            $response = $service->send($aiRequest->request_data);
            $this->finalizeRequest($aiRequest, $service, $response);

        } catch (\Throwable $e) {
            $this->handleFailure($aiRequest, $e);
        }
    }

    protected function finalizeRequest(AiRequest $aiRequest, $service, array $response)
    {
        $success = $response['success'] ?? false;
        $status = $success ? 'completed' : 'failed';

        // Определяем тип фильтра из metadata
        $metadata = $aiRequest->metadata ?? [];
        $filterType = $this->determineFilterType($metadata);
        
        // Парсим ответ специфично для фильтра
        $parsedResponse = [];
        if ($success && $filterType) {
            try {
                $parsedResponse = $service->parseFilterResponse($filterType, $response);
                
                // Объединяем оригинальный и распарсенный ответ
                $response['parsed'] = $parsedResponse;
            } catch (\Throwable $e) {
                Log::warning("Failed to parse filter response", [
                    'filter_type' => $filterType,
                    'service' => get_class($service),
                    'error' => $e->getMessage()
                ]);
            }
        }

        $updateData = [
            'response_data' => $response,
            'status' => $status,
            'metadata' => array_merge(
                $metadata,
                [
                    'service_used' => get_class($service),
                    'filter_type' => $filterType,
                    'parsed_response' => $parsedResponse,
                    'processed_at' => now()->toDateTimeString(),
                    'execution_time' => round(microtime(true) - LARAVEL_START, 3)
                ]
            )
        ];

        $aiRequest->update($updateData);
        Log::info("LLM request {$status}", [
            'request_id' => $aiRequest->id,
            'filter_type' => $filterType,
            'service' => get_class($service)
        ]);
        
        $this->processCallbacks($aiRequest, $response, $status);
    }

    /**
     * Определение типа фильтра из metadata
     */
    protected function determineFilterType(array $metadata): ?string
    {
        // Из callback (приоритет 1)
        $callback = $metadata['processing_callback'] ?? [];
        if (isset($callback['filter_class'])) {
            $classParts = explode('\\', $callback['filter_class']);
            $className = end($classParts);
            
            // Преобразуем CamelCase в snake_case для именования
            return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
        }
        
        // Из filter_id (приоритет 2) - можно добавить маппинг если нужно
        if (isset($metadata['filter_id'])) {
            return $metadata['filter_type'] ?? null;
        }
        
        return null;
    }

    protected function handleFailure(AiRequest $aiRequest, \Throwable $e)
    {
        $errorData = [
            'status' => 'failed',
            'response_data' => [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception' => get_class($e)
            ],
            'metadata' => array_merge(
                $aiRequest->metadata ?? [],
                [
                    'failed_at' => now()->toDateTimeString(),
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e)
                ]
            )
        ];

        $aiRequest->update($errorData);
        $this->fail($e);
    }

    protected function processCallbacks(AiRequest $aiRequest, array $response, string $status): void
    {
        $metadata = $aiRequest->metadata;
        $callback = $metadata['processing_callback'] ?? null;
        
        if (!$callback || $status !== 'completed') {
            return;
        }
        
        try {
            // Вызываем статический метод callback'а
            if (isset($callback['type']) && $callback['type'] === 'filter_completion') {
                $class = $callback['filter_class'] ?? null;
                $method = $callback['method'] ?? null;
                
                if ($class && $method && method_exists($class, $method)) {
                    call_user_func([$class, $method], $aiRequest->id, $response);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error processing AI request callback: ' . $e->getMessage(), [
                'ai_request_id' => $aiRequest->id,
                'callback' => $callback
            ]);
        }
    }
}