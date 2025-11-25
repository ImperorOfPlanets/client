<?php

namespace App\Observers;

use App\Models\Assistant\MessagesModel;
use App\Models\Socials\UpdatesModel;
use App\Models\Socials\SocialsModel;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

use App\Models\Ai\AiRequest;

use App\Jobs\Assistant\Messages\Delete;
use App\Jobs\Assistant\Messages\Send;

use App\Helpers\Ai\AiServiceLocator;
use App\Helpers\Assistant\FilterProcessor;

class MessageObserver
{
    private $initializedSocials = [];
    private $answerMessages = [];
    private $arrayAnswers = [];
    private $logator;
    private $messagesModel;
    private $info;
    private $isGroup = false;
    private $params;
    
    // Настройки отладки
    private $debugMode = true; // Включить/выключить отладочные сообщения
    private $debugMessageTemplate = "🔍 Обработка фильтра: {filter_name}\nСтатус: {status}";

    public function __construct()
    {
        \Log::info('MessageObserver instance created');
    }

    /* ---------------------------------------------- СОЗДАНИЕ СООБЩЕНИЯ ----------------------------------------- */

    public function created(MessagesModel $messagesModel): void
    {
        // Пропускаем отладочные/служебные сообщения
        if ($this->isDebugOrServiceMessage($messagesModel)) {
            return;
        }

        $this->initializeSocialClassAndVariables($messagesModel);

        Log::info('Observer triggered: created', ['id' => $messagesModel->id]);

        // Отправка уведомления о начале обработки
        if(!$this->isGroup){
            $this->sendProcessingNotification($messagesModel);
        }

        // Применение AI-фильтров
        // --- Здесь запускаем цепочку фильтров ---
        FilterProcessor::startFilterChain($messagesModel);
    }

    /*---------------------------------ОБРАБОТКА СООБЩЕНИЙ --------------------------------------------*/

    public function updated(MessagesModel $message): void
    {
        // Проверяем только если изменилось поле info
        if (!$message->isDirty('info')) {
            return;
        }
    
        $this->initializeSocialClassAndVariables($message);

        // Пропускаем обработку если сообщение уже обработано как команда
        if ($message->info['processed_as_command'] ?? false) {
            Log::info('Сообщение уже обработано как команда, пропускаем дальнейшую обработку', [
                'message_id' => $message->id
            ]);
            return;
        }

        // Проверяем завершение всех фильтров
        $allFiltersCompleted = FilterProcessor::areAllFiltersCompleted($message);
    
        if ($allFiltersCompleted) {
            $approved = FilterProcessor::analyzeFilterResults($message->info['filters'] ?? []);
            
            if ($approved) {
                $this->handleApprovedMessage($message);
            } else {
                $this->handleRejectedMessage($message);
            }
            
            // Помечаем сообщение как полностью обработанное
            $message->status = 1;
            $message->save();
            
            Log::info('Обработка сообщения завершена', [
                'message_id' => $message->id,
                'approved' => $approved
            ]);
        } else {
            Log::debug('Фильтрация еще не завершена', [
                'message_id' => $message->id,
                'completed_filters' => array_keys($message->info['filters'] ?? [])
            ]);
        }
    }

    /*---------------------------------ДОПОЛНИТЕЛЬНЫЕ ФУНКЦИИ --------------------------------------------*/

    // Инициализация нужных переменных
    private function initializeSocialClassAndVariables(MessagesModel $messagesModel): void
    {
        $this->messagesModel = $messagesModel;
        $this->info = $this->messagesModel->info;
        $this->getSocialNetwork();
        $this->isGroup = $this->getSocialNetwork()->isGroup($this->messagesModel);
    }

    // Инициализирует класс социальной сети
    private function getSocialNetwork()
    {
        if (!isset($this->initializedSocials[$this->messagesModel->soc])) {
            $social = SocialsModel::find($this->messagesModel->soc);
            $p35 = $social->propertyById(35)->pivot->value ?? null;
            if (!is_null($p35)) {
                $this->initializedSocials[$this->messagesModel->soc] = new $p35;
            } else {
                throw new \Exception('Ошибка инициализации социальной сети');
            }
        }
        return $this->initializedSocials[$this->messagesModel->soc];
    }

