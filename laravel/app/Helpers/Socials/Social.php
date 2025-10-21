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
}
