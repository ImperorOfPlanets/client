<?php
namespace App\Helpers\Socials;

use App\Jobs\recognizeVoice;

use App\Models\Assistant\MessagesModel;
use App\Models\Socials\SocialsModel;
use App\Helpers\Socials\Social;

use Illuminate\Support\Facades\Http;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram as TG;

use Illuminate\Support\Facades\Log;

class Telegram extends Social implements SocialInterface
{
	//Объект телеграмм
	public $objectID=12;
	public $object=null;
	//Настройки сайта
	public $siteSettings=null;
	public $telegram=null;
	public $params = null;
	public $result = null;

	public $ssh = null;
	public function __construct()
	{
		//Настройки
		$telegramSettings = SocialsModel::find(12);
		$this->telegram = new TG($telegramSettings->propertyById(30)->pivot->value,$telegramSettings->propertyById(39)->pivot->value);
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
		if(isset($this->params['attachments']))
		{
			if(isset($this->params['text']))
			{
				$this->result = Request::sendPhoto([
					'chat_id' => $group_id,
					'photo' => Request::encodeFile($this->params['attachments'][0]['path']),
					'caption'=> $this->params['text']
				]);
			}
			else
			{
				$this->result = Request::sendPhoto([
					'chat_id' => $group_id,
					'photo' => Request::encodeFile($this->params['attachments'][0]['path'])
				]);
			}
		}
		else
		{
			$this->result = Request::sendMessage([
				'chat_id' => $group_id,
				'text'    => $this->params['text'],
			]);
		}
		return $this->result;
	}

	//Отправить ответ
	public function sendMessage($chat_id,$text,$params=null)
	{
		Log::info('Telegram sendMessage:', [
            'chat_id' => $chat_id,
			'text'=> $text,
			'params'=> $params
        ]);

		$this->checkDefaultinitialized();

		$arrayForSend = [
			'chat_id' => $chat_id,
			'text'    => $text
		];

		Log::info('Telegram sendMessage: CHECK REPLY FOR ADD_PARAM FOR REQUEST', [
            'chat_id' => $chat_id,
			'text'=> $text,
			'params'=> $params
        ]);

		if(isset($params['reply_for']))
		{
			Log::info('Telegram sendMessage: CHECK REPLY ADD_PARAM');
			$arrayForSend['reply_to_message_id']=$params['reply_for'];
		}

		if(isset($params['thread_id']))
		{
			Log::info('Telegram sendMessage: thread_id '. $params['thread_id']);
			$arrayForSend['message_thread_id']=$params['thread_id'];
		}

		Log::info('Telegram Request::sendMessage:', [
            'arrayForSend' => $arrayForSend
        ]);

		$result = Request::sendMessage($arrayForSend);

		Log::info('Telegram Request::sendMessage->result', [
            'result' => $result,
			'backtrace' => debug_backtrace()[0]
        ]);

		return $result;
	}

	//Получить голосовое сообщение
	public function getVoiceMessage($params)
	{

		$this->params = $params;
		$this->result = Request::getFile([
			'file_id' => $this->params['file_id']
		]);
		$this->telegram->setDownloadPath(storage_path('app'));
		if ($this->result->isOk() && Request::downloadFile($this->result->getResult()))
		{
			rename(storage_path('app') . '/' . $this->result->getResult()->getFilePath(),storage_path('app/voice/'.$this->params['file_unique_id']));
			return true;
		}
		else
		{
			return false;
		}
	}

	//Функция проверки установки
	public function checkInstall()
	{
		try
		{
			$telegram = new Request;
			return true;
		}
		catch(\Throwable $ex)
		{
			return false;
		}
	}

	//Проверить группа это или человек
	public function isGroup($messagesModel)
	{
		// Если у нас есть объект сообщения с информацией о чате
		if (isset($messagesModel->info['chat_type'])) {
			$chatType = $messagesModel->info['chat_type'];
			return in_array($chatType, ['group', 'supergroup', 'channel']);
		}
		
		// Если info недоступен, проверяем chat_id
		$chatId = (string)$messagesModel->chat_id;
		
		// Группы и супергруппы начинаются с минуса, каналы тоже
		// Но лучше проверять по типу чата из информации сообщения
		if ($chatId[0] === "-") {
			return true;
		}
		
		// Альтернативная проверка - если chat_id числовой и больше определенного значения
		// ID пользователей обычно меньше 1000000000, группы больше
		if (is_numeric($chatId) && (int)$chatId < 0) {
			return true;
		}
		
		return false;
	}

	//Редактировать сообщение
	public function editMessage($chatId, $messageId, $text,$params)
	{
		$this->checkDefaultinitialized();
		try {
			$data = [
				'chat_id' => $chatId,
				'message_id' => $messageId, // Decrement by 1 as Telegram uses sequential IDs
				'text' => $text,
				'reply_to_message_id' => $params['reply_to'] ?? null
			];
			$result = Request::editMessageText($data);
			return $result;
		} catch (\Exception $e) {
			\Log::error("Error editing message: " . $e->getMessage());
			return false;
		}
		//$this->setUpdate($data);
	}

