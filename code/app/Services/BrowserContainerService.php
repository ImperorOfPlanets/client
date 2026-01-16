<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrowserContainerService
{
    private $host;
    private $timeout;
    private $browserServers = [];
    private $isConfigured = false;

    public function __construct()
    {
        Log::info('BrowserContainerService constructor called');
        $this->initializeConfiguration();
    }

    private function initializeConfiguration()
    {
        Log::debug('Starting Browser Container service configuration');
        
        try {
            $this->browserServers = $this->discoverBrowserServers();
            Log::debug('Discovered Browser Container servers', ['servers' => $this->browserServers]);
            
            if (empty($this->browserServers)) {
                Log::warning('No Browser Container servers discovered');
                $this->isConfigured = false;
                return;
            }
            
            $this->host = $this->getAvailableServer();
            
            if ($this->host) {
                $this->timeout = env('BROWSER_CONTAINER_TIMEOUT', 60);
                $this->isConfigured = true;
                
                Log::info('Browser Container service successfully initialized', [
                    'host' => $this->host,
                    'timeout' => $this->timeout,
                    'discovered_servers' => $this->browserServers
                ]);
            } else {
                Log::warning('No available Browser Container servers found from discovered list', [
                    'discovered_servers' => $this->browserServers
                ]);
                $this->isConfigured = false;
            }

        } catch (\Exception $e) {
            Log::error('Browser Container service configuration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->isConfigured = false;
        }
        
        Log::debug('Browser Container service configuration completed', ['is_configured' => $this->isConfigured]);
    }

    private function discoverBrowserServers()
    {
        $projectName = env('PROJECTNAME', 'core');
        Log::debug('Discovering Browser Container servers', ['project_name' => $projectName]);
        
        $servers = [
            // 1. Локальный контейнер с префиксом проекта и портом 8008
            'http://host.docker.internal:8100'
        ];
        
        // 2. Добавляем хост из .env если он задан
        $envHost = env('BROWSER_CONTAINER_HOST');
        Log::debug('BROWSER_CONTAINER_HOST from env', ['value' => $envHost]);
        
        if (!empty($envHost)) {
            $servers[] = $envHost;
        }
        
        $filteredServers = array_unique(array_filter($servers));
        Log::debug('Final discovered servers list', ['servers' => $filteredServers]);
        
        return $filteredServers;
    }

    public function isConfigured()
    {
        Log::debug('Browser Container service configuration check', ['is_configured' => $this->isConfigured]);
        return $this->isConfigured;
    }

    private function getAvailableServer()
    {
        Log::debug('Searching for available Browser Container server', ['servers_count' => count($this->browserServers)]);
        
        foreach ($this->browserServers as $index => $server) {
            Log::debug('Checking server availability', [
                'server' => $server,
                'attempt' => $index + 1
            ]);
            
            $isHealthy = $this->checkServerHealth($server);
            
            if ($isHealthy) {
                Log::info('Available Browser Container server found', [
                    'server' => $server,
                    'attempt' => $index + 1
                ]);
                return $server;
            }
            
            Log::debug('Server is not available', ['server' => $server]);
        }
        
        Log::warning('No available Browser Container servers found after checking all');
        return null;
    }

    private function checkServerHealth($server)
    {
        $healthUrl = rtrim($server, '/') . '/debug';
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
                'is_healthy' => $isOk
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

    /**
     * Выполнение цепочки команд в браузерном контейнере
     */
    public function executeCommands(array $commands, array $meta = [], ?string $requestId = null)
    {
        Log::debug('Browser Container executeCommands called', [
            'commands_count' => count($commands),
            'request_id' => $requestId,
            'meta' => $meta
        ]);
        
        if (!$this->isConfigured) {
            Log::warning('Browser Container service not configured for executeCommands');
            return [
                'status' => 'error',
                'error' => [
                    'code' => 'ServiceNotConfigured',
                    'message' => 'Browser Container service is not configured'
                ]
            ];
        }

        $requestId = $requestId ?: $this->generateRequestId();
        
        $payload = [
            'request_id' => $requestId,
            'commands' => $commands,
            'meta' => array_merge([
                'source' => 'laravel_service',
                'timestamp' => now()->toISOString()
            ], $meta)
        ];

        try {
            $url = "{$this->host}/execute"; // ИСПРАВЛЕНО: /command -> /execute
            Log::debug('Sending commands to browser container', [
                'url' => $url,
                'request_id' => $requestId,
                'commands_count' => count($commands)
            ]);

            $startTime = microtime(true);
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->timeout($this->timeout)
              ->post($url, $payload);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::debug('Browser Container response', [
                'status' => $response->status(),
                'response_time_ms' => $responseTime,
                'request_id' => $requestId
            ]);

            if ($response->successful()) {
                $result = $response->json();
                Log::info('Browser commands executed successfully', [
                    'request_id' => $requestId,
                    'status' => $result['status'] ?? 'unknown',
                    'response_time_ms' => $responseTime
                ]);
                return $result;
            } else {
                Log::error('Browser commands execution failed', [
                    'request_id' => $requestId,
                    'status_code' => $response->status(),
                    'response' => $response->body(),
                    'response_time_ms' => $responseTime
                ]);
                
                return [
                    'request_id' => $requestId,
                    'status' => 'error',
                    'error' => [
                        'code' => 'HttpError',
                        'message' => 'HTTP request failed with status: ' . $response->status()
                    ]
                ];
            }

        } catch (\Exception $e) {
            Log::error('Browser commands execution exception', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'request_id' => $requestId,
                'status' => 'error',
                'error' => [
                    'code' => 'InternalError',
                    'message' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Получить страницу отладки
     */
    public function getDebugPage()
    {
        Log::debug('Browser Container getDebugPage called');
        
        if (!$this->isConfigured) {
            Log::warning('Browser Container service not configured for getDebugPage');
            return null;
        }

        try {
            $url = "{$this->host}/debug";
            $response = Http::timeout($this->timeout)->get($url);

            if ($response->successful()) {
                Log::debug('Debug page retrieved successfully');
                return $response->body();
            }

            Log::error('Failed to retrieve debug page', [
                'status_code' => $response->status()
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('Debug page retrieval error', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Вспомогательные методы для конкретных сценариев
     */

    public function browseUrl(string $url, bool $screenshot = false, bool $text = false)
    {
        $commands = [
            [
                'action' => 'browse',
                'url' => $url,
                'options' => [
                    'screenshot' => $screenshot,
                    'text' => $text
                ]
            ]
        ];

        return $this->executeCommands($commands);
    }

    public function search(string $query, string $engine = 'google', bool $screenshot = false, bool $text = false)
    {
        $commands = [
            [
                'action' => 'search',
                'query' => $query,
                'engine' => $engine,
                'options' => [
                    'screenshot' => $screenshot,
                    'text' => $text
                ]
            ]
        ];

        return $this->executeCommands($commands);
    }

    public function extractText(string $selector)
    {
        $commands = [
            [
                'action' => 'extract_text',
                'selector' => $selector
            ]
        ];

        return $this->executeCommands($commands);
    }

    public function clickElement(string $selector)
    {
        $commands = [
            [
                'action' => 'click',
                'selector' => $selector
            ]
        ];

        return $this->executeCommands($commands);
    }

    public function fillForm(string $selector, string $value)
    {
        $commands = [
            [
                'action' => 'fill',
                'selector' => $selector,
                'value' => $value
            ]
        ];

        return $this->executeCommands($commands);
    }

    public function takeScreenshot(bool $fullPage = false)
    {
        $commands = [
            [
                'action' => 'screenshot',
                'full_page' => $fullPage
            ]
        ];

        return $this->executeCommands($commands);
    }

    /**
     * Комплексные сценарии
     */
    public function searchAndExtract(string $query, string $extractSelector, string $engine = 'google')
    {
        $commands = [
            [
                'action' => 'search',
                'query' => $query,
                'engine' => $engine,
                'options' => ['text' => true]
            ],
            [
                'action' => 'extract_text',
                'selector' => $extractSelector
            ]
        ];

        return $this->executeCommands($commands);
    }

    public function searchAndClickFirst(string $query, string $engine = 'google')
    {
        $commands = [
            [
                'action' => 'search',
                'query' => $query,
                'engine' => $engine,
                'options' => ['text' => true]
            ],
            [
                'action' => 'click',
                'selector' => '$results[0].items[0]'
            ],
            [
                'action' => 'screenshot'
            ]
        ];

        return $this->executeCommands($commands);
    }

    /**
     * Проверка здоровья сервиса
     */
    public function healthCheck()
    {
        Log::debug('Browser Container health check called');
        
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
     * Получить информацию для отладки
     */
    public function getDebugInfo()
    {
        return [
            'is_configured' => $this->isConfigured,
            'host' => $this->host,
            'timeout' => $this->timeout,
            'discovered_servers' => $this->browserServers,
            'project_name' => env('PROJECTNAME', 'core'),
            'browser_container_host_env' => env('BROWSER_CONTAINER_HOST')
        ];
    }

    /**
     * Генерация уникального ID запроса
     */
    private function generateRequestId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}