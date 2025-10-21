<?php
namespace App\Helpers\Socials;

use Illuminate\Http\Request;
use App\Models\Socials\SocialsModel;
use App\Helpers\Socials\Social;
use Log;
use App\Events\PrivateNewEvent;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Reverb\Events\MessageReceived;

class Web extends Social implements SocialInterface
{
	//Объект
	public $objectID=38;

	public $object=null;
	//Настройки сайта
	public $siteSettings=null;
	public $params = null;

	public function __construct()
	{
		//Настройки
		$webSettings = SocialsModel::find(38);
		//$this->telegram = new TG($telegramSettings->propertyById(30)->pivot->value,$telegramSettings->propertyById(39)->pivot->value);
	}

	//Опубликовать в группе
	public function publishPost($params)
	{
		$this->params = $params;
		$group_id = $this->object->propertyById(62)->pivot->value;
		if(isset($this->params['group_id']))
		{
			$group_id = $this->params['group_id'];
		}
		
		// Для Web интерфейса публикация поста может быть через событие
		$message_id = $this->generateRandomString();
		
		$postData = [
			'id' => $message_id,
			'text' => $this->params['text'] ?? '',
			'attachments' => $this->params['attachments'] ?? []
		];

		event(new PrivateNewEvent($group_id, [$postData], 'NewPost'));
		
		return [
			'chat_id' => $group_id,
			'message_id' => $message_id
		];
	}

	//Отправить ответ
	public function sendMessage($chat_id,$text,$params=null)
	{
		//Log::info($chat_id);
		$this->checkDefaultinitialized();
		$message_id = $this->generateRandomString();

		// Формируем сообщение
		$messages = [
			[
				'id' => $message_id,
				'text' => $text
			]
		];

		event(new PrivateNewEvent($chat_id, $messages,'Messages'));

		return [
			'chat_id'=>$chat_id,
			'text' => $text,
			'message_id' => $message_id,
			'event'=>'new_message'
		];
	}

