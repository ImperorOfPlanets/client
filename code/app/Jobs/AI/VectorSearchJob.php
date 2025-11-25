<?php

namespace App\Jobs\AI;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Services\QdrantService;
use App\Services\VectorSearchFileService;

class VectorSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $teiServers;

    public function __construct(
        public string $searchText,
        public int $k = 5,
        public ?string $searchId = null,
        public bool $isSync = false
    ) {
        $projectName = env('PROJECTNAME', 'core');
        $this->teiServers = [
            "http://tei-{$projectName}:8080",
            "http://tei-{$projectName}",
            'http://tei1.example.com:8080',
            'http://tei2.example.com:8080'
        ];
        
        if (!$this->searchId) {
            $this->searchId = 'search_' . Str::random(20) . '_' . time();
        }
    }

    public function handle()
    {
        // Инициализируем сервис только когда нужен (для асинхронного поиска)
        if (!$this->isSync) {
            $fileService = app(VectorSearchFileService::class);
            $fileService->updateSearchStatus($this->searchId, 'processing');
        }

        try {
            // 1. Генерируем embedding через TEI
            $embeddingData = $this->getEmbeddingFromTei($this->searchText);
            $embedding = $embeddingData['embedding'];

            // 2. Запрашиваем ближайшие вектора в Qdrant
            $results = $this->searchNearestVectors($embedding, $this->k);

            // 3. Сохраняем результаты
            if ($this->isSync) {
                return [
                    'success' => true,
                    'search_id' => $this->searchId,
                    'query_text' => $this->searchText,
                    'k' => $this->k,
                    'results' => $results,
                    'results_count' => count($results),
                    'timestamp' => now()->toISOString(),
                    'embedding_info' => [
                        'dimensions' => count($embedding),
                        'tei_server' => $embeddingData['tei_server'] ?? 'unknown'
                    ]
                ];
            } else {
                $fileService = app(VectorSearchFileService::class);
                $fileService->saveSearchResults($this->searchId, $results);

                Log::info('Vector search completed', [
                    'search_id' => $this->searchId,
                    'text' => $this->searchText,
                    'k' => $this->k,
                    'results_count' => count($results),
                    'tei_server' => $embeddingData['tei_server'] ?? 'unknown'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Vector search failed', [
                'search_id' => $this->searchId,
                'error' => $e->getMessage(),
                'text' => $this->searchText,
                'k' => $this->k
            ]);

            if (!$this->isSync) {
                $fileService = app(VectorSearchFileService::class);
                $fileService->saveSearchError($this->searchId, $e->getMessage());
            }

            throw $e;
        }
    }

    // Остальные методы остаются без изменений...
    protected function getEmbeddingFromTei(string $text): array
    {
        $server = $this->getAvailableServer();
        if (!$server) {
            throw new \RuntimeException("No available TEI server found");
        }

        Log::info('Sending embedding request to TEI server for vector search', [
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
            ->post(rtrim($server, '/') . '/embeddings', $payload);

        Log::debug('TEI server response for vector search', [
            'status' => $response->status(),
            'server' => $server
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("TEI server error: " . $response->status() . " - " . $response->body());
        }

        $result = $response->json();

        // Обрабатываем различные форматы ответа TEI
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

    protected function getAvailableServer(): ?string
    {
        foreach ($this->teiServers as $server) {
            try {
                $healthUrl = rtrim($server, '/') . '/health';
                $response = Http::timeout(2)->get($healthUrl);

                if ($response->ok()) {
                    Log::debug('TEI server health check passed for vector search', ['server' => $server]);
                    return $server;
                }
            } catch (\Exception $e) {
                Log::debug('TEI server health check failed for vector search', [
                    'server' => $server,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        
        Log::warning('No available TEI servers found for vector search', ['servers_tried' => $this->teiServers]);
        return null;
    }

    protected function searchNearestVectors(array $embedding, int $k): array
    {
        Log::debug('Starting vector search via QdrantService', [
            'embedding_dimensions' => count($embedding),
            'k' => $k
        ]);
    
        $qdrantService = app(QdrantService::class);
        
        Log::debug('QdrantService debug info', $qdrantService->getDebugInfo());
        
        if (!$qdrantService->isConfigured()) {
            throw new \RuntimeException("Qdrant service is not configured");
        }
    
        $results = $qdrantService->searchPoints($embedding, $k);
        
        Log::info('Vector search completed via QdrantService', [
            'results_count' => count($results)
        ]);
        
        return $results;
    }
}