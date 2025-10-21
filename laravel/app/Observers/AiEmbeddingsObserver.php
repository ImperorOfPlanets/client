<?php
namespace App\Observers;

use Illuminate\Support\Facades\Log;
use App\Models\Ai\AiEmbeddings;
use App\Models\Ai\AiRequest;
use App\Jobs\AI\ProcessEmbedding;

class AiEmbeddingsObserver
{
    public function created(AiEmbeddings $embedding)
    {
        Log::info('Embedding OBSERVER CREATE START', ['id' => $embedding->id]);
        $this->dispatchEmbeddingJob($embedding);
    }

    public function updated(AiEmbeddings $embedding)
    {
        Log::info('Embedding OBSERVER UPDATE START', ['id' => $embedding->id]);
        
        // Если статус указывает на необходимость пересоздания
        if (in_array($embedding->status, ['needs_update', 'processing'])) {
            $this->dispatchEmbeddingJob($embedding);
        }
    }

    protected function dispatchEmbeddingJob(AiEmbeddings $embedding)
    {
        try {
            // Создаем новый запрос на обработку через ProcessEmbedding
            $aiRequest = AiRequest::create([
                'type' => 'embedding',
                'request_data' => [
                    'text' => $embedding->content,
                    'model' => 'tei'
                ],
                'status' => 'pending',
                'metadata' => [
                    'embedding_id' => $embedding->id,
                    'source' => 'observer',
                    'category_id' => $embedding->category_id ?? null,
                    'object_id' => $embedding->object_id ?? null,
                    'previous_vector_ids' => $embedding->vector_ids ?? []
                ]
            ]);

            ProcessEmbedding::dispatch($aiRequest->id);

            Log::info('Embedding job dispatched from observer', [
                'embedding_id' => $embedding->id,
                'request_id' => $aiRequest->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to dispatch embedding job from observer', [
                'embedding_id' => $embedding->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}