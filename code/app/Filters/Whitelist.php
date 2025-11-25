<?php

namespace App\Filters;

use App\Models\Assistant\MessagesModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Whitelist extends Filter
{
    protected array $whitelistConfig = [];

    public function __construct()
    {
        parent::__construct();
        $this->applyParameters();
        $this->loadWhitelistConfig();

        Log::info('Whitelist filter initialized', [
            'filter_id' => $this->getFilterId(),
            'filter_name' => $this->getFilterName(),
            'socials_count' => count($this->whitelistConfig)
        ]);
    }

    /**
     * Определение структуры параметров для управления белым списком
     */
    public function getParametersStructure(): array
    {
        $parentStructure = parent::getParametersStructure();
        
        return array_merge($parentStructure, [
            'allowed_user_ids' => [
                'type' => 'textarea',
                'label' => 'Разрешенные ID пользователей',
                'description' => 'Список ID пользователей через запятую. Если не пусто, то разрешены только указанные пользователи. Если пусто, то используется стандартная проверка по белому списку.',
                'default' => '',
                'required' => false,
                'placeholder' => '123, 456, 789'
            ],
            'auto_deny_empty_config' => [
                'type' => 'boolean',
                'label' => 'Автоматически запрещать при пустой конфигурации',
                'description' => 'Если включено, при отсутствии конфигурации доступ будет запрещен',
                'default' => true,
                'required' => false
            ],
            'send_notification' => [
                'type' => 'boolean',
                'label' => 'Отправлять уведомление об отказе',
                'description' => 'Отправлять ли сообщение пользователю при отказе в доступе',
                'default' => true,
                'required' => false
            ],
            'notification_text' => [
                'type' => 'textarea',
                'label' => 'Текст уведомления',
                'description' => 'Текст сообщения при отказе в доступе',
                'default' => "❌ Доступ к ассистенту ограничен.\n\nВаш аккаунт или чат не входят в белый список разрешенных пользователей.",
                'required' => false
            ],
            'social_networks' => [
                'type' => 'social_networks',
                'label' => 'Социальные сети и пользователи',
                'description' => 'Настройка белого списка для каждой социальной сети',
                'default' => [],
                'required' => false
            ]
        ]);
    }

    /**
     * Загрузка конфигурации белого списка
     */
    protected function loadWhitelistConfig(): void
    {
        // Сначала пробуем загрузить из параметров фильтра
        $parametersConfig = $this->loadConfigFromParameters();
        
        if (!empty($parametersConfig)) {
            $this->whitelistConfig = $parametersConfig;
            Log::info('Whitelist config loaded from parameters', [
                'socials_count' => count($this->whitelistConfig)
            ]);
            return;
        }

        // Если в параметрах нет, загружаем из файла (для обратной совместимости)
        if (!Storage::disk('local')->exists('filters/whitelist.json')) {
            Log::warning('Whitelist config file not found');
            return;
        }

        try {
            $json = Storage::disk('local')->get('filters/whitelist.json');
            $this->whitelistConfig = json_decode($json, true) ?? [];

            Log::info('Whitelist config loaded from file', [
                'socials_count' => count($this->whitelistConfig),
                'socials' => array_keys($this->whitelistConfig)
            ]);
        } catch (\Throwable $e) {
            Log::error('Error loading whitelist config: ' . $e->getMessage());
        }
    }

    /**
     * Загрузка конфигурации из параметров фильтра
     */
    protected function loadConfigFromParameters(): array
    {
        $socialNetworksParam = $this->getParameter('social_networks', []);
        
        if (empty($socialNetworksParam)) {
            return [];
        }

        $config = [];
        
        foreach ($socialNetworksParam as $socialId => $socialConfig) {
            if (!isset($socialConfig['enabled']) || !$socialConfig['enabled']) {
                continue;
            }

            $config[$socialId] = [
                'enabled' => true,
                'users' => [
                    'access' => $socialConfig['users_access'] ?? 'deny_all',
                    'define' => $this->parseUserList($socialConfig['users_list'] ?? '')
                ],
                'groups' => [
                    'access' => $socialConfig['groups_access'] ?? 'deny_all',
                    'define' => $this->parseGroupList($socialConfig['groups_list'] ?? '')
                ]
            ];
        }

        return $config;
    }

    /**
     * Парсинг списка пользователей из текстового поля
     */
    protected function parseUserList(string $usersText): array
    {
        if (empty($usersText)) {
            return [];
        }

        $users = [];
        $lines = explode("\n", $usersText);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Формат: user_id|username (username опционально)
            $parts = explode('|', $line);
            $userId = trim($parts[0]);
            
            if (!empty($userId)) {
                $users[] = $userId;
            }
        }

        return $users;
    }

    /**
     * Парсинг списка групп из текстового поля
     */
    protected function parseGroupList(string $groupsText): array
    {
        if (empty($groupsText)) {
            return [];
        }

        $groups = [];
        $lines = explode("\n", $groupsText);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $groups[] = $line;
            }
        }

        return $groups;
    }

    public function handle(MessagesModel $message): array
    {
        $socialId = $message->soc;
        $userId = $message->info['from'] ?? null;
        $chatId = $message->chat_id;
        $isGroup = $message->info['is_group'] ?? false;

        $this->sendDebugMessage($message, "Проверка белого списка", [
            'social_id' => $socialId,
            'user_id' => $userId,
            'chat_id' => $chatId,
            'is_group' => $isGroup
        ]);

        Log::info('Обработка Whitelist фильтра', [
            'message_id' => $message->id,
            'social_id' => $socialId,
            'user_id' => $userId,
            'chat_id' => $chatId,
            'is_group' => $isGroup,
            'filter_id' => $this->getFilterId()
        ]);

        // Сначала проверяем глобальный список разрешенных пользователей
        $globalAccess = $this->checkGlobalUserAccess($message);
        if ($globalAccess !== null) {
            if ($globalAccess) {
                $this->sendDebugMessage($message, "Доступ разрешен по глобальному списку пользователей");
                return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
                    'reason' => 'access_granted_by_global_whitelist'
                ]);
            } else {
                $this->sendDebugMessage($message, "Доступ запрещен по глобальному списку пользователей");
                $this->sendAccessDeniedMessage($message);
                return $this->createResponse(false, self::DECISION_REJECT, self::STATUS_COMPLETED, [
                    'reason' => 'access_denied_by_global_whitelist'
                ]);
            }
        }

        // Проверяем доступ через стандартную логику
        $hasAccess = $this->checkAccess($socialId, $userId, $chatId, $isGroup);

        if (!$hasAccess) {
            $this->sendDebugMessage($message, "Доступ запрещен по белому списку", [
                'reason' => 'access_denied',
                'social_id' => $socialId,
                'user_id' => $userId
            ]);

            Log::warning('Доступ запрещен по Whitelist', [
                'message_id' => $message->id,
                'social_id' => $socialId,
                'user_id' => $userId,
                'chat_id' => $chatId,
                'is_group' => $isGroup
            ]);

            $this->sendAccessDeniedMessage($message);

            return $this->createResponse(false, self::DECISION_REJECT, self::STATUS_COMPLETED, [
                'reason' => 'access_denied_by_whitelist',
                'social_id' => $socialId,
                'user_id' => $userId,
                'chat_id' => $chatId,
                'is_group' => $isGroup
            ]);
        }

        $this->sendDebugMessage($message, "Доступ разрешен по белому списку", [
            'social_id' => $socialId,
            'user_id' => $userId
        ]);

        Log::info('Доступ разрешен по Whitelist', [
            'message_id' => $message->id,
            'social_id' => $socialId,
            'user_id' => $userId
        ]);

        return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
            'reason' => 'access_granted_by_whitelist'
        ]);
    }

    /**
     * Проверка доступа по глобальному списку пользователей
     */
    protected function checkGlobalUserAccess(MessagesModel $message): ?bool
    {
        $allowedUserIdsParam = $this->getParameter('allowed_user_ids', '');
        
        // Если параметр пустой - используем стандартную логику
        if (empty($allowedUserIdsParam)) {
            return null;
        }

        $userId = $message->info['user_id'] ?? null;
        
        if (!$userId) {
            Log::warning('Global whitelist check: user_id not found in message info', [
                'message_id' => $message->id
            ]);
            return false;
        }

        $allowedUserIds = array_map('trim', explode(',', $allowedUserIdsParam));
        $allowedUserIds = array_filter($allowedUserIds); // Убираем пустые значения

        $hasAccess = in_array((string)$userId, $allowedUserIds);

        Log::info('Global whitelist check', [
            'message_id' => $message->id,
            'user_id' => $userId,
            'allowed_ids' => $allowedUserIds,
            'has_access' => $hasAccess
        ]);

        return $hasAccess;
    }

    /**
     * Проверка доступа пользователя/группы
     */
    protected function checkAccess(int $socialId, ?string $userId, string $chatId, bool $isGroup): bool
    {
        $autoDenyEmpty = $this->getParameter('auto_deny_empty_config', true);

        // Если конфигурация пустая
        if (empty($this->whitelistConfig)) {
            Log::warning('Whitelist config is empty', ['auto_deny' => $autoDenyEmpty]);
            return !$autoDenyEmpty; // Если auto_deny_empty_config = true, то запрещаем
        }

        // Проверяем наличие конфигурации для социальной сети
        if (!isset($this->whitelistConfig[$socialId])) {
            Log::warning('Social ID not found in whitelist config', ['social_id' => $socialId]);
            return !$autoDenyEmpty;
        }

        $socialConfig = $this->whitelistConfig[$socialId];

        // Проверяем включен ли белый список для этой соцсети
        if (!($socialConfig['enabled'] ?? false)) {
            Log::info('Whitelist disabled for social network', ['social_id' => $socialId]);
            return true;
        }

        if ($isGroup) {
            return $this->checkGroupAccess($socialConfig, $chatId);
        } else {
            return $this->checkUserAccess($socialConfig, $userId);
        }
    }

    /**
     * Проверка доступа для пользователя
     */
    protected function checkUserAccess(array $socialConfig, ?string $userId): bool
    {
        if (!$userId) {
            Log::warning('User ID is empty');
            return false;
        }

        $usersConfig = $socialConfig['users'] ?? [];
        $accessType = $usersConfig['access'] ?? 'deny_all';

        Log::debug('Checking user access', [
            'user_id' => $userId,
            'access_type' => $accessType,
            'defined_users' => $usersConfig['define'] ?? []
        ]);

        switch ($accessType) {
            case 'allow_all':
                return true;

            case 'allow_defined':
                $allowedUsers = $usersConfig['define'] ?? [];
                return in_array($userId, $allowedUsers);

            case 'deny_all':
            default:
                return false;
        }
    }

    /**
     * Проверка доступа для группы
     */
    protected function checkGroupAccess(array $socialConfig, string $chatId): bool
    {
        $groupsConfig = $socialConfig['groups'] ?? [];
        $accessType = $groupsConfig['access'] ?? 'deny_all';

        Log::debug('Checking group access', [
            'chat_id' => $chatId,
            'access_type' => $accessType,
            'defined_groups' => $groupsConfig['define'] ?? []
        ]);

        switch ($accessType) {
            case 'allow_all':
                return true;

            case 'allow_defined':
                $allowedGroups = $groupsConfig['define'] ?? [];
                return in_array($chatId, $allowedGroups);

            case 'deny_all':
            default:
                return false;
        }
    }

    /**
     * Отправка сообщения об отказе в доступе
     */
    protected function sendAccessDeniedMessage(MessagesModel $message): void
    {
        $sendNotification = $this->getParameter('send_notification', true);
        
        if (!$sendNotification) {
            return;
        }

        try {
            $text = $this->getParameter('notification_text', 
                "❌ Доступ к ассистенту ограничен.\n\nВаш аккаунт или чат не входят в белый список разрешенных пользователей.");

            self::sendMessage($message, $text);

            Log::info('Сообщение об отказе в доступе отправлено', [
                'message_id' => $message->id,
                'chat_id' => $message->chat_id
            ]);
        } catch (\Throwable $e) {
            Log::error('Ошибка отправки сообщения об отказе в доступе', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function processSavedData(MessagesModel $message, array $result): array
    {
        Log::info('Обработка сохраненных данных в фильтре Whitelist', [
            'message_id' => $message->id,
            'result_keys' => array_keys($result)
        ]);

        // Whitelist фильтр не использует асинхронную обработку,
        // поэтому всегда продолжаем цепочку
        return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
            'reason' => 'whitelist_sync_processing_completed'
        ]);
    }

    /**
     * Валидация параметров для специального типа social_networks
     */
    protected function validateParameters(array $parameters): array
    {
        $structure = $this->getParametersStructure();
        $validated = [];

        foreach ($structure as $key => $config) {
            $value = $parameters[$key] ?? $config['default'] ?? null;

            // Специальная обработка для social_networks
            if ($config['type'] === 'social_networks' && is_array($value)) {
                $validated[$key] = $this->validateSocialNetworksConfig($value);
                continue;
            }

            // Стандартная валидация типов
            switch ($config['type'] ?? 'text') {
                case 'number':
                    $value = is_numeric($value) ? (int)$value : ($config['default'] ?? 0);
                    break;
                case 'boolean':
                    $value = (bool)$value;
                    break;
                case 'textarea':
                    // Для textarea просто оставляем как есть
                    break;
                default:
                    // Для text и других типов
                    $value = (string)$value;
            }

            $validated[$key] = $value;
        }

        return $validated;
    }

    /**
     * Валидация конфигурации социальных сетей
     */
    protected function validateSocialNetworksConfig(array $socialNetworks): array
    {
        $validated = [];

        foreach ($socialNetworks as $socialId => $config) {
            $socialId = (int)$socialId;
            if ($socialId <= 0) {
                continue;
            }

            $validated[$socialId] = [
                'enabled' => (bool)($config['enabled'] ?? false),
                'users_access' => in_array($config['users_access'] ?? 'deny_all', ['allow_all', 'allow_defined', 'deny_all']) 
                    ? $config['users_access'] 
                    : 'deny_all',
                'users_list' => (string)($config['users_list'] ?? ''),
                'groups_access' => in_array($config['groups_access'] ?? 'deny_all', ['allow_all', 'allow_defined', 'deny_all']) 
                    ? $config['groups_access'] 
                    : 'deny_all',
                'groups_list' => (string)($config['groups_list'] ?? ''),
            ];
        }

        return $validated;
    }
}