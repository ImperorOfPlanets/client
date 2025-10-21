<?php

namespace App\Services;

use App\Models\Ai\AiEmbeddings;
use App\Helpers\Embeddings\EmbeddingService;
use Illuminate\Support\Facades\Log;

class VectorEmbeddingService
{
    private QdrantService $qdrant;
    private EmbeddingService $embeddingService;
    private int $vectorSize;

    public function __construct(QdrantService $qdrant, EmbeddingService $embeddingService)
    {
        $this->qdrant = $qdrant;
        $this->embeddingService = $embeddingService;
        $this->vectorSize = $this->determineVectorSize();
    }

    private function determineVectorSize(): int
    {
        // Можно определить размер вектора на основе используемого сервиса
        // Например, для OpenAI text-embedding-3-small это 1536
        // Для других моделей можно добавить соответствующую логику
        return 1536;
    }

    public function initialize(): void
    {
        if (!$this->qdrant->collectionExists()) {
            $this->qdrant->createCollection($this->vectorSize);
        }
    }

    public function createEmbedding(
        $model = null,
        string $content,
        ?int $categoryId = null
    ): ?AiEmbeddings {
        try {
            $response = $this->embeddingService->generate($content);
            
            if (!$response['success'] || empty($response['data'])) {
                throw new \RuntimeException('Empty or failed embedding response: ' . ($response['error'] ?? 'Unknown error'));
            }

            $vector = $response['data'][0]; // Берем первый вектор для первого входа

            $embedding = AiEmbeddings::create([
                'embeddingable_type' => $model ? get_class($model) : null,
                'embeddingable_id' => $model?->id,
                'content' => $content,
                'category_id' => $categoryId,
                'qdrant_id' => $this->generateQdrantId(),
            ]);

            $payload = $this->buildPayload($embedding, $model);

            if (!$this->qdrant->upsertPoint($embedding->qdrant_id, $vector, $payload)) {
                $embedding->delete();
                throw new \RuntimeException('Failed to upsert point to Qdrant');
            }

            return $embedding;
        } catch (\Exception $e) {
            Log::error('Create embedding failed: ' . $e->getMessage(), [
                'content' => $content,
                'model' => $model ? get_class($model) : null,
                'error' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function searchSimilar(string $query, int $limit = 5, ?int $categoryId = null): array
    {
        try {
            $response = $this->embeddingService->generate($query);
            
            if (!$response['success'] || empty($response['data'])) {
                throw new \RuntimeException('Empty or failed embedding response: ' . ($response['error'] ?? 'Unknown error'));
            }

            $vector = $response['data'][0];
            
            $filters = [];
            if ($categoryId !== null) {
                $filters = [
                    'must' => [
                        ['key' => 'category_id', 'match' => ['value' => $categoryId]]
                    ]
                ];
            }

            return $this->qdrant->searchVectors($vector, $limit, $filters);
        } catch (\Exception $e) {
            Log::error('Search similar failed: ' . $e->getMessage(), [
                'query' => $query,
                'error' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function updateEmbedding(AiEmbeddings $embedding): bool
    {
        try {
            $response = $this->embeddingService->generate($embedding->content);
            
            if (!$response['success'] || empty($response['data'])) {
                throw new \RuntimeException('Empty or failed embedding response');
            }

            $vector = $response['data'][0];
            $payload = $this->buildPayload($embedding, $embedding->embeddingable);

            return $this->qdrant->upsertPoint($embedding->qdrant_id, $vector, $payload);
        } catch (\Exception $e) {
            Log::error('Update embedding failed: ' . $e->getMessage(), [
                'embedding_id' => $embedding->id,
                'error' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function deleteEmbedding(AiEmbeddings $embedding): bool
    {
        try {
            if ($this->qdrant->deletePoint($embedding->qdrant_id)) {
                return $embedding->delete();
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Delete embedding failed: ' . $e->getMessage(), [
                'embedding_id' => $embedding->id,
                'error' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    private function generateQdrantId(): string
    {
        return 'emb_' . uniqid();
    }

    private function buildPayload(AiEmbeddings $embedding, $model = null): array
    {
        $payload = [
            'db_id' => $embedding->id,
            'content_excerpt' => mb_substr($embedding->content, 0, 200),
            'created_at' => $embedding->created_at->toDateTimeString(),
        ];

        if ($model) {
            $payload['object_type'] = get_class($model);
            $payload['object_id'] = $model->id;
        }

        if ($embedding->category_id) {
            $payload['category_id'] = $embedding->category_id;
        }

        return $payload;
    }

    public function getEmbeddingService(): EmbeddingService
    {
        return $this->embeddingService;
    }

    public function getVectorSize(): int
    {
        return $this->vectorSize;
    }
}