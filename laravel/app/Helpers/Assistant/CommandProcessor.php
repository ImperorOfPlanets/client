<?php

namespace App\Helpers\Assistant;

use App\Models\Assistant\MessagesModel;
use App\Models\Assistant\CommandsModel;
use App\Models\Socials\SocialsModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CommandProcessor
{
    protected array $permissions = [];

    public function __construct()
    {
        $this->loadPermissions();
    }

    /**
     * Загрузка разрешений из файла
     */
    protected function loadPermissions(): void
    {
        if (!Storage::disk('local')->exists('commands/permissions.json')) {
            Log::warning('Permissions file not found');
            return;
        }

        $json = Storage::disk('local')->get('commands/permissions.json');
        $this->permissions = json_decode($json, true) ?? [];
    }

    /**
     * Проверка доступа пользователя к команде
     */
    public function checkAccess(int $commandId, MessagesModel $message): bool
    {
        $commandPermissions = $this->getCommandPermissions($commandId);
        
        if (empty($commandPermissions)) {
            return false;
        }

        $socialId = $message->soc;
        $userId = $message->info['from'] ?? null;
        $isGroup = $message->info['is_group'] ?? false;

        // Проверяем доступность команды в социальной сети
        if (!isset($commandPermissions[$socialId]) || 
            !($commandPermissions[$socialId]['on'] ?? false)) {
            return false;
        }

        $socialPermissions = $commandPermissions[$socialId];

        // Проверяем доступ для пользователей/групп
        if ($isGroup) {
            return $this->checkGroupAccess($socialPermissions, $message);
        } else {
            return $this->checkUserAccess($socialPermissions, $userId);
        }
    }

    /**
     * Проверка доступа для пользователя
     */
    protected function checkUserAccess(array $permissions, ?string $userId): bool
    {
        $accessType = $permissions['users']['access'] ?? 'anybody';
        
        if ($accessType === 'all') {
            return true;
        }
        
        if ($accessType === 'define' && !empty($userId)) {
            return in_array($userId, $permissions['users']['define'] ?? []);
        }
        
        return false;
    }

    /**
     * Проверка доступа для группы
     */
    protected function checkGroupAccess(array $permissions, MessagesModel $message): bool
    {
        $accessType = $permissions['groups']['access'] ?? 'anybody';
        $chatId = $message->chat_id;
        
        if ($accessType === 'all') {
            return true;
        }
        
        if ($accessType === 'define') {
            return in_array($chatId, $permissions['groups']['define'] ?? []);
        }
        
        return false;
    }

    /**
     * Получение разрешений для команды
     */
    protected function getCommandPermissions(int $commandId): array
    {
        foreach ($this->permissions as $permission) {
            if ($permission['id'] === $commandId) {
                return $permission['access'] ?? [];
            }
        }
        
        return [];
    }

    /**
     * Выполнение команды
     */
    public function executeCommand(int $commandId, MessagesModel $message): void
    {
        if (!$this->checkAccess($commandId, $message)) {
            Log::warning('Access denied for command', [
                'command_id' => $commandId,
                'user_id' => $message->info['from'] ?? null,
                'chat_id' => $message->chat_id
            ]);
            
            $this->sendAccessDeniedResponse($message);
            return;
        }

        $command = CommandsModel::find($commandId);
        
        if (!$command) {
            Log::error('Command not found', ['command_id' => $commandId]);
            return;
        }

        $type = $command->propertyById(107)?->pivot->value;
        $value = $command->propertyById(108)?->pivot->value;

        if ($type === 'answer') {
            $this->handleAnswerCommand($value, $message);
        } elseif ($type === 'controller') {
            $this->handleControllerCommand($value, $message);
        }
    }

    /**
     * Обработка команды-ответа
     */
    protected function handleAnswerCommand(string $answer, MessagesModel $message): void
    {
        // Получаем экземпляр социальной сети через сервис-локатор или напрямую
        $social = $this->getSocialInstance($message->soc);
        
        if ($social) {
            $social->sendMessage(
                $message->chat_id,
                $answer,
                ['reply_for' => $message->info['message_id']]
            );
        }

        Log::info('Answer command executed', [
            'message_id' => $message->id,
            'command_type' => 'answer'
        ]);
    }

    /**
     * Обработка команды-контроллера
     */
    protected function handleControllerCommand(string $handler, MessagesModel $message): void
    {
        $handlerParts = explode('@', $handler);
        
        if (count($handlerParts) !== 2) {
            Log::error('Invalid handler format', ['handler' => $handler]);
            return;
        }

        [$class, $method] = $handlerParts;

        if (class_exists($class) && method_exists($class, $method)) {
            $handlerInstance = new $class();
            $handlerInstance->$method($message);
            
            Log::info('Controller command executed', [
                'message_id' => $message->id,
                'handler' => $handler
            ]);
        } else {
            Log::error('Handler class or method not found', [
                'class' => $class,
                'method' => $method
            ]);
        }
    }

    /**
     * Получение экземпляра социальной сети
     */
    protected function getSocialInstance(int $socialId)
    {
        // Используем ваш существующий механизм получения социальной сети
        // Например, через SocialsModel и propertyById(35)
        $social = SocialsModel::find($socialId);
        
        if (!$social) {
            Log::error('Social network not found', ['social_id' => $socialId]);
            return null;
        }

        $classPath = $social->propertyById(35)?->pivot->value;
        
        if (!$classPath || !class_exists($classPath)) {
            Log::error('Social network class not found or invalid', [
                'social_id' => $socialId,
                'class_path' => $classPath
            ]);
            return null;
        }

        return new $classPath();
    }

    /**
     * Отправка сообщения об отказе в доступе
     */
    protected function sendAccessDeniedResponse(MessagesModel $message): void
    {
        $social = $this->getSocialInstance($message->soc);
        
        if ($social) {
            $social->sendMessage(
                $message->chat_id,
                'У вас нет доступа к этой команде.',
                ['reply_for' => $message->info['message_id']]
            );
        }
    }
}