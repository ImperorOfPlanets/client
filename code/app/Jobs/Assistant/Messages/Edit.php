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

class Edit implements ShouldQueue
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
            $this->logator->setText("Редактирую сообщение");
            $this->logator->write();

            // Находим сообщение для редактирования
            $message = MessagesModel::find($this->params['message_id']);
            
            if (!$message) {
                throw new \Exception("Сообщение с ID {$this->params['message_id']} не найдено");
            }

            // Обновляем текст сообщения в БД
            $message->text = $this->params['text'] ?? $message->text;
            
            // Обновляем информацию о редактировании
            $info = $message->info ?? [];
            $info['edited'] = true;
            $info['edit_attempted_at'] = now()->toDateTimeString();
            $info['edit_params'] = $this->params;
            $message->info = $info;
            $message->status = 0; // Статус "в процессе редактирования"
            $message->save();

            // Получаем объект социальной сети
            $social = SocialsModel::find($message->soc);

            // Получаем путь до класса социальной сети
            $p35 = $social->propertyById(35)->pivot->value ?? null;
            if (!is_null($p35)) {
                $socialInstance = new $p35;
            } else {
                throw new \Exception('Ошибка инициализации социальной сети');
            }

            // Проверяем поддержку редактирования сообщений
            if (!$socialInstance->checkEditMessage()) {
                throw new \Exception('Социальная сеть не поддерживает редактирование сообщений');
            }

            // Подготавливаем параметры для редактирования
            $editParams = $this->prepareEditParameters();

            // Получаем ID сообщения в социальной сети
            $socialMessageId = $this->getSocialMessageId($message);

            // Редактируем сообщение через социальную сеть
            $result = $socialInstance->editMessage(
                $message->chat_id,
                $socialMessageId,
                $message->text,
                $editParams
            );

            // Обрабатываем результат редактирования
            $this->processEditResult($message, $result, $socialInstance);

            $this->logator->setText("Сообщение успешно отредактировано");
            $this->logator->write();

        } catch (\Throwable $e) {
            $this->logator->setType('error');
            $this->logator->setText("Ошибка редактирования сообщения: " . $e->getMessage());
            $this->logator->write();
            
            // Обновляем статус сообщения в случае ошибки
            if (isset($message)) {
                $info = $message->info ?? [];
                $info['edit_error'] = $e->getMessage();
                $info['edit_status'] = 'failed';
                $message->info = $info;
                $message->status = 2; // Статус ошибки
                $message->save();
            }
            
            throw $e;
        }
    }

    /**
     * Подготавливает параметры для метода editMessage
     */
    private function prepareEditParameters(): array
    {
        $editParams = [];

        // Добавляем параметр reply_to если нужно сохранить reply
        if (isset($this->params['reply_to'])) {
            $editParams['reply_to'] = $this->params['reply_to'];
        }

        // Добавляем дополнительные параметры, если есть
        if (isset($this->params['additional_params'])) {
            $editParams = array_merge($editParams, $this->params['additional_params']);
        }

        return $editParams;
    }

    /**
     * Получает ID сообщения в социальной сети из info
     */
    private function getSocialMessageId(MessagesModel $message): string
    {
        $info = $message->info ?? [];
        
        // Пытаемся получить message_id из info (сохраненный при отправке)
        if (isset($info['message_id'])) {
            return $info['message_id'];
        }
        
        // Для старых сообщений может быть другой путь
        if (isset($info['send_raw_result'])) {
            $processedResult = $message->soc->processResultSendMessage($info['send_raw_result']);
            if ($processedResult && isset($processedResult['message_id'])) {
                return $processedResult['message_id'];
            }
        }
        
        throw new \Exception('Не удалось получить ID сообщения в социальной сети');
    }

    /**
     * Обрабатывает результат редактирования
     */
    private function processEditResult(MessagesModel $message, $result, $socialInstance): void
    {
        $info = $message->info ?? [];
        
        // Сохраняем сырой результат редактирования
        $info['edit_raw_result'] = $result;
        $info['edit_processed_at'] = now()->toDateTimeString();

        // Обрабатываем результат через метод социальной сети
        $processedResult = $socialInstance->processResultEditMessage($result);
        
        if ($processedResult) {
            $info['edit_status'] = 'success';
            $info['last_edited_at'] = now()->toDateTimeString();
            $message->status = 1; // Статус "успешно отредактировано"
        } else {
            $info['edit_status'] = 'failed';
            $info['edit_error'] = 'Ошибка обработки результата редактирования';
            $message->status = 2; // Статус ошибки
        }

        $message->info = $info;
        $message->save();
    }
}