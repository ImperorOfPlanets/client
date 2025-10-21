<?php

namespace App\Jobs\AI;

use App\Models\Ai\AiEmbeddings;
use App\Services\QdrantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupEmbeddingVectorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;

    protected array $embeddingIds;

    public function __construct(array $embeddingIds)
    {
        $this->embeddingIds = $embeddingIds;
    }

    public function handle()
    {
        Log::info('Starting embedding vectors cleanup', [
            'embedding_ids_count' => count($this->embeddingIds),
            'embedding_ids' => $this->embeddingIds
        ]);

        try {
            $qdrantService = app(QdrantService::class);
            $embeddings = AiEmbeddings::whereIn('id', $this->embeddingIds)->get();

            $totalVectorsDeleted = 0;
            $errors = [];

            foreach ($embeddings as $embedding) {
                try {
                    if (!empty($embedding->vector_ids)) {
                        Log::info('Deleting vectors for embedding', [
                            'embedding_id' => $embedding->id,
                            'vector_ids_count' => count($embedding->vector_ids)
                        ]);

                        // Преобразуем vector_ids в point_ids
                        $pointIds = array_map(function($vectorId) {
                            return (string) crc32($vectorId);
                        }, $embedding->vector_ids);

                        $success = $qdrantService->deletePoints($pointIds);
                        
                        if ($success) {
                            $totalVectorsDeleted += count($embedding->vector_ids);
                            Log::info('Vectors deleted for embedding', [
                                'embedding_id' => $embedding->id,
                                'vectors_count' => count($embedding->vector_ids)
                            ]);
                        } else {
                            $errors[] = $embedding->id;
                            Log::error('Failed to delete vectors for embedding', [
                                'embedding_id' => $embedding->id
                            ]);
                        }
                    }

                    // Удаляем сам эмбеддинг из базы
                    $embedding->delete();
                    Log::info('Embedding deleted from database', ['embedding_id' => $embedding->id]);

                } catch (\Exception $e) {
                    $errors[] = $embedding->id;
                    Log::error('Error cleaning up embedding', [
                        'embedding_id' => $embedding->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Embedding vectors cleanup completed', [
                'total_embeddings_processed' => count($this->embeddingIds),
                'total_vectors_deleted' => $totalVectorsDeleted,
                'errors_count' => count($errors),
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Embedding vectors cleanup job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}