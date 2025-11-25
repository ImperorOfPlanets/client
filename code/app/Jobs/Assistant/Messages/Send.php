<?php

namespace App\Jobs\Assistant\Messages;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\Assistant\MessagesModel;
use App\Models\Socials\SocialsModel;
use App\Helpers\Logs\Logs as Logator;

class Send implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $params = null;
    public $soc = null;
    public $logator;

    public function __construct($params = null)
    {
        $this->params = $params;
    }

    public function handle()
    {
        try {
            $this->logator = new Logator;
            $this->logator->setType('success');
            $this->logator->setText("Отправляю сообщение");
            $this->logator->write();

            // Создаем запись сообщения в БД перед отправкой
            $message = $this->createMessage();

            // Получаем объект социальной сети
            $social = SocialsModel::find($message->soc);

            // Получаем путь до класса социальной сети
            $p35 = $social->propertyById(35)->pivot->value ?? null;
            if (!is_null($p35)) {
                $socialInstance = new $p35;
            } else {
                throw new \Exception('Ошибка инициализации социальной сети');
            }

            // Подготавливаем параметры для отправки
            $sendParams = $this->prepareSendParameters();

            // Отправляем сообщение через социальную сеть
            $result = $socialInstance->sendMessage(
                $message->chat_id,
                $message->text,
                $sendParams
            );

            // Сохраняем сырой результат в info
            $this->saveRawResult($message, $result);

            $this->logator->setText("Сообщение успешно отправлено");
            $this->logator->write();

        } catch (\Throwable $e) {
            $this->logator->setType('error');
            $this->logator->setText("Ошибка отправки сообщения: " . $e->getMessage());
            $this->logator->write();
            
            // Обновляем статус сообщения в случае ошибки
            if (isset($message)) {
                $info = $message->info ?? [];
                $info['send_error'] = $e->getMessage();
                $info['send_status'] = 'failed';
                $message->info = $info;
                $message->status = 2; // Статус ошибки
                $message->save();
            }
            
            throw $e;
        }
    }

    /**
     * Создает запись сообщения в БД перед отправкой
     */
    private function createMessage(): MessagesModel
    {
        $messageData = [
            'text' => $this->params['text'] ?? '',
            'soc' => $this->params['soc'] ?? null,
            'chat_id' => $this->params['chat_id'] ?? null,
            'status' => 0, // Статус "в процессе отправки"
            'info' => $this->prepareMessageInfo()
        ];

        // Если передан ID существующего сообщения (для ответа/редактирования)
        if (isset($this->params['message_id'])) {
            $message = MessagesModel::find($this->params['message_id']);
            if ($message) {
                $message->update($messageData);
                return $message;
            }
        }

        // Создаем новое сообщение
        return MessagesModel::create($messageData);
    }

    /**
     * Подготавливает информацию для поля info сообщения
     */
    private function prepareMessageInfo(): array
    {
        $info = [
            'message_type' => 'text',
            'is_bot_response' => true,
            'send_attempted_at' => now()->toDateTimeString(),
            'send_params' => $this->params
        ];

        // Добавляем информацию о reply, если есть
        if (isset($this->params['reply_for'])) {
            $info['reply_to'] = $this->params['reply_for'];
        }

        // Добавляем информацию о thread, если есть
        if (isset($this->params['thread_id'])) {
            $info['thread_id'] = $this->params['thread_id'];
        }

        // Добавляем информацию о исходном сообщении, если есть
        if (isset($this->params['original_message_id'])) {
            $info['bot_response_to'] = $this->params['original_message_id'];
        }

        return $info;
    }

    /**
     * Подготавливает параметры для метода sendMessage
     */
    private function prepareSendParameters(): array
    {
        $sendParams = [];

        if (isset($this->params['reply_for'])) {
            $sendParams['reply_for'] = $this->params['reply_for'];
        }

        if (isset($this->params['thread_id'])) {
            $sendParams['thread_id'] = $this->params['thread_id'];
        }

        if (isset($this->params['original_message_id'])) {
            $sendParams['original_message_id'] = $this->params['original_message_id'];
        }

        if (isset($this->params['chat_type'])) {
            $sendParams['chat_type'] = $this->params['chat_type'];
        }

        // Добавляем дополнительные параметры, если есть
        if (isset($this->params['additional_params'])) {
            $sendParams = array_merge($sendParams, $this->params['additional_params']);
        }

        return $sendParams;
    }

    /**
     * Сохраняет сырой результат в info
     */
    private function saveRawResult(MessagesModel $message, $result): void
    {
        $info = $message->info ?? [];
        
        // Просто сохраняем весь результат как JSON
        $info['send_raw_result'] = $result;
        $info['send_status'] = 'success';
        $info['send_processed_at'] = now()->toDateTimeString();

        $message->info = $info;
        $message->status = 1; // Статус "успешно отправлено"
        $message->save();
    }
}