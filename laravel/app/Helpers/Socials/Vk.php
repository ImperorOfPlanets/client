<?php
namespace App\Helpers\Socials;

use Illuminate\Http\Request;
use App\Models\Socials\SocialsModel;
use \VK\Client\VKApiClient;
use Illuminate\Support\Facades\Log;

class Vk extends Social implements SocialInterface
{
	//Объект вк
	public $objectID=13;

	//Объект вк
	public $object=null;

	//Настройки сайта
	public $siteSettings=null;
	public $vkclient = null;
	public $params = null;

	//Отправить сообщение
	public function sendMessage($chat_id,$text,$params=null)
	{
		if(is_null($this->object))
		{
			$this->object=SocialsModel::find($this->objectID);
		}

		if(is_null($this->vkclient))
		{
			$this->vkclient = new VKApiClient('5.131');
		}

		$this->params['message']=$text;
		$this->params['peer_ids']=$chat_id;
		$this->params['group_id']=$this->object->propertyById(40)->pivot->value;
		$this->params['random_id']=(int)$this->object->propertyById(61)->pivot->value+1;
		$this->object->propertys()->updateExistingPivot(61,['value'=>$this->params['random_id']]);
		
		try
		{ 
			$response = $this->vkclient->messages()->send($this->object->propertyById(45)->pivot->value, $this->params);
			return $response;
		}
		catch(\VKApiException $e)
		{
			Log::error('VK sendMessage error', ['error' => $e->getMessage()]);
			return false;
		}
	}

	//Функция проверки установки
	public function checkInstall()
	{
		try
		{
			$vk = new VKApiClient;
			return true;
		}
		catch(\Throwable $ex)
		{
			return false;
		}
	}

	//Функция проверки установки
	public function isGroup($info)
	{
		//Для ВК не доступно добавить бота в чат
		return false;
	}

	//Получить голосовое сообщение
	public function getVoiceMessage($params)
	{
		// TODO: Implement getVoiceMessage() method
		return null;
	}

	//Опубликовать пост
	public function publishPost($params)
	{
		// TODO: Implement publishPost() method
		return null;
	}
	
	public function checkEditMessage()
	{
		return true;
	}

    //Проверяет поддерживает ли удаление сообщений
    public function checkDeleteMessage()
	{
		return true;
	}

	//Редактировать сообщение
	public function editMessage($chatId, $messageId, $text,$params)
	{
		// TODO: Implement editMessage() method
		return null;
	}

	//Удалить сообщение
	public function deleteMessage($chatId, $messageId, $params)
	{
		// TODO: Implement deleteMessage() method
		return null;
	}

	//Проверяет выполненые стартовая инициализация, если нет то в ней же и описать
	public function checkDefaultinitialized()
	{
		if(is_null($this->object))
		{
			$this->object=SocialsModel::find($this->objectID);
		}

		if(is_null($this->vkclient))
		{
			$this->vkclient = new VKApiClient('5.131');
		}
	}

	//Обратать результат запроса и вернеуть важные данные
	public function processResultSendMessage($result)
	{
		// Для VK API структура ответа отличается от Telegram
		if (isset($result['response'])) {
			return [
				'message_id' => $result['response'][0] ?? null
			];
		}
		
		Log::error("Error processResultSendMessage VK: " . json_encode($result));
		return false;
	}

	//Обработать запрос удаления сообщения
	public function processResultDeleteMessage($result)
	{
		// Для VK API
		return isset($result['response']) && $result['response'] == 1;
	}

	//Обработать запрос удаления сообщения
	public function processResultEditMessage($result)
	{
		// Для VK API
		return isset($result['response']) && $result['response'] == 1;
	}

	public function processUpdate(array $updateData): ?array
	{
		try {
			// VK обычно отправляет updates в другом формате
			// Нужно адаптировать под структуру VK Callback API
			
			$type = $updateData['type'] ?? null;
			
			if ($type !== 'message_new') {
				return null;
			}
			
			$object = $updateData['object']['message'] ?? $updateData['object'] ?? [];
			$from = $object['from_id'] ?? null;
			$peer = $object['peer_id'] ?? null;
			
			// Определение типа сообщения
			$messageType = $this->detectVkMessageType($object);
			
			$info = [
				'message_type' => $messageType,
				'message_id' => $object['id'] ?? null,
				'from' => $from,
				'chat_type' => ($peer > 2000000000) ? 'chat' : 'private',
				'date' => $object['date'] ?? null,
				'reply_to' => $object['reply_message']['id'] ?? null
			];
			
			// Обработка голосового сообщения
			if ($messageType === 'audio' && isset($object['attachments'])) {
				foreach ($object['attachments'] as $attachment) {
					if ($attachment['type'] === 'audio_message') {
						$audio = $attachment['audio_message'];
						$info['file_id'] = $audio['id'] ?? null;
						$info['duration'] = $audio['duration'] ?? null;
						$info['file_size'] = $audio['size'] ?? null;
						$info['link_mp3'] = $audio['link_mp3'] ?? null;
						$info['link_ogg'] = $audio['link_ogg'] ?? null;
						break;
					}
				}
			}
			
			$text = $object['text'] ?? '';
			$attachments = [];
			
			return [
				'soc'         => 13, // ID VK
				'chat_id'     => $peer,
				'text'        => $text,
				'info'        => $info,
				'attachments' => $attachments,
			];

		} catch (\Throwable $e) {
			Log::error('VK processUpdate error', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);
			return null;
		}
	}
	
	// Определяет тип сообщения VK
	private function detectVkMessageType(array $message): string
	{
		if (empty($message['attachments'])) {
			return !empty($message['text']) ? 'text' : 'unknown';
		}
		
		$types = [];
		foreach ($message['attachments'] as $attachment) {
			$types[] = $attachment['type'];
		}
		
		$typeMap = [
			'photo' => 'image',
			'video' => 'video',
			'audio' => 'audio',
			'audio_message' => 'audio',
			'doc' => 'file',
			'sticker' => 'sticker'
		];
		
		foreach ($types as $type) {
			if (isset($typeMap[$type])) {
				return $typeMap[$type];
			}
		}
		
		return 'unknown';
	}

	// Проверяет, может ли соцсеть получить длительность голосового сообщения без скачивания
	public function checkGetVoiceMessageDuration(array $params): bool
	{
		return isset($params['duration']) || isset($params['audio_message']['duration']);
	}
	
	// Проверяет, может ли соцсеть получить размер голосового сообщения без скачивания
	public function checkGetVoiceMessageFileSize(array $params): bool
	{
		return isset($params['file_size']) || isset($params['audio_message']['size']);
	}
	
	// Получить длительность голосового сообщения в секундах (если доступна)
	public function getVoiceMessageDuration(array $params): ?int
	{
		return $params['duration'] ?? $params['audio_message']['duration'] ?? null;
	}
	
	// Получить размер голосового сообщения в байтах (если доступен)
	public function getVoiceMessageFileSize(array $params): ?int
	{
		return $params['file_size'] ?? $params['audio_message']['size'] ?? null;
	}

	public function checkMessageCreatedDate(array $messageInfo): bool
	{
		return isset($messageInfo['date']) && !empty($messageInfo['date']);
	}

	public function checkMessageUpdatedDate(array $messageInfo): bool
	{
		// В VK тоже нет явного поля обновления
		return false;
	}
}