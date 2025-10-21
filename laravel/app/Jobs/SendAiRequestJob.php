<?php

namespace App\Jobs;

use App\Models\Ai\AiRequest;
use App\Models\Ai\AiEmbeddings;
use App\Helpers\Ai\AiServiceLocator;
use App\Services\QdrantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SendAiRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Конструктор - принимает ID AI запроса для обработки
     */
    public function __construct(public int $requestId) {}

    /**
     * Основной метод обработки job'а
     * Вызывается при выполнении job'а в очереди
     */
    public function handle()
    {
        Log::info('Starting AI request job', ['request_id' => $this->requestId]);
        
        // Находим запрос в базе данных
        $aiRequest = AiRequest::findOrFail($this->requestId);
        
        // Проверяем, не обработан ли уже этот запрос
        if ($aiRequest->status === 'completed') {
            Log::warning('Request already completed', ['request_id' => $aiRequest->id]);
            return;
        }

        // Обновляем статус запроса на "в обработке"
        $aiRequest->update(['status' => 'processing']);
        Log::info('AI request status updated', [
            'request_id' => $aiRequest->id,
            'status' => 'processing'
        ]);

        try {
            // Получаем сервис AI по ID из базы данных
            $service = AiServiceLocator::getServiceById($aiRequest->service_id);
            
            // Определяем тип запроса: embedding (векторизация) или regular (обычный запрос)
            $isEmbedding = ($aiRequest->metadata['type'] ?? null) === 'embedding';

            Log::debug('Service resolved', [
                'service' => get_class($service),
                'is_embedding' => $isEmbedding
            ]);

            // Обрабатываем запрос в зависимости от типа
            if ($isEmbedding) {
                // Для embedding запросов создаем сервис Qdrant и обрабатываем
                $qdrantService = app(QdrantService::class);
                $response = $this->processEmbedding($aiRequest, $service, $qdrantService);
            } else {
                // Для обычных запросов обрабатываем напрямую
                $response = $this->processRegular($aiRequest, $service);
            }

            // Завершаем обработку запроса
            $this->finalizeRequest($aiRequest, $service, $response, $isEmbedding);
            
        } catch (\Throwable $e) {
            // Обрабатываем любые ошибки, возникшие в процессе
            $this->handleFailure($aiRequest, $e);
        }
    }

    /**
     * Обработка embedding запроса - преобразование текста в вектор
     */
    protected function processEmbedding(AiRequest $aiRequest, $service, QdrantService $qdrantService)
    {
        // Проверяем, поддерживает ли сервис embedding
        if (!$service->supportEmbeddings()) { // ИСПРАВЛЕНО: supportEmbeddings() вместо supportsEmbeddings()
            throw new \RuntimeException("Service {$service::getName()} doesn't support embeddings");
        }

        // Извлекаем текст для векторизации
        $text = $aiRequest->request_data['text'] ?? '';
        if (empty($text)) {
            throw new \InvalidArgumentException("Empty text for embedding");
        }

        Log::info('Processing embedding request', [
            'text_length' => strlen($text),
            'model' => $aiRequest->request_data['model'] ?? null
        ]);

        // Получаем вектор эмбеддинга от AI сервиса
        $embeddingResponse = $service->getEmbedding([
            'text' => $text,
            'model' => $aiRequest->request_data['model'] ?? null,
            'metadata' => $aiRequest->request_data['metadata'] ?? []
        ]);

        // Проверяем корректность ответа
        if (!isset($embeddingResponse['embedding']) || !is_array($embeddingResponse['embedding'])) {
            throw new \RuntimeException("Invalid embedding response - embedding missing");
        }

        // Работа с Qdrant - подготовка коллекции для хранения векторов
        $dimensions = count($embeddingResponse['embedding']);
        if (!$qdrantService->ensureCollectionExists($dimensions)) {
            throw new \RuntimeException("Failed to ensure Qdrant collection exists");
        }

        // Формируем метаданные для вектора
        $payload = [
            'text' => $text,
            'model' => $aiRequest->request_data['model'] ?? null,
            'source' => $aiRequest->metadata['source'] ?? null,
            'created_at' => now()->toDateTimeString()
        ];

        // Сохраняем вектор в Qdrant
        $pointId = 'embedding_' . $aiRequest->id;
        $vectorId = $qdrantService->upsertPoint($pointId, $embeddingResponse['embedding'], $payload);

        if (!$vectorId) {
            throw new \RuntimeException("Failed to store embedding in Qdrant");
        }

        // Возвращаем обогащенный ответ
        return array_merge($embeddingResponse, [
            'vector_id' => $vectorId,
            'meta' => array_merge($embeddingResponse['meta'] ?? [], [
                'dimensions' => $dimensions,
                'provider' => 'qdrant',
                'collection' => $qdrantService->getCollectionName()
            ])
        ]);
    }

    /**
     * Обработка обычного AI запроса (чат, генерация текста и т.д.)
     */
    protected function processRegular(AiRequest $aiRequest, $service)
    {
        Log::debug('Processing regular AI request', [
            'request_data' => $aiRequest->request_data
        ]);

        // Проверяем, поддерживает ли сервис обычные запросы
        if (!$service->supportRegulars()) { // ИСПРАВЛЕНО: supportRegulars() вместо supportsRegular()
            throw new \RuntimeException("Service {$service::getName()} doesn't support regular requests");
        }

        // Отправляем запрос к AI сервису
        return $service->send($aiRequest->request_data);
    }

    /**
     * Завершение обработки запроса - сохранение результатов
     */
    protected function finalizeRequest(AiRequest $aiRequest, $service, array $response, bool $isEmbedding)
    {
        // Определяем статус на основе успешности выполнения
        $success = $response['success'] ?? false;
        $status = $success ? 'completed' : 'failed';

        // Формируем данные для обновления
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

        // Если это embedding запрос и есть ID embedding'а - обновляем запись
        if ($isEmbedding && $success && ($aiRequest->metadata['embedding_id'] ?? null)) {
            $this->updateEmbedding($aiRequest->metadata['embedding_id'], $response);
        }

        // Сохраняем результаты в базу данных
        $aiRequest->update($updateData);
        Log::info("Request {$status}", ['request_id' => $aiRequest->id]);
    }

    /**
     * Обновление записи embedding в базе данных
     */
    protected function updateEmbedding($embeddingId, array $response)
    {
        try {
            AiEmbeddings::where('id', $embeddingId)->update([
                'vector_id' => $response['vector_id'] ?? null,
                'metadata->embedding' => [
                    'model' => $response['meta']['model'] ?? null,
                    'dimensions' => $response['meta']['dimensions'] ?? null,
                    'service' => $response['meta']['provider'] ?? null,
                    'generated_at' => now()->toDateTimeString()
                ]
            ]);
            Log::debug('Embedding record updated', ['embedding_id' => $embeddingId]);
        } catch (\Exception $e) {
            Log::error('Failed to update embedding', [
                'embedding_id' => $embeddingId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Обработка неудачного выполнения запроса
     */
    protected function handleFailure(AiRequest $aiRequest, \Throwable $e)
    {
        // Формируем данные об ошибке
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

        // Сохраняем информацию об ошибке
        $aiRequest->update($errorData);
        Log::error('Request failed', [
            'request_id' => $aiRequest->id,
            'error' => $e->getMessage()
        ]);

        // Помечаем job как неудачный
        $this->fail($e);
    }

    /**
     * Обработка callback'ов после завершения AI запроса
     */
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