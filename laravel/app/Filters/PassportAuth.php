<?php

namespace App\Filters;

use App\Models\Assistant\MessagesModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class PassportAuth extends Filter
{
    private $passportBaseUrl;
    private $timeout = 5;
    private $cacheTtl = 300;
    private $debug_mode = true;

    public function __construct()
    {
        parent::__construct();
        $this->loadPassportConfig();

        Log::info('🔧 PassportAuth filter initialized', [
            'filter_id' => $this->getFilterId(),
            'filter_name' => $this->getFilterName(),
            'debug_mode' => $this->debug_mode
        ]);
    }

    protected function loadPassportConfig(): void
    {
        $this->passportBaseUrl = $this->getParameter('passport_base_url', 'https://myidon.site');
        $this->timeout = $this->getParameter('timeout_seconds', 5);
        $this->cacheTtl = $this->getParameter('cache_ttl_minutes', 5) * 60;

        Log::debug('Passport config loaded', [
            'base_url' => $this->passportBaseUrl,
            'timeout' => $this->timeout
        ]);
    }

    public function handle(MessagesModel $message): array
    {
        Log::info('🎫 PassportAuth STARTED', [
            'message_id' => $message->id,
            'social_id' => $message->soc,
            'provider_user_id' => isset($message->info['from']) ? $message->info['from'] : null,
        ]);

        // Отправляем отладочное сообщение о начале обработки
        $this->sendDebugMessage($message, "Начало обработки авторизации Passport");

        // Устанавливаем максимальное время выполнения
        set_time_limit($this->timeout + 2);

        // Пропускаем социальную сеть 38 если включено исключение
        $excludeSocial38 = $this->getParameter('exclude_social_38', true);
        if ($excludeSocial38 && $message->soc == 38) {
            $this->sendDebugMessage($message, "Пропуск проверки для социальной сети 38");
            
            Log::info('🔓 Пропуск проверки для социальной сети 38', [
                'message_id' => $message->id,
                'social_id' => $message->soc
            ]);
            
            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
                'reason' => 'social_38_excluded'
            ]);
        }

        $providerUserId = isset($message->info['from']) ? $message->info['from'] : null;
        if (empty($providerUserId)) {
            $this->sendDebugMessage($message, "Отсутствует provider_user_id");
            
            Log::warning('Missing provider_user_id', ['message_id' => $message->id]);
            $this->saveAuthResult($message, null, 'missing_provider_user_id');
            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
                'reason' => 'missing_provider_user_id'
            ]);
        }

        $provider = $this->getProviderBySocialId($message->soc);
        if (!$provider) {
            $this->sendDebugMessage($message, "Провайдер не найден для социальной сети", [
                'social_id' => $message->soc
            ]);
            
            Log::warning('Provider not found for social network', [
                'social_id' => $message->soc,
                'message_id' => $message->id
            ]);
            $this->saveAuthResult($message, null, 'unknown_social_network');
            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
                'reason' => 'unknown_social_network'
            ]);
        }

        // Проверяем кэш
        $cacheEnabled = $this->getParameter('enable_cache', true);
        if ($cacheEnabled) {
            $cachedAuth = $this->getCachedAuth($provider, $providerUserId);
            if ($cachedAuth !== null) {
                $cachedUserId = isset($cachedAuth['user_id']) ? $cachedAuth['user_id'] : null;
                $this->sendDebugMessage($message, "Использован кэшированный результат авторизации", [
                    'user_id' => $cachedUserId
                ]);
                
                Log::info('Using cached auth data', [
                    'message_id' => $message->id,
                    'user_id' => $cachedUserId,
                    'from_cache' => true
                ]);
                
                $this->saveAuthResult($message, $cachedUserId, 'cached_success', true);
                
                // Отправляем дебаг-сообщение для кэшированного результата
                if ($this->debug_mode) {
                    Log::info('🔍 Sending debug notification for cached auth');
                    $this->sendDebugNotification($message, $cachedUserId, true);
                }
                
                return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
                    'reason' => 'passport_auth_success_from_cache',
                    'passport_user_id' => $cachedUserId,
                    'from_cache' => true
                ]);
            }
        }

        // СИНХРОННЫЙ ЗАПРОС С ГАРАНТИРОВАННЫМ ТАЙМАУТОМ
        $this->sendDebugMessage($message, "Выполнение запроса к Passport API", [
            'provider' => $provider,
            'provider_user_id' => $providerUserId
        ]);
        
        Log::info('🚀 Making SYNC Passport request', [
            'message_id' => $message->id,
            'timeout' => $this->timeout,
            'provider' => $provider,
            'provider_user_id' => $providerUserId
        ]);

        $authResult = $this->makeSyncPassportRequest($message, $provider, $providerUserId);

        // Кэшируем результат если нужно
        if ($cacheEnabled && isset($authResult['success'])) {
            $this->cacheAuthResult($provider, $providerUserId, $authResult);
        }

        // Обрабатываем результат
        if ($authResult['success'] && isset($authResult['user_id'])) {
            $this->sendDebugMessage($message, "Успешная авторизация Passport", [
                'user_id' => $authResult['user_id'],
                'from_cache' => false
            ]);
            
            Log::info('✅ Passport auth SUCCESS', [
                'message_id' => $message->id,
                'user_id' => $authResult['user_id']
            ]);
            
            $this->saveAuthResult($message, $authResult['user_id'], 'auth_success', false);
            
            // Отправляем дебаг-сообщение при успешной авторизации
            if ($this->debug_mode) {
                Log::info('🔍 Sending debug notification for successful auth');
                $this->sendDebugNotification($message, $authResult['user_id'], false, $authResult);
            }
            
            if ($this->getParameter('send_auth_notification', false)) {
                $this->sendAuthSuccessNotification($message, $authResult['user_id']);
            }

            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
                'reason' => 'passport_auth_success',
                'passport_user_id' => $authResult['user_id'],
                'from_cache' => false
            ]);
        } else {
            $error = isset($authResult['error']) ? $authResult['error'] : 'Unknown error';
            $this->sendDebugMessage($message, "Ошибка авторизации Passport", [
                'error' => $error
            ]);
            
            Log::warning('❌ Passport auth FAILED, but continuing', [
                'message_id' => $message->id,
                'error' => $error
            ]);
            
            $this->saveAuthResult($message, null, 'auth_failed', false, $error);
            
            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
                'reason' => 'passport_auth_failed_but_continue',
                'error' => $error
            ]);
        }
    }

    /**
     * Отправка дебаг-сообщения при успешной авторизации
     */
    protected function sendDebugNotification(MessagesModel $message, $userId, bool $fromCache = false, array $authResult = []): void
    {
        try {
            $userInfo = isset($authResult['user_info']) ? $authResult['user_info'] : [];
            
            $text = "🔍 **Режим отладки: Авторизация Passport**\n\n";
            $text .= "✅ Вы определены в системе как пользователь\n\n";
            $text .= "👤 **ID пользователя:** `{$userId}`\n";
            
            if (!empty($userInfo)) {
                $userName = isset($userInfo['name']) ? $userInfo['name'] : 'Не указано';
                $userEmail = isset($userInfo['email']) ? $userInfo['email'] : 'Не указан';
                $providerName = isset($userInfo['provider_name']) ? $userInfo['provider_name'] : 'Не указано';
                
                $text .= "📛 **Имя:** {$userName}\n";
                $text .= "📧 **Email:** {$userEmail}\n";
                $text .= "👥 **Имя в соцсети:** {$providerName}\n";
            }
            
            $text .= "🔗 **Провайдер:** " . $this->getProviderBySocialId($message->soc) . "\n";
            $providerUserId = isset($message->info['from']) ? $message->info['from'] : 'Неизвестно';
            $text .= "🆔 **ID в соцсети:** `{$providerUserId}`\n";
            
            if ($fromCache) {
                $text .= "💾 **Источник:** Данные из кэша\n";
            } else {
                $text .= "🔄 **Источник:** Прямой запрос к API\n";
            }
            
            $text .= "\n⏱ **Время:** " . now()->format('H:i:s');

            Log::info('🔍 Sending debug notification to user', [
                'message_id' => $message->id,
                'user_id' => $userId,
                'text_length' => strlen($text)
            ]);

            self::sendMessage($message, $text);
            
            Log::info('Debug notification sent', [
                'message_id' => $message->id,
                'user_id' => $userId,
                'from_cache' => $fromCache
            ]);
        } catch (\Throwable $e) {
            Log::error('Error sending debug notification', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Синхронный запрос с гарантированным таймаутом
     */
    protected function makeSyncPassportRequest(MessagesModel $message, string $provider, string $providerUserId): array
    {
        $startTime = microtime(true);

        try {
            // Генерируем callback_id и state
            $callbackId = $this->generateCallbackId($message);
            $state = $this->generateState($callbackId);

            $requestData = [
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                'callback_id' => $callbackId,
                'state' => $state,
            ];

            Log::debug('Sending Passport API request', [
                'message_id' => $message->id,
                'request_data' => $requestData
            ]);

            $response = Http::timeout($this->timeout)
                ->connectTimeout(3)
                ->retry(0)
                ->withHeaders([
                    'User-Agent' => 'AssistantBot/1.0',
                    'Accept' => 'application/json',
                ])
                ->post("{$this->passportBaseUrl}/socials/userinfo", $requestData);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('📨 Passport API response received', [
                'message_id' => $message->id,
                'status' => $response->status(),
                'response_time_ms' => $responseTime
            ]);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::debug('Passport API success response', [
                    'message_id' => $message->id,
                    'response' => $result
                ]);
                
                return $result;
            } else {
                $errorResponse = $response->body();
                Log::warning('Passport API error response', [
                    'message_id' => $message->id,
                    'status' => $response->status(),
                    'response' => $errorResponse
                ]);
                
                return [
                    'success' => false,
                    'error' => 'HTTP Error: ' . $response->status(),
                    'details' => $errorResponse
                ];
            }

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('🔌 Passport API connection timeout', [
                'message_id' => $message->id,
                'timeout' => $this->timeout,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => 'Connection timeout',
                'timeout' => true
            ];
        } catch (\Exception $e) {
            Log::error('💥 Passport API request error', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Генерация callback_id по формату
     */
    protected function generateCallbackId(MessagesModel $message): string
    {
        $callbackId = "social_{$message->soc}_chat_{$message->chat_id}_message_{$message->id}";

        Log::debug('Callback ID generated', [
            'message_id' => $message->id,
            'callback_id' => $callbackId
        ]);

        return $callbackId;
    }

    /**
     * Генерация state по формату (первые 4 символа callback_id + случайная строка)
     */
    protected function generateState(string $callbackId): string
    {
        // Берем первые 4 символа callback_id
        $callbackPrefix = substr($callbackId, 0, 4);
        $random = bin2hex(random_bytes(8));
        $state = "{$callbackPrefix}_{$random}";

        Log::debug('State generated', [
            'callback_id' => $callbackId,
            'callback_prefix' => $callbackPrefix,
            'state' => $state
        ]);

        return $state;
    }

    /**
     * Получение провайдера по ID социальной сети
     */
    protected function getProviderBySocialId(int $socialId): ?string
    {
        $providerMap = [
            12 => 'telegram',
            13 => 'vkontakte',
            3  => 'whatsapp', 
            4  => 'viber',
            5  => 'facebook',
            6  => 'instagram'
        ];

        $provider = isset($providerMap[$socialId]) ? $providerMap[$socialId] : null;
        
        Log::debug('Provider mapping', [
            'social_id' => $socialId,
            'provider' => $provider,
            'available_map' => $providerMap
        ]);

        return $provider;
    }

    protected function getCachedAuth(string $provider, string $providerUserId): ?array
    {
        $cacheKey = "passport_auth:{$provider}:{$providerUserId}";
        return Cache::get($cacheKey);
    }

    protected function cacheAuthResult(string $provider, string $providerUserId, array $authResult): void
    {
        $cacheKey = "passport_auth:{$provider}:{$providerUserId}";
        
        $cacheData = [
            'success' => $authResult['success'],
            'user_id' => isset($authResult['user_id']) ? $authResult['user_id'] : null,
            'cached_at' => now()->timestamp
        ];

        Cache::put($cacheKey, $cacheData, $this->cacheTtl);

        Log::debug('Auth result cached', [
            'cache_key' => $cacheKey,
            'user_id' => isset($authResult['user_id']) ? $authResult['user_id'] : null
        ]);
    }

    protected function saveAuthResult(MessagesModel $message, $userId, string $reason, bool $fromCache = false, $error = null): void
    {
        try {
            $info = isset($message->info) ? $message->info : [];
            // Сохраняем user_id на первом уровне в info
            if (!is_null($userId)) {
                $info['user_id'] = $userId;
                Log::debug('User ID saved to top level info', [
                    'message_id' => $message->id,
                    'user_id' => $userId
                ]);
            }
            $info['passport_auth'] = [
                'authenticated' => !is_null($userId),
                'user_id' => $userId,
                'reason' => $reason,
                'from_cache' => $fromCache,
                'attempted_at' => now()->toISOString(),
                'provider' => $this->getProviderBySocialId($message->soc),
                'provider_user_id' => isset($message->info['from']) ? $message->info['from'] : null
            ];

            if ($error) {
                $info['passport_auth']['error'] = $error;
            }

            $message->info = $info;
            $message->save();

            Log::debug('Auth result saved to message', [
                'message_id' => $message->id,
                'user_id' => $userId,
                'reason' => $reason
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save auth result to message', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function sendAuthSuccessNotification(MessagesModel $message, int $userId): void
    {
        try {
            $text = "✅ Authorization successful! User ID: {$userId}";
            self::sendMessage($message, $text);
            
            Log::info('Auth success notification sent', [
                'message_id' => $message->id,
                'user_id' => $userId
            ]);
        } catch (\Throwable $e) {
            Log::error('Error sending auth notification', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function processSavedData(MessagesModel $message, array $result): array
    {
        return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED);
    }

    /**
     * Получение структуры параметров для PassportAuth
     */
    public function getParametersStructure(): array
    {
        $parentStructure = parent::getParametersStructure();
        
        return array_merge($parentStructure, [
            'passport_base_url' => [
                'type' => 'text',
                'label' => 'Base URL для Passport',
                'description' => 'Базовый URL для API Passport',
                'default' => 'https://myidon.site',
                'required' => true
            ],
            'timeout_seconds' => [
                'type' => 'number',
                'label' => 'Таймаут запроса (секунды)',
                'description' => 'Максимальное время ожидания ответа от Passport',
                'default' => 5,
                'required' => true
            ],
            'cache_ttl_minutes' => [
                'type' => 'number',
                'label' => 'Время кэширования (минуты)',
                'description' => 'Время хранения результата авторизации в кэше',
                'default' => 5,
                'required' => true
            ],
            'enable_cache' => [
                'type' => 'boolean',
                'label' => 'Включить кэширование',
                'description' => 'Сохранять ли результат авторизации в кэше',
                'default' => true,
                'required' => false
            ],
            'send_auth_notification' => [
                'type' => 'boolean',
                'label' => 'Отправлять уведомление об авторизации',
                'description' => 'Отправлять ли сообщение об успешной авторизации',
                'default' => false,
                'required' => false
            ],
            'exclude_social_38' => [
                'type' => 'boolean',
                'label' => 'Исключить социальную сеть 38',
                'description' => 'Пропускать проверку для социальной сети с ID 38',
                'default' => true,
                'required' => false
            ],
            'debug_mode' => [
                'type' => 'boolean',
                'label' => 'Режим отладки (устаревшее)',
                'description' => 'Используйте настройки отладки выше',
                'default' => true,
                'required' => false
            ]
        ]);
    }
}