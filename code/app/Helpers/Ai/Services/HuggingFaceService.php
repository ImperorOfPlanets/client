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

    public function parseCommandsResponse(array $response): array
    {
        Log::info('HuggingFaceService: Парсинг ответа для командного фильтра', [
            'response_keys' => array_keys($response),
            'has_data' => isset($response['data'])
        ]);

        $result = [
            'success' => false,
            'is_command' => false,
            'command_id' => null,
            'found_keyword' => null,
            'found_in_part' => null,
            'confidence' => null,
            'raw_text' => '',
            'parsed_data' => null,
            'filter_type' => 'commands',
            'provider' => self::getName()
        ];

        try {
            if (!isset($response['success']) || $response['success'] !== true) {
                return $result;
            }

            // Извлекаем текст из структуры HuggingFace
            $textResponse = '';
            if (isset($response['data']) && is_string($response['data'])) {
                $textResponse = $response['data'];
            } elseif (isset($response['choices'][0]['message']['content'])) {
                $textResponse = $response['choices'][0]['message']['content'];
            }

            if (empty($textResponse)) {
                Log::warning('Не удалось извлечь текст из ответа HuggingFace', [
                    'response_structure' => $response
                ]);
                return $result;
            }

            $result['raw_text'] = $textResponse;
            $result['success'] = true;

            // Парсим JSON из текста
            $parsedData = $this->parseJsonFromText($textResponse);
            
            if ($parsedData) {
                $result['parsed_data'] = $parsedData;
                $result['is_command'] = $parsedData['is_command'] ?? false;
                $result['command_id'] = $parsedData['command_id'] ?? null;
                $result['found_keyword'] = $parsedData['found_keyword'] ?? null;
                $result['found_in_part'] = $parsedData['found_in_part'] ?? null;
                $result['confidence'] = $parsedData['confidence'] ?? null;
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error('HuggingFaceService: Ошибка парсинга ответа для команд', [
                'error' => $e->getMessage(),
                'response' => $response
            ]);
            return $result;
        }
    }

    private function parseJsonFromText(string $text): ?array
    {
        try {
            // Удаляем markdown code блоки если есть
            $cleanText = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/m', '', $text);
            
            // Ищем JSON в тексте
            $jsonStart = strpos($cleanText, '{');
            $jsonEnd = strrpos($cleanText, '}');
            
            if ($jsonStart !== false && $jsonEnd !== false) {
                $jsonString = substr($cleanText, $jsonStart, $jsonEnd - $jsonStart + 1);
                $data = json_decode($jsonString, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
            }
            
            // Пробуем декодировать весь текст
            $data = json_decode($cleanText, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
            
            return null;
        } catch (\Throwable $e) {
            Log::warning('Ошибка парсинга JSON из текста', [
                'text' => $text,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}