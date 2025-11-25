<?php

namespace App\Helpers\Ai\Services;

use App\Helpers\Ai\AiServices;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class OpenRouterService extends AiServices
{
    public $id = 1;

    public function send(array $params): array
    {
        // Проверяем настройки перед использованием
        $this->validateSettings();

        // Получаем параметры из настроек
        $apiKey = $this->getSetting('api_key');
        $projectId = $this->getSetting('project_id');

        try {
            $client = new Client();
            Log::info('START SEND');
            $params = [
                'model' => 'gpt-4', // Обязательный параметр
                'messages' => [     // Обязательный параметр
                    ['role' => 'user', 'content' => 'Ваш текст запроса']
                ],
                'temperature' => 0.7,     // Опционально (значение по умолчанию)
                'max_tokens' => 1000,     // Опционально (значение по умолчанию)
                'project_url' => 'https://core.myidon.site' // Для HTTP-Referer
            ];
            // Используем актуальные параметры из аргументов метода
            $response = $client->post('https://openrouter.ai/api/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'HTTP-Referer' => $params['project_url'] ?? 'https://core.myidon.site',
                    'X-Project-ID' => $projectId
                ],
                'json' => [
                    'model' => $params['model'] ?? 'gpt-4',
                    'messages' => $params['messages'],
                    'temperature' => $params['temperature'] ?? 0.7,
                    'max_tokens' => $params['max_tokens'] ?? 1000,
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            return [
                'success' => true,
                'data' => $data['choices'][0]['message']['content'] ?? '',
                'usage' => $data['usage'] ?? []
            ];

        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    public static function getName(): string
    {
        return 'OpenRouter';
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
}