	public function generateRandomString($length = 12) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}

	//Получить голосовое сообщение
	public function getVoiceMessage($params)
	{
		// Для Web интерфейса голосовые сообщения могут не поддерживаться
		return null;
	}

	//Функция проверки установки
	public function checkInstall()
	{
		// Web интерфейс всегда доступен
		return true;
	}

	//Проверить группа это или человек
	public function isGroup($message)
	{
		return false;
	}

	//Редактировать сообщение
	public function editMessage($chatId, $messageId, $text,$params)
	{
		$this->checkDefaultinitialized();

		// Формируем сообщение
		$data = [
			[
				'chat_id' => $chatId,
				'message_id' => $messageId,
				'text' => $text
			]
		];

		event(new PrivateNewEvent($chatId, $data,'EditMessage'));
		
		return [
			'chat_id'=>$chatId,
			'text' => $text,
			'message_id' => $messageId
		];
	}

	//Удалить сообщение
	public function deleteMessage($chatId, $messageId, $params = null)
	{
		$this->checkDefaultinitialized();
		
		$data = [
			'chat_id' => $chatId,
			'message_id' => $messageId,
		];
		
		event(new PrivateNewEvent($chatId, $data, 'DeleteMessage'));
		
		return [
			'success' => true,
			'chat_id' => $chatId,
			'message_id' => $messageId
		];
	}

	//Проверяет поддерживает удаление сообщений
	public function checkDeleteMessage()
	{
		return true;
	}

	//Проверяет поддерживает редактирование сообщений
	public function checkEditMessage()
	{
		return true;
	}

	//Проверяет иницизирована дефолтные данные
	public function checkDefaultinitialized()
	{
		if(is_null($this->object))
		{
			$this->object=SocialsModel::find($this->objectID);
		}
	}

	//Обратать результат запроса и вернеуть важные данные
	public function processResultSendMessage($result)
	{
		return [
			'message_id' => $result['message_id'] ?? null
		];
	}

	//Обработать запрос удаления сообщения
	public function processResultDeleteMessage($result)
	{
		return $result['success'] ?? false;
	}

	//Обработать запрос редактирования сообщения
	public function processResultEditMessage($result)
	{
		return isset($result['message_id']);
	}

	//Обработка обновления
	public function processUpdate(array $updateData): ?array
    {
        try {
            // Для Web интерфейса структура update может быть разной
            // Адаптируем под вашу структуру WebSocket сообщений
            
            $type = $updateData['type'] ?? 'message';
            $message = $updateData['data'] ?? $updateData;
            
            if ($type !== 'message') {
                return null;
            }

            $from = $message['user_id'] ?? $message['from'] ?? null;
            $chat = $message['chat_id'] ?? $message['channel'] ?? 'web';

            // Базовые данные
            $info = [
                'message_type' => $message['type'] ?? 'text',
                'message_id' => $message['id'] ?? $this->generateRandomString(),
                'from' => $from,
                'name' => $message['name'] ?? 'Web User',
                'username' => $message['username'] ?? null,
                'chat_type' => 'private', // Web обычно приватные чаты
                'date' => $message['timestamp'] ?? time(),
                'reply_to' => $message['reply_to'] ?? null
            ];

            return [
                'soc' => 38, // ID Web интерфейса
                'chat_id' => $chat,
                'text' => $message['text'] ?? '',
                'info' => $info,
                'attachments' => $message['attachments'] ?? []
            ];

        } catch (\Throwable $e) {
            Log::error('Web processUpdate error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

	// === НОВЫЕ МЕТОДЫ ДЛЯ СООТВЕТСТВИЯ ИНТЕРФЕЙСУ ===

	// Проверяет, может ли соцсеть получить длительность голосового сообщения без скачивания
	public function checkGetVoiceMessageDuration(array $params): bool
	{
		// Web интерфейс обычно не поддерживает голосовые сообщения
		return false;
	}
	
	// Проверяет, может ли соцсеть получить размер голосового сообщения без скачивания
	public function checkGetVoiceMessageFileSize(array $params): bool
	{
		// Web интерфейс обычно не поддерживает голосовые сообщения
		return false;
	}
	
	// Получить длительность голосового сообщения в секундах (если доступна)
	public function getVoiceMessageDuration(array $params): ?int
	{
		// Web интерфейс обычно не поддерживает голосовые сообщения
		return null;
	}
	
	// Получить размер голосового сообщения в байтах (если доступен)
	public function getVoiceMessageFileSize(array $params): ?int
	{
		// Web интерфейс обычно не поддерживает голосовые сообщения
		return null;
	}

	// Проверяем можем ли мы получить дату создания сообщения
	public function checkMessageCreatedDate(array $messageInfo): bool
	{
		return isset($messageInfo['date']) && !empty($messageInfo['date']);
	}

	// Проверяем можем ли мы получить дату обновления или изменения
	public function checkMessageUpdatedDate(array $messageInfo): bool
	{
		// В Web интерфейсе обычно нет отдельного поля обновления
		return false;
	}

	// === ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ===

	//Определяет тип сообщения
	private function detectMessageType(object $message): string
	{
		$typeMap = [
			'text' => 'text',
			'photo' => 'image',
			'video' => 'video',
			'audio' => 'audio',
			'voice' => 'audio',
			'document' => 'file',
			'sticker' => 'sticker',
			'location' => 'location'
		];

		foreach ($typeMap as $field => $type) {
			if (isset($message->{$field})) {
				return $type;
			}
		}
		return 'unknown';
	}

	private function extractContent(object $message, string $type): array
    {
        $result = ['text' => '', 'attachments' => []];

        // Текст/подпись
        $result['text'] = $message->text ?? $message->caption ?? '';

        // Обработка вложений
        switch ($type) {
            case 'image':
                $result['attachments'] = $this->processPhotoAttachments($message->photo);
                break;
                
            case 'video':
                $result['attachments'] = [$this->processVideoAttachment($message->video)];
                break;

            case 'audio':
                $result['attachments'] = [$this->processAudioAttachment($message->audio)];
                break;

            case 'file':
                $result['attachments'] = [$this->processDocumentAttachment($message->document)];
                break;
        }

        return $result;
    }

	// Обработка фото вложений
	private function processPhotoAttachments($photos): array
	{
		if (!is_array($photos)) return [];
		
		return array_map(function($photo) {
			return [
				'type' => 'image',
				'file_id' => $photo->file_id ?? null,
				'file_unique_id' => $photo->file_unique_id ?? null
			];
		}, $photos);
	}

	// Обработка видео вложения
	private function processVideoAttachment($video): array
	{
		return [
			'type' => 'video',
			'file_id' => $video->file_id ?? null,
			'file_unique_id' => $video->file_unique_id ?? null
		];
	}

	// Обработка аудио вложения
	private function processAudioAttachment($audio): array
	{
		return [
			'type' => 'audio',
			'file_id' => $audio->file_id ?? null,
			'file_unique_id' => $audio->file_unique_id ?? null
		];
	}

	// Обработка документа вложения
	private function processDocumentAttachment($document): array
	{
		return [
			'type' => 'file',
			'file_id' => $document->file_id ?? null,
			'file_unique_id' => $document->file_unique_id ?? null,
			'file_name' => $document->file_name ?? null
		];
	}
}