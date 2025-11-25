<?php

namespace App\Helpers\Ai\Services;

use App\Helpers\Ai\AiServices;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class HuggingFaceService extends AiServices
{
    public $id = 3; // Новый ID для этого сервиса

    public function send(array $params): array
    {
        Log::info('HuggingFaceService: Starting request', ['params' => $params]);
        $this->validateSettings();
        
        $apiToken = $this->getSetting('api_token');
        $model = $this->getSetting('model', 'deepseek-ai/DeepSeek-V3-0324');

        try {
            $client = new Client([
                'base_uri' => 'https://router.huggingface.co/',
                'timeout' => 30
            ]);

            // Determine if this is a stateless request or needs context
            if (isset($params['stateless'])) {
                // Handle stateless request (one-time prompt)
                $messages = [
                    [
                        'role' => 'user',
                        'content' => $params['stateless']
                    ]
                ];
            } else {
                // Handle request with context
                $messages = $params['messages'] ?? [
                    [
                        'role' => 'user',
                        'content' => $params['prompt'] ?? ''
                    ]
                ];
            }

            $requestData = [
                'messages' => $messages,
                'model' => $model,
                'stream' => false
            ];

            Log::debug('HuggingFaceService: Request data', $requestData);

            $response = $client->post('novita/v3/openai/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData
            ]);

            $data = json_decode($response->getBody(), true);
            
            Log::info('HuggingFaceService: Successful response', [
                'status' => $response->getStatusCode(),
                'response' => $data
            ]);

            return [
                'success' => true,
                'data' => $data['choices'][0]['message']['content'] ?? '',
                'meta' => [
                    'model' => $model,
                    'provider' => 'HuggingFace',
                    'response_time' => $response->getHeaderLine('x-response-time'),
                    'request_type' => isset($params['stateless']) ? 'stateless' : 'context'
                ]
            ];

        } catch (\Exception $e) {
            Log::error('HuggingFaceService: API request failed', [
                'error' => $e->getMessage(),
                'model' => $model
            ]);
            
            return $this->handleError($e);
        }
    }

    public static function getName(): string
    {
        return 'HuggingFace';
    }

    public static function getRequiredSettings(): array
    {
        return [
            [
                'key' => 'api_token',
                'label' => 'API Token',
                'description' => 'Your Hugging Face API token (from Settings)',
                'required' => true
            ],
            [
                'key' => 'model',
                'label' => 'Model',
                'description' => 'Model ID from Hugging Face Hub',
                'required' => false,
                'default' => 'deepseek-ai/DeepSeek-V3-0324'
            ]
        ];
    }

    protected function handleError(\Exception $e): array
    {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'exception' => get_class($e),
            'trace' => config('app.debug') ? $e->getTraceAsString() : null
        ];
    }

    public function validateSettings(): bool
    {
        $required = array_column($this->getRequiredSettings(), 'key');
        
        foreach ($required as $key) {
            if (!array_key_exists($key, $this->settings)) {
                throw new \RuntimeException("Missing required setting: {$key}");
            }
        }
        
        return true;
    }

    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    public function getSettings(): array
    {
        return $this->settings ?? [];
    }

    public function supportEmbeddings(): bool
    {
        return true;
    }

    public function getEmbedding(array $params): array
    {
        $this->validateSettings();
        
        $apiToken = $this->getSetting('api_token');
        $model = $this->getSetting('embedding_model', 'deepseek-ai/DeepSeek-V3-0324');

        try {
            $client = new Client([
                'base_uri' => 'https://api-inference.huggingface.co/',
                'timeout' => 30
            ]);

            $response = $client->post("pipeline/feature-extraction/$model", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'inputs' => $params['text'],
                    'options' => [
                        'wait_for_model' => true,
                        'use_cache' => true
                    ]
                ]
            ]);

            $embedding = json_decode($response->getBody(), true);
            
            return [
                'success' => true,
                'embedding' => $embedding,
                'vector_id' => 'hf_'.md5($params['text']),
                'meta' => [
                    'model' => $model,
                    'dimensions' => is_array($embedding) ? count($embedding) : null,
                    'provider' => 'HuggingFace (DeepSeek)'
                ]
            ];

        } catch (\Exception $e) {
            Log::error('HuggingFace embedding error', [
                'error' => $e->getMessage(),
                'model' => $model
            ]);
            
            return $this->handleError($e);
        }
    }
}