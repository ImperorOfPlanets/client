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

        $updateData = [
            'response_data' => $response,
            'status' => $status,
            'metadata' => array_merge(
                $aiRequest->metadata ?? [],
                [
                    'service_used' => get_class($service),
                    'processed_at' => now()->toDateTimeString(),
                    'execution_time' => round(microtime(true) - LARAVEL_START, 3)
                ]
            )
        ];

        $aiRequest->update($updateData);
        Log::info("LLM request {$status}", ['request_id' => $aiRequest->id]);
        
        $this->processCallbacks($aiRequest, $response, $status);
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