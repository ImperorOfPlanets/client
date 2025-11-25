<?php

namespace App\Helpers\Ai\Services;

use App\Helpers\Ai\AiServices;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class LocalEmbeddingService extends AiServices
{
    public $id = 4; // Уникальный ID для этого сервиса

    public function send(array $params): array
    {
        // Не реализовано для этого сервиса
        return [
            'success' => false,
            'error' => 'Chat completions not supported by LocalEmbeddingService',
            'code' => 501
        ];
    }

    public static function getName(): string
    {
        return 'LocalEmbedding';
    }

    public static function getRequiredSettings(): array
    {
        return [
            [
                'key' => 'tei_url',
                'label' => 'TEI Service URL',
                'description' => 'URL локального TEI-сервиса (например, http://tei:80)',
                'required' => true,
                'default' => 'http://tei:80'
            ],
            [
                'key' => 'model',
                'label' => 'Embedding Model',
                'description' => 'Модель для эмбеддингов (должна быть загружена в TEI)',
                'required' => false,
                'default' => 'BAAI/bge-small-en-v1.5'
            ]
        ];
    }

    public function supportEmbeddings(): bool
    {
        return true;
    }

    public function getEmbedding(array $params): array
    {
        if (empty($this->settings['tei_url'])) {
            throw new \RuntimeException('TEI service URL is not configured');
        }
        
        $teiUrl = $this->settings['tei_url'];
        $texts = is_array($params['text']) ? $params['text'] : [$params['text']];
    
        try {
            $client = new Client([
                'base_uri' => $teiUrl,
                'timeout' => 15.0
            ]);
    
            $response = $client->post('/embed', [
                'json' => [
                    'texts' => $texts,
                    'normalize_embeddings' => true
                ]
            ]);
    
            $data = json_decode($response->getBody(), true);
            
            $embeddings = $data['embeddings'] ?? [];
            $results = [];
            
            foreach ($texts as $i => $text) {
                $results[] = [
                    'success' => true,
                    'embedding' => $embeddings[$i] ?? [],
                    'vector_id' => 'local_' . md5($text),
                    'meta' => [
                        'model' => $this->settings['model'] ?? 'BAAI/bge-m3',
                        'dimensions' => count($embeddings[$i] ?? []),
                        'provider' => 'Local Sentence Transformer'
                    ]
                ];
            }
            
            return count($results) === 1 ? $results[0] : $results;
    
        } catch (\Exception $e) {
            Log::error('LocalEmbeddingService error', [
                'error' => $e->getMessage(),
                'url' => $teiUrl,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode() ?: 500
            ];
        }
    }
}