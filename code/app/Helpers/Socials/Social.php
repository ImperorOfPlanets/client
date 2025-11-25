<?php
namespace App\Helpers\Socials;

use App\Models\Socials\UpdatesModel;
use App\Models\Assistant\MessagesModel;

class Social
{
	public $objectID;

    //Добавить обновление в таблицу updates
    public function setUpdate($updates)
    {
        //Подготавливаем событие для сохранения
        $update = new UpdatesModel;
        $update->soc= $this->objectID;
        $update->status= 1;
        $update->json = $updates;
        $update->save();
        if(isset($updates['event']))
        {
            if($updates['event']== 'new_message')
            {
                //Добавляем Ид события
                $updates['update_id'] = $update->id;
                $res = $this->setNewMessage($updates);
                $res['update_id'] = $update->id;
                return $res;
            }
            if($updates['event']== 'edit_message')
            {
                //Добавляем Ид события
                $updates['update_id'] = $update->id;
                $res = $this->setNewMessage($updates);
                $res['update_id'] = $update->id;
                return $res;
            }
            if($updates['event']== 'SendToText')
            {
                //Добавляем Ид события
                $updates['update_id'] = $update->id;
                $res = $this->setNewMessage($updates);
                $res['update_id'] = $update->id;
                return $res;
            }
        }
    }

    //Добавить сообщение в общую таблицу
    public function setNewMessage($updates)
    {
        $message = new MessagesModel;
        $message->soc = $this->objectID;
        $message->chat_id = $updates['chat_id'];
        $message->text=$updates['text'];
        $message->status=1;
        $info = [
            'message_id'=>$updates['message_id'],
            'update_id'=>$updates['update_id'],
        ];
        $message->info = $info;
        $message->save();
        return ['message_id'=>$message->id];
    }

    /**
     * Сохраняет ответ бота в таблицу messages
     */
    public function saveResponseMessage(array $responseData): MessagesModel
    {
        $message = new MessagesModel;
        $message->soc = $this->objectID;
        $message->chat_id = $responseData['chat_id'];
        $message->text = $responseData['text'] ?? '';
        $message->status = 1; // Статус "обработано"
        
        $info = [
            'message_id' => $responseData['message_id'] ?? null,
            'is_bot_response' => true,
            'bot_response_to' => $responseData['original_message_id'] ?? null,
            'response_type' => $responseData['response_type'] ?? 'text',
            'processed_at' => now()->toISOString(),
            'from' => 'bot', // Идентификатор бота
            'name' => 'Assistant Bot',
            'chat_type' => $responseData['chat_type'] ?? 'private'
        ];

        // Добавляем дополнительные данные если есть
        if (isset($responseData['additional_info'])) {
            $info = array_merge($info, $responseData['additional_info']);
        }

        $message->info = $info;
        $message->save();

        return $message;
    }
}
