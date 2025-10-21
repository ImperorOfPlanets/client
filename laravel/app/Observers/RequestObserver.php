<?php

namespace App\Observers;

use App\Models\Ai\AiRequest;
use App\Jobs\AI\ProcessLlmRequest;
use App\Jobs\AI\ProcessEmbedding;
use App\Jobs\Assistant\Messages\ProcessingResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use App\Models\Assistant\MessagesModel;

class RequestObserver
{
    // ------------------------------------------------- СОЗДАНИЕ -----------------------------------//

    public function created(AiRequest $request)
    {
        Log::info("Новый запрос! Добавляем в очередь", [
            'request_id' => $request->id
        ]);

        try {
            // Определяем тип запроса: сначала проверяем поле type, затем metadata
            $type = $request->type ?? $request->metadata['type'] ?? 'llm';

            Log::info('Определение типа AI запроса', [
                'ai_request_id' => $request->id,
                'field_type' => $request->type,
                'metadata_type' => $request->metadata['type'] ?? null,
                'final_type' => $type
            ]);

            // Определяем job в зависимости от типа запроса
            $job = match($type) {
                'llm' => new ProcessLlmRequest($request->id),
                'embedding' => new ProcessEmbedding($request->id),
                default => throw new \Exception("Unknown AI request type: {$type}")
            };

            // Настраиваем очередь и задержку
            $job->onQueue('ai-requests')->delay(now()->addSeconds(5));

            // Отправляем задание в очередь и получаем его ID
            $jobId = Queue::push($job);

            // Обновляем запись в базе данных
            $request->update([
                'job_id' => $jobId
            ]);

            Log::info('Job dispatched', [
                'ai_request_id' => $request->id,
                'job_id' => $jobId
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to dispatch job', [
                'error' => $e->getMessage(),
                'ai_request_id' => $request->id
            ]);
        }
    }

    // ------------------------------------------------- ОБНОВЛЕНИЕ -----------------------------------//

    public function updated(AiRequest $aiRequest): void
    {
        // Обрабатываем только завершенные/ошибочные запросы
        if (!in_array($aiRequest->status, ['completed', 'failed'])) {
            return;
        }

        // Проверяем, что статус именно изменился
        if (!$aiRequest->isDirty('status')) {
            return;
        }

        Log::debug('AiRequest status changed to completed/failed', [
            'request_id' => $aiRequest->id,
            'status' => $aiRequest->status,
            'previous_status' => $aiRequest->getOriginal('status')
        ]);

        try {
            // Получаем ID сообщения из metadata
            $metadata = $aiRequest->metadata;
            $messageId = $metadata['message_id'] ?? null;
            $filterId = $metadata['filter_id'] ?? null;

            if (!$messageId || !$filterId) {
                Log::warning('Missing message_id or filter_id in metadata', [
                    'request_id' => $aiRequest->id,
                    'metadata' => $metadata
                ]);
                return;
            }

            $message = MessagesModel::find($messageId);
            if (!$message) {
                Log::error('Message not found for AI request', [
                    'request_id' => $aiRequest->id,
                    'message_id' => $messageId
                ]);
                return;
            }

            // 🔥 АВТОМАТИЧЕСКИ СОЗДАЁМ ProcessingResult JOB
            $this->dispatchProcessingResultJob($aiRequest, $message, $filterId);

            Log::info('ProcessingResult job dispatched for completed AI request', [
                'message_id' => $message->id,
                'filter_id' => $filterId,
                'request_id' => $aiRequest->id,
                'status' => $aiRequest->status
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to process AI request update in observer', [
                'request_id' => $aiRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Автоматически создает и запускает job ProcessingResult
     */
    protected function dispatchProcessingResultJob(AiRequest $aiRequest, MessagesModel $message, $filterId): void
    {
        $params = [
            'message_id' => $message->id,
            'filter_id' => $filterId,
            'request_id' => $aiRequest->id,
            'status' => $aiRequest->status,
            'result' => $aiRequest->response_data,
            'metadata' => $aiRequest->metadata,
            'service_id' => $aiRequest->service_id,
            'processed_at' => now()->toDateTimeString()
        ];

        // Если статус failed, добавляем информацию об ошибке
        if ($aiRequest->status === 'failed') {
            $params['error'] = $aiRequest->response_data['error'] ?? 'Unknown error';
        }

        // Запускаем job
        ProcessingResult::dispatch($params);

        Log::debug('ProcessingResult job created with params', [
            'message_id' => $message->id,
            'filter_id' => $filterId,
            'has_result_data' => !empty($aiRequest->response_data)
        ]);
    }
}