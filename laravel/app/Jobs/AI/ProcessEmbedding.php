<?php

namespace App\Jobs\AI;

use App\Models\Ai\AiEmbeddings;
use App\Services\QdrantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessEmbedding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $embeddingId,
        public string $content,
        public array $metadata = []
    ) {}

    public function handle()
    {
        Log::info('ProcessEmbedding job started', [
            'embedding_id' => $this->embeddingId,
            'metadata' => $this->metadata
        ]);

        try {
            $embedding = AiEmbeddings::find($this->embeddingId);
            if (!$embedding) {
                Log::error('Embedding not found', ['embedding_id' => $this->embeddingId]);
                return;
            }

            $qdrantService = app(QdrantService::class);
            
            // ШАГ 1: Если это пересоздание - сначала удаляем старые векторы
            if (($this->metadata['recreate'] ?? false) && !empty($embedding->vector_ids)) {
                Log::info('Deleting old vectors before recreation', [
                    'embedding_id' => $this->embeddingId,
                    'old_vector_ids' => $embedding->vector_ids
                ]);
                
                $this->deleteOldVectors($qdrantService, $embedding->vector_ids);
                
                // Очищаем vector_ids в базе
                $embedding->update(['vector_ids' => []]);
            }

            // Проверяем доступность Qdrant и создаем коллекцию если нужно
            if (!$qdrantService->isConfigured()) {
                throw new \RuntimeException("Qdrant service is not configured");
            }

            // Убеждаемся, что коллекция существует
            $dimensions = 384;
            if (!$qdrantService->ensureCollectionExists($dimensions)) {
                throw new \RuntimeException("Failed to create or verify Qdrant collection");
            }

            // ШАГ 2: Создаем чанки
            $chunks = $this->splitIntoChunks($this->content);
            Log::info('Content split into chunks', [
                'embedding_id' => $this->embeddingId,
                'chunks_count' => count($chunks)
            ]);

            // ШАГ 3: Создаем векторы для каждого чанка
            $newVectorIds = [];
            $chunksMetadata = [];
            
            foreach ($chunks as $index => $chunk) {
                // Генерируем embedding ID для использования в Qdrant
                $vectorId = "embedding_{$this->embeddingId}_chunk_{$index}";
                
                // Генерируем эмбеддинг
                $embeddingData = $this->getEmbeddingFromTei($chunk);
                $vector = $embeddingData['embedding'] ?? null;
                
                if (!$vector) {
                    Log::error('Failed to generate embedding for chunk', [
                        'embedding_id' => $this->embeddingId,
                        'chunk_index' => $index
                    ]);
                    continue;
                }

                // Сохраняем в Qdrant - передаем vectorId, а QdrantService сам преобразует в pointId
                $payload = [
                    'text' => $chunk,
                    'embedding_id' => $this->embeddingId,
                    'chunk_id' => $index,
                    'total_chunks' => count($chunks),
                    'source' => $this->metadata['source'] ?? 'unknown',
                    'category_id' => $this->metadata['category'] ?? null,
                    'object_id' => $this->metadata['object_id'] ?? null,
                    'parent_id' => $this->embeddingId,
                    'is_chunk' => true
                ];

                // ПЕРЕДАЕМ vectorId, а не pointId!
                $success = $qdrantService->upsertPoint($vectorId, $vector, $payload);
                
                if ($success) {
                    // Сохраняем именно vectorId, который будет преобразован в тот же pointId что и в Qdrant
                    $newVectorIds[] = $vectorId;
                    $chunksMetadata[] = [
                        'chunk_id' => $index,
                        'vector_id' => $vectorId,
                        'status' => 'completed'
                    ];
                    Log::debug('Chunk vector created successfully', [
                        'embedding_id' => $this->embeddingId,
                        'chunk_index' => $index,
                        'vector_id' => $vectorId
                    ]);
                } else {
                    Log::error('Failed to upsert chunk vector', [
                        'embedding_id' => $this->embeddingId,
                        'chunk_index' => $index
                    ]);
                }
            }

            // ШАГ 4: Обновляем запись в базе с полной metadata
            $existingMetadata = $embedding->metadata ?? [];
            $existingMetadata['technical'] = [
                'total_tokens' => 0,
                'dimensions' => $dimensions,
                'model' => 'multilingual-e5-small'
            ];
            $existingMetadata['chunking'] = [
                'total_chunks' => count($chunks),
                'chunks_processed' => count($newVectorIds),
                'strategy' => 'semantic'
            ];
            $existingMetadata['processing'] = [
                'status' => 'completed',
                'processed_at' => now()->toISOString(),
                'source' => $this->metadata['source'] ?? 'unknown'
            ];
            $existingMetadata['chunks'] = $chunksMetadata;

            // ВАЖНО: Сохраняем именно vectorId, которые будут преобразованы в pointId в Qdrant
            $embedding->update([
                'vector_ids' => $newVectorIds, // Это vectorId, которые преобразуются в pointId в Qdrant
                'metadata' => $existingMetadata
            ]);

            Log::info('ProcessEmbedding job completed successfully', [
                'embedding_id' => $this->embeddingId,
                'new_vector_ids_count' => count($newVectorIds),
                'vector_ids' => $newVectorIds // Логируем vectorId
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessEmbedding job failed', [
                'embedding_id' => $this->embeddingId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Обновляем статус на ошибку
            $embedding = AiEmbeddings::find($this->embeddingId);
            if ($embedding) {
                $existingMetadata = $embedding->metadata ?? [];
                $existingMetadata['processing'] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString()
                ];
                $embedding->update(['metadata' => $existingMetadata]);
            }
            
            throw $e;
        }
    }

    /**
     * Удаляет старые векторы из Qdrant
     */
    protected function deleteOldVectors(QdrantService $qdrantService, array $oldVectorIds): void
    {
        if (empty($oldVectorIds)) {
            return;
        }

        Log::info('Deleting old vectors', [
            'vector_ids_count' => count($oldVectorIds),
            'old_vector_ids' => $oldVectorIds
        ]);
        
        try {
            // Преобразуем vectorIds в pointIds для удаления
            $pointIds = array_map(function($vectorId) {
                return (int) crc32($vectorId);
            }, $oldVectorIds);

            $success = $qdrantService->deletePoints($pointIds);
            
            if ($success) {
                Log::info('Old vectors deleted successfully', [
                    'vector_ids_count' => count($oldVectorIds),
                    'point_ids_used' => $pointIds
                ]);
            } else {
                Log::error('Failed to delete old vectors', [
                    'vector_ids_count' => count($oldVectorIds)
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Exception deleting old vectors', [
                'error' => $e->getMessage(),
                'vector_ids_count' => count($oldVectorIds)
            ]);
        }
    }

    protected function splitIntoChunks(string $content): array
    {
        $sentences = preg_split('/(?<=[.?!])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = [];
        $currentChunk = '';
        
        foreach ($sentences as $sentence) {
            if (strlen($currentChunk . $sentence) > 1000 && !empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                $currentChunk = '';
            }
            $currentChunk .= $sentence . ' ';
        }
        
        if (!empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }
        
        if (empty($chunks)) {
            $chunks = [trim($content)];
        }
        
        return $chunks;
    }

    protected function getEmbeddingFromTei(string $text): array
    {
        $projectName = env('PROJECTNAME', 'core');
        $localServer = "http://tei-{$projectName}";
    
        $servers = [
            $localServer,
            'http://tei1.example.com',
            'http://tei2.example.com'
        ];
    
        $server = $this->getAvailableServer($servers);
        if (!$server) {
            throw new \RuntimeException("No available TEI server found");
        }
    
        $targetUrl = rtrim($server, '/') . '/embeddings';
    
        Log::info('Sending embedding request to TEI server', [
            'server' => $server,
            'text_length' => strlen($text),
            'text_preview' => substr($text, 0, 100)
        ]);
    
        $payload = [
            'input' => $text,
            'truncate' => true,
            'normalize' => true
        ];
    
        $response = Http::timeout(60)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->post($targetUrl, $payload);
    
        Log::debug('TEI server response', [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->body()
        ]);
    
        if (!$response->successful()) {
            throw new \RuntimeException("TEI server error: " . $response->status() . " - " . $response->body());
        }
    
        $result = $response->json();
    
        if (isset($result['object']) && $result['object'] === 'list' && isset($result['data'][0]['embedding'])) {
            return [
                'embedding' => $result['data'][0]['embedding'],
                'tei_server' => $server,
                'success' => true
            ];
        }
    
        if (isset($result['embedding'])) {
            return [
                'embedding' => $result['embedding'],
                'tei_server' => $server,
                'success' => true
            ];
        }
    
        if (isset($result[0]['embedding'])) {
            return [
                'embedding' => $result[0]['embedding'],
                'tei_server' => $server,
                'success' => true
            ];
        }
    
        if (isset($result[0]) && is_array($result[0])) {
            return [
                'embedding' => $result[0],
                'tei_server' => $server,
                'success' => true
            ];
        }
    
        throw new \RuntimeException("Invalid response format from TEI server: " . json_encode($result));
    }    
    
    protected function getAvailableServer(array $servers)
    {
        foreach ($servers as $server) {
            try {
                $healthUrl = rtrim($server, '/') . '/health';
                $response = Http::timeout(2)->get($healthUrl);
    
                if ($response->ok()) {
                    Log::info('TEI server health check passed', ['server' => $server]);
                    return $server;
                }
            } catch (\Exception $e) {
                Log::debug('TEI server health check failed', [
                    'server' => $server,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        
        Log::warning('No available TEI servers found', ['servers_tried' => $servers]);
        return null;
    }
}