    // Отправка сообщения через JOB Send
    private function sendMessage(MessagesModel $message, string $text, array $params): void
    {
        try {
            // Подготавливаем параметры для JOB Send
            $sendParams = [
                'soc' => $message->soc,
                'chat_id' => $message->chat_id,
                'text' => $text,
                'reply_for' => $params['reply_for'] ?? null,
                'thread_id' => $params['thread_id'] ?? null,
                'original_message_id' => $message->id,
                'chat_type' => $message->info['chat_type'] ?? 'private'
            ];

            // Добавляем дополнительные параметры если есть
            if (isset($params['additional_params'])) {
                $sendParams['additional_params'] = $params['additional_params'];
            }

            // Отправляем через JOB
            Send::dispatch($sendParams);

            Log::info('Сообщение отправлено через JOB Send', [
                'message_id' => $message->id,
                'text' => $text,
                'params' => $sendParams
            ]);

        } catch (\Throwable $e) {
            Log::error('Ошибка отправки сообщения через JOB: ' . $e->getMessage());
        }
    }

    // Обработка голосового сообщения
    private function processMessage()
    {
        if (!$this->isGroup) {
            Bus::chain([
                new AiRequest(['message_id' => $this->messagesModel->id])
            ])->onQueue('default')->dispatch();
        }

        $this->messagesModel->status = 1;
        $this->messagesModel->save();
    }

    private function handleApprovedMessage(MessagesModel $message): void
    {
        Log::info('Сообщение одобрено всеми фильтрами', ['message_id' => $message->id]);
        
        // Здесь будет логика отправки ответа пользователю
        $responseText = "Ваше сообщение обработано успешно!";
        $this->sendMessage($message, $responseText, [
            'reply_for' => $message->info['message_id']
        ]);
    }

    private function handleRejectedMessage(MessagesModel $message): void
    {
        Log::info('Сообщение отклонено фильтрами', ['message_id' => $message->id]);
        
        $rejectionText = "Ваше сообщение не прошло проверку.";
        $this->sendMessage($message, $rejectionText, [
            'reply_for' => $message->info['message_id']
        ]);
    }

    private function isDebugOrServiceMessage(MessagesModel $message): bool
    {
        $info = $message->info;
        
        // Пропускаем сообщения, которые являются отладочными или служебными
        if (isset($info['is_debug']) && $info['is_debug']) {
            return true;
        }
        
        // Пропускаем сообщения без user_id (скорее всего служебные)
        if (empty($info['from'])) {
            return true;
        }
        
        // Пропускаем сообщения, которые являются ответами бота
        if (isset($info['is_bot_response']) && $info['is_bot_response']) {
            return true;
        }
        
        return false;
    }

    // Отправка уведомления об отправке на фильтрацию через JOB Send
    private function sendProcessingNotification(MessagesModel $message): void
    {
        // Если не группа проверка пока отключена
        if($this->isGroup && !$this->debugMode)
        {
            return;
        }
        
        try {
            $processingMessage = "Ваше сообщение получено и отправлено на обработку. Пожалуйста, подождите...";
            
            // Подготавливаем параметры для JOB Send
            $sendParams = [
                'soc' => $message->soc,
                'chat_id' => $message->chat_id,
                'text' => $processingMessage,
                'reply_for' => $this->info['message_id'],
                'thread_id' => $this->info['thread_id'] ?? null,
                'original_message_id' => $message->id,
                'chat_type' => $this->info['chat_type'] ?? 'private'
            ];

            // Отправляем через JOB Send
            Send::dispatch($sendParams);

            Log::info('Processing notification sent via JOB Send', [
                'user_id' => $this->info['from'] ?? null,
                'message_id' => $this->info['message_id'],
                'chat_id' => $message->chat_id
            ]);

        } catch (\Throwable $e) {
            Log::error("Failed to send processing notification via JOB: " . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    // Удаление сообщения через JOB Delete (если нужно)
    private function deleteMessage(MessagesModel $message): void
    {
        try {
            $params = [
                'message_id' => $message->id
            ];

            Delete::dispatch($params);

            Log::info('Сообщение отправлено на удаление через JOB Delete', [
                'message_id' => $message->id
            ]);

        } catch (\Throwable $e) {
            Log::error('Ошибка отправки сообщения на удаление через JOB: ' . $e->getMessage());
        }
    }
}