	//Удалить сообщение
	public function deleteMessage($chatId, $messageId, $params = null)
	{
		$this->checkDefaultinitialized();
		try {
			$data = [
				'chat_id' => $chatId,
				'message_id' => $messageId,
			];
			$result = Request::deleteMessage($data);
			return $result;
		} catch (\Exception $e) {
			\Log::error("Error delete message: " . $e->getMessage());
			return false;
		}
	}

	//Проверяет поддерживает удаление сообшений
	public function checkDeleteMessage()
	{
		return true;
	}

	//Проверяет поддерживает редактирование сообшений
	public function checkEditMessage()
	{
		return true;
	}

	//Проверяет иницизирована дефолтные данные
	public function checkDefaultinitialized()
	{
		Log::info('Telegram checkDefaultinitialized', [
            'backtrace' => debug_backtrace()[0]
        ]);
		if(is_null($this->object))
		{
			$this->object=SocialsModel::find($this->objectID);
		}

		if(is_null($this->telegram))
		{
			$this->telegram = new TG($this->object->propertyById(30)->pivot->value,$this->object->propertyById(39)->pivot->value);
		}
	}

	//Обратать результат отправки сообщений
	public function processResultSendMessage($result)
	{
		if ($result->isOk())
		{
			return [
				'message_id' => $result->getResult()->message_id
			];
		}
		else
		{
			\Log::error("Error processResultSendMessage: " .$result->getDescription());
			return false;
		}
	}

	//Обработать запрос удаления сообщения
	public function processResultDeleteMessage($result)
	{
		if ($result->isOk())
		{
			return true;
		}
		return false;
	}

	//Обработать запрос удаления сообщения
	public function processResultEditMessage($result)
	{
		if ($result->isOk())
		{
			return true;
		}
		return false;
	}

	//Обработка обновления
	public function processUpdate(array $updateData): ?array
    {
        try {
            $update = json_decode(json_encode($updateData), false);
            
            if (!isset($update->message)) {
                return null;
            }

            $message = $update->message;
            $from = $message->from;
            $chat = $message->chat;

            // Определение типа сообщения
            $messageType = $this->detectMessageType($message);
            
            // Базовые данные
            $info = [
                'message_type' => $messageType,
                'message_id' => $message->message_id,
                'from' => $from->id,
                'name' => $from->first_name ?? '',
                'username' => $from->username ?? null,
                'chat_type' => $chat->type,
                'date' => $message->date,
                'reply_to' => $message->reply_to_message->message_id ?? null,
                'thread_id' => $message->message_thread_id ?? null
            ];

			// если голосовое — сразу добавляем нужное
			if ($messageType === 'audio' && isset($message->voice)) {
				$info['file_id']        = $message->voice->file_id ?? null;
				$info['file_unique_id'] = $message->voice->file_unique_id ?? null;
				$info['duration']       = $message->voice->duration ?? null;
				$info['mime_type']      = $message->voice->mime_type ?? null;
				$info['file_size']      = $message->voice->file_size ?? null;
			}

            // Текст и остальные вложения
        	$text = $message->text ?? '';
			$attachments = [];

			return [
				'soc'         => 12,
				'chat_id'     => $chat->id,
				'text'        => $text,
				'info'        => $info,
				'attachments' => $attachments,
			];

        } catch (\Throwable $e) {
            Log::error('Telegram processUpdate error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

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

    private function processPhotoAttachments(array $photos): array
    {
        return array_map(function($photo) {
            return [
                'type' => 'image',
                'file_id' => $photo->file_id,
                'file_unique_id' => $photo->file_unique_id,
                'file_size' => $photo->file_size ?? null
            ];
        }, $photos);
    }

	public function checkGetVoiceMessageDuration(array $params): bool
	{
		// Ищем duration как в корне, так и в voice
		return isset($params['duration']) || isset($params['voice']['duration']);
	}
	
	public function checkGetVoiceMessageFileSize(array $params): bool
	{
		return isset($params['file_size']) || isset($params['voice']['file_size']);
	}
	
	public function getVoiceMessageDuration(array $params): ?int
	{
		return $params['duration'] ?? $params['voice']['duration'] ?? null;
	}
	
	public function getVoiceMessageFileSize(array $params): ?int
	{
		return $params['file_size'] ?? $params['voice']['file_size'] ?? null;
	}

	public function checkMessageCreatedDate(array $messageInfo): bool
	{
		return isset($messageInfo['date']) && !empty($messageInfo['date']);
	}

	public function checkMessageUpdatedDate(array $messageInfo): bool
	{
		// В Telegram, например, нет обновленной даты
		return false;
	}

	//Получить дату создания сообщения
	public function getMessageCreatedDate(array $messageInfo): ?string
	{
		if (isset($messageInfo['date'])) {
			return \Carbon\Carbon::createFromTimestamp($messageInfo['date'])->toDateTimeString();
		}
		return null;
	}

	//Получить дату создания изменения
	public function getMessageUpdatedDate(array $messageInfo): ?string
	{
		// В Telegram нет явного поля обновления, можно вернуть null
		return null;
	}
}