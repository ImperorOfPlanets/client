<?php

namespace App\Helpers\Ai\Services;

use App\Helpers\Ai\AiServices;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class VoidAiService extends AiServices
{
    public $id = 2;

    public function send(array $params): array
    {
        Log::info('VoidAiService: Starting request processing', ['params' => $params]);
        $this->validateSettings();
        
        $apiKey = $this->getSetting('api_key');
        $projectId = $this->getSetting('project_id');
        
        Log::debug('VoidAiService: Retrieved settings', [
            'api_key' => substr($apiKey, 0, 3) . '...', // Частичное логирование ключа
            'project_id' => $projectId
        ]);

        try {
            $client = new Client();
            
            $defaultParams = [
                'prompt' => '',
                'length' => 500,
                'temperature' => 0.7
            ];
            
            $mergedParams = array_merge($defaultParams, $params);
            Log::debug('VoidAiService: Merged request parameters', $mergedParams);

            $startTime = microtime(true);
            $response = $client->post('https://api.voidai.app/v1', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'X-Project-ID' => $projectId,
                ],
                'json' => [
                    'prompt' => $mergedParams['prompt'],
                    'length' => $mergedParams['length'],
                    'creativity' => $mergedParams['temperature'],
                ],
                'timeout' => 30
            ]);
            
            $responseTime = microtime(true) - $startTime;
            $responseBody = $response->getBody()->getContents();
            $data = json_decode($responseBody, true);
            
            Log::info('VoidAiService: Successful API response', [
                'status_code' => $response->getStatusCode(),
                'response_time' => $responseTime,
                'response_data' => $data
            ]);

            return [
                'success' => true,
                'data' => $data['generated_text'] ?? '',
                'meta' => array_merge(
                    $data['meta'] ?? [],
                    [
                        'api_response_time' => $responseTime,
                        'api_status_code' => $response->getStatusCode()
                    ]
                )
            ];

        } catch (RequestException $e) {
            $response = $e->getResponse();
            $errorResponse = $response ? json_decode($response->getBody()->getContents(), true) : null;
            
            Log::error('VoidAiService: API request failed', [
                'error' => $e->getMessage(),
                'status_code' => $response ? $response->getStatusCode() : null,
                'response' => $errorResponse,
                'request_params' => $mergedParams ?? $params
            ]);
            
            return $this->handleError($e, [
                'api_status_code' => $response ? $response->getStatusCode() : null,
                'api_error_response' => $errorResponse
            ]);
            
        } catch (\Exception $e) {
            Log::error('VoidAiService: Unexpected error', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ]);
            
            return $this->handleError($e);
        }
    }

    protected function handleError(\Throwable $e, array $additionalData = []): array
    {
        $errorData = [
            'success' => false,
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'exception' => get_class($e)
        ];
        
        if (config('app.debug')) {
            $errorData['trace'] = $e->getTraceAsString();
        }
        
        return array_merge($errorData, $additionalData);
    }

    public static function getName(): string
    {
        return 'VoidAi';
    }

    public static function getRequiredSettings(): array
    {
        return [
            [
                'key' => 'api_key',
                'label' => 'API Ключ',
                'description' => 'Введите ваш уникальный API ключ.',
                'required' => true
            ],
            [
                'key' => 'project_id',
                'label' => 'ID проекта',
                'description' => 'Введите идентификатор вашего проекта.',
                'required' => true
            ]
        ];
    }

    public function getSettings(): array
    {
        return $this->settings ?? [];
    }

    public function validateSettings(): bool
    {
        $required = array_column($this->getRequiredSettings(), 'key');
        $missing = [];
        
        foreach ($required as $key) {
            if (empty($this->settings[$key])) {
                $missing[] = $key;
            }
        }
        
        if (!empty($missing)) {
            Log::error('VoidAiService: Missing required settings', ['missing_settings' => $missing]);
            throw new \RuntimeException("Missing required settings: " . implode(', ', $missing));
        }
        
        Log::debug('VoidAiService: Settings validation passed');
        return true;
    }

    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }
}