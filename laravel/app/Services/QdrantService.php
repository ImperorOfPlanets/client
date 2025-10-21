<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QdrantService
{
    private $host;
    private $collection;
    private $timeout;
    private $qdrantServers = [];
    private $isConfigured = false;

    public function __construct()
    {
        Log::info('QdrantService constructor called');
        $this->initializeConfiguration();
    }

    private function initializeConfiguration()
    {
        Log::debug('Starting Qdrant service configuration');
        
        try {
            // Сначала определяем возможные серверы
            $this->qdrantServers = $this->discoverQdrantServers();
            Log::debug('Discovered Qdrant servers', ['servers' => $this->qdrantServers]);
            
            if (empty($this->qdrantServers)) {
                Log::warning('No Qdrant servers discovered');
                $this->isConfigured = false;
                return;
            }
            
            // Пробуем найти доступный сервер
            $this->host = $this->getAvailableServer();
            
            // Если нашли доступный сервер - настраиваем
            if ($this->host) {
                $this->collection = env('QDRANT_COLLECTION', 'embeddings');
                $this->timeout = env('QDRANT_TIMEOUT', 30);
                $this->isConfigured = true;
                
                Log::info('Qdrant service successfully initialized', [
                    'host' => $this->host,
                    'collection' => $this->collection,
                    'timeout' => $this->timeout,
                    'discovered_servers' => $this->qdrantServers
                ]);
            } else {
                Log::warning('No available Qdrant servers found from discovered list', [
                    'discovered_servers' => $this->qdrantServers
                ]);
                $this->isConfigured = false;
            }

        } catch (\Exception $e) {
            Log::error('Qdrant service configuration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->isConfigured = false;
        }
        
        Log::debug('Qdrant service configuration completed', ['is_configured' => $this->isConfigured]);
    }

    private function discoverQdrantServers()
    {
        $projectName = env('PROJECTNAME', 'core');
        Log::debug('Discovering Qdrant servers', ['project_name' => $projectName]);
        
        // ТОЛЬКО локальный контейнер и из env - в правильном порядке
        $servers = [
            // 1. Локальный контейнер с префиксом проекта (самый приоритетный)
            "http://qdrant-{$projectName}:6333",
        ];
        
        // 2. Добавляем хост из .env если он задан (второй приоритет)
        $envHost = env('QDRANT_HOST');
        Log::debug('QDRANT_HOST from env', ['value' => $envHost]);
        
        if (!empty($envHost)) {
            $servers[] = $envHost;
        }
        
        $filteredServers = array_unique(array_filter($servers));
        Log::debug('Final discovered servers list', ['servers' => $filteredServers]);
        
        return $filteredServers;
    }

    public function isConfigured()
    {
        Log::debug('Qdrant service configuration check', ['is_configured' => $this->isConfigured]);
        return $this->isConfigured;
    }

    private function getAvailableServer()
    {
        Log::debug('Searching for available Qdrant server', ['servers_count' => count($this->qdrantServers)]);
        
        foreach ($this->qdrantServers as $index => $server) {
            Log::debug('Checking server availability', [
                'server' => $server,
                'attempt' => $index + 1
            ]);
            
            $isHealthy = $this->checkServerHealth($server);
            
            if ($isHealthy) {
                Log::info('Available Qdrant server found', [
                    'server' => $server,
                    'attempt' => $index + 1
                ]);
                return $server;
            }
            
            Log::debug('Server is not available', ['server' => $server]);
        }
        
        Log::warning('No available Qdrant servers found after checking all');
        return null;
    }

    private function checkServerHealth($server)
    {
        $healthUrl = rtrim($server, '/') . '/collections';
        Log::debug('Checking server health', [
            'server' => $server,
            'health_url' => $healthUrl
        ]);
        
        try {
            $startTime = microtime(true);
            $response = Http::timeout(5)->get($healthUrl);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $isOk = $response->ok();
            
            Log::debug('Server health check result', [
                'server' => $server,
                'status_code' => $response->status(),
                'response_time_ms' => $responseTime,
                'is_healthy' => $isOk,
                'response_body_preview' => substr($response->body(), 0, 200)
            ]);
            
            return $isOk;
            
        } catch (\Exception $e) {
            Log::warning('Server health check failed', [
                'server' => $server,
                'error' => $e->getMessage(),
                'error_type' => get_class($e)
            ]);
            return false;
        }
    }

    public function upsertPoint($id, $vector, $payload = [])
    {
        Log::debug('Qdrant upsertPoint called', [
            'point_id' => $id,
            'vector_dimensions' => is_array($vector) ? count($vector) : 'unknown',
            'payload_keys' => array_keys($payload)
        ]);
        
        if (!$this->isConfigured) {
            Log::warning('Qdrant service not configured for upsertPoint');
            return false;
        }
    
        try {
            $pointId = $this->preparePointId($id);
            Log::debug('Prepared point ID', ['original_id' => $id, 'hashed_id' => $pointId]);
            
            // Вариант 1: Используем правильный формат для batch операций
            $data = [
                'points' => [
                    [
                        'id' => (int)$pointId, // Qdrant ожидает integer ID
                        'vector' => $vector,
                        'payload' => $payload
                    ]
                ]
            ];
    
            $url = "{$this->host}/collections/{$this->collection}/points";
            Log::debug('Sending upsert request', [
                'url' => $url,
                'data' => $data // Логируем данные для отладки
            ]);
    
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->timeout($this->timeout)
              ->put($url, $data);
    
            Log::debug('Qdrant response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
    
            if ($response->successful()) {
                Log::info('Point upserted successfully', [
                    'point_id' => $pointId,
                    'collection' => $this->collection
                ]);
                return true;
            } else {
                Log::error('Point upsert failed', [
                    'point_id' => $pointId,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }
    
        } catch (\Exception $e) {
            Log::error('Point upsert exception', [
                'point_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    private function preparePointId($id)
    {
        return (string) crc32($id);
    }

    public function ensureCollectionExists($dimensions)
    {
        Log::debug('Ensuring collection exists', [
            'collection' => $this->collection,
            'dimensions' => $dimensions
        ]);
        
        if (!$this->isConfigured) {
            Log::warning('Qdrant service not configured for ensureCollectionExists');
            return false;
        }
    
        // Проверяем существование коллекции
        if ($this->collectionExists()) {
            Log::debug('Collection already exists', ['collection' => $this->collection]);
            return true;
        }
    
        // Создаем коллекцию если не существует
        Log::info('Creating Qdrant collection', [
            'collection' => $this->collection,
            'dimensions' => $dimensions
        ]);
    
        try {
            $data = [
                'vectors' => [
                    'size' => (int)$dimensions,
                    'distance' => 'Cosine'
                ]
            ];
    
            $response = Http::timeout($this->timeout)
                ->put("{$this->host}/collections/{$this->collection}", $data);
    
            if ($response->successful()) {
                Log::info('Qdrant collection created successfully', [
                    'collection' => $this->collection,
                    'dimensions' => $dimensions
                ]);
                return true;
            }
    
            Log::error('Qdrant collection creation failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return false;
    
        } catch (\Exception $e) {
            Log::error('Qdrant collection creation error', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function collectionExists()
    {
        if (!$this->isConfigured) {
            Log::debug('Collection exists check failed - service not configured');
            return false;
        }

        try {
            $url = "{$this->host}/collections/{$this->collection}";
            Log::debug('Checking collection existence', ['url' => $url]);
            
            $response = Http::timeout($this->timeout)->get($url);
            $exists = $response->successful();
            
            Log::debug('Collection existence check result', [
                'collection' => $this->collection,
                'exists' => $exists,
                'status_code' => $response->status()
            ]);
            
            return $exists;
            
        } catch (\Exception $e) {
            Log::error('Collection existence check error', [
                'collection' => $this->collection,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function searchPoints($vector, $limit = 5, $scoreThreshold = 0.8)
    {
        Log::debug('Qdrant searchPoints called', [
            'vector_dimensions' => is_array($vector) ? count($vector) : 'unknown',
            'limit' => $limit,
            'score_threshold' => $scoreThreshold
        ]);
        
        if (!$this->isConfigured) {
            Log::warning('Qdrant service not configured for searchPoints');
            return [];
        }

        try {
            $url = "{$this->host}/collections/{$this->collection}/points/search";
            $payload = [
                'vector' => $vector,
                'limit' => $limit,
                'with_payload' => true,
                'score_threshold' => $scoreThreshold
            ];
            
            Log::debug('Sending search request', [
                'url' => $url,
                'payload_keys' => array_keys($payload)
            ]);

            $startTime = microtime(true);
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->timeout($this->timeout)
            ->post($url, $payload);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                $result = $response->json();
                $resultsCount = count($result['result'] ?? []);
                
                Log::info('Qdrant search completed successfully', [
                    'results_count' => $resultsCount,
                    'response_time_ms' => $responseTime,
                    'collection' => $this->collection
                ]);
                
                return $result['result'] ?? [];
            }

            Log::error('Qdrant search request failed', [
                'status_code' => $response->status(),
                'response_body' => $response->body(),
                'response_time_ms' => $responseTime
            ]);
            return [];

        } catch (\Exception $e) {
            Log::error('Qdrant search error', [
                'error' => $e->getMessage(),
                'collection' => $this->collection,
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Простая проверка доступности Qdrant
     */
    public function healthCheck()
    {
        Log::debug('Qdrant health check called');
        
        if (!$this->isConfigured) {
            Log::debug('Health check failed - service not configured');
            return false;
        }

        $isHealthy = $this->checkServerHealth($this->host);
        Log::debug('Health check result', [
            'host' => $this->host,
            'is_healthy' => $isHealthy
        ]);
        
        return $isHealthy;
    }

    /**
     * Получить текущую конфигурацию для отладки
     */
    public function getDebugInfo()
    {
        return [
            'is_configured' => $this->isConfigured,
            'host' => $this->host,
            'collection' => $this->collection,
            'timeout' => $this->timeout,
            'discovered_servers' => $this->qdrantServers,
            'project_name' => env('PROJECTNAME', 'core'),
            'qdrant_host_env' => env('QDRANT_HOST')
        ];
    }

    public function scrollPoints(?string $offset = null, int $limit = 1000): array
    {
        Log::debug('Qdrant scrollPoints called', [
            'offset' => $offset,
            'limit' => $limit
        ]);
        
        if (!$this->isConfigured) {
            Log::warning('Qdrant service not configured for scrollPoints');
            return [];
        }

        try {
            $url = "{$this->host}/collections/{$this->collection}/points/scroll";
            $payload = [
                'limit' => $limit,
                'with_payload' => true,
                'with_vector' => false // Мы не нуждаемся в самих векторах для синхронизации
            ];
            
            if ($offset) {
                $payload['offset'] = $offset;
            }
            
            Log::debug('Sending scroll request', [
                'url' => $url,
                'payload' => $payload
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->timeout($this->timeout)
            ->post($url, $payload);

            if ($response->successful()) {
                $result = $response->json();
                Log::debug('Scroll request successful', [
                    'points_count' => count($result['result']['points'] ?? []),
                    'has_next_page' => isset($result['result']['next_page_offset'])
                ]);
                
                return $result;
            }

            Log::error('Scroll request failed', [
                'status_code' => $response->status(),
                'response_body' => $response->body()
            ]);
            return [];

        } catch (\Exception $e) {
            Log::error('Qdrant scroll error', [
                'error' => $e->getMessage(),
                'collection' => $this->collection
            ]);
            return [];
        }
    }

    public function deletePoint($pointId)
    {
        Log::debug('Qdrant deletePoint called', ['point_id' => $pointId]);
        
        if (!$this->isConfigured) {
            Log::warning('Qdrant service not configured for deletePoint');
            return false;
        }

        try {
            $url = "{$this->host}/collections/{$this->collection}/points/delete";
            $payload = [
                'points' => [(int)$pointId]
            ];

            Log::debug('Sending delete request', [
                'url' => $url,
                'payload' => $payload
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->timeout($this->timeout)
            ->post($url, $payload);

            Log::debug('Qdrant delete response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                Log::info('Point deleted successfully', [
                    'point_id' => $pointId,
                    'collection' => $this->collection
                ]);
                return true;
            } else {
                Log::error('Point delete failed', [
                    'point_id' => $pointId,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Point delete exception', [
                'point_id' => $pointId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    // Также добавьте метод для массового удаления
    public function deletePoints(array $pointIds)
    {
        Log::debug('Qdrant deletePoints called', ['point_ids_count' => count($pointIds)]);
        
        if (!$this->isConfigured) {
            Log::warning('Qdrant service not configured for deletePoints');
            return false;
        }

        try {
            $url = "{$this->host}/collections/{$this->collection}/points/delete";
            $payload = [
                'points' => array_map('intval', $pointIds)
            ];

            Log::debug('Sending batch delete request', [
                'url' => $url,
                'points_count' => count($pointIds)
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->timeout($this->timeout)
            ->post($url, $payload);

            Log::debug('Qdrant batch delete response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                Log::info('Points batch deleted successfully', [
                    'points_count' => count($pointIds),
                    'collection' => $this->collection
                ]);
                return true;
            } else {
                Log::error('Points batch delete failed', [
                    'points_count' => count($pointIds),
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Points batch delete exception', [
                'points_count' => count($pointIds),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}