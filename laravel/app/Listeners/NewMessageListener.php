<?php

namespace App\Listeners;

use App\Models\Assistant\MessagesModel;
use App\Models\Socials\UpdatesModel;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use Laravel\Reverb\Events\MessageReceived;

class NewMessageListener
{
    //public $queue = 'core';
    /**
     * Handle the event.
     */
    public function handle(MessageReceived $eventData): void
    {
        // Получаем сообщение из payload
        $payload = json_decode($eventData->message);
        
        //Событие
        $event = $payload->event ?? null;
        //echo "event $event\n";

        //Данные
        $data = $payload->data ?? null;
        //var_dump($data);echo "\n";

        $channel = $payload->channel ?? null;

        //Подключение
        $connection_id = $eventData->connection->id();
        //dd($connection_id);

        $session_id = null;
        if(!is_null($channel))
        {
            // Разделяем строку по точке
             $parts = explode('.', $channel);

            // Проверяем, что есть хотя бы две части, и получаем channel_id (вторая часть)
            if(count($parts) > 1){
                $session_id = strrev($parts[1]);
                //echo "session_id - $session_id\n";
            }
        }

        if(is_null($session_id) || is_null($event))
        {
            return;
        }

        if($event == 'SendMessage')
        {
            // Создаем обновление модели
            $update = new UpdatesModel();
            $update->soc = 38;
            $update->status = 1;

            // Создаем сообщение модели
            $message = new MessagesModel();
            $message->soc = 38;
            $message->chat_id = strrev($session_id);
            $message->text = $data->text;

            // Определяем тип сообщения
            if ($data->type === 'audio')
            {
                // Обработка аудио файла
                /*$fileName = time() . '.' . $messageData['info']['filename_extension'];
                $path = storage_path('app/voice/' . $fileName);
                Storage::put($path, $messageData['info']['file']);

                $updateJSON['type'] = 'audio';
                $updateJSON['info'] = [
                    'filename' => $fileName,
                    'type' => 'audio'
                ];*/
            }
            elseif($data->type === 'text')
            {
                $updateJSON['message_type'] = 'text';
                $updateJSON['text'] = $data->text;
                $updateJSON['message_id'] = $data->temp_id;
            }
            else
            {
                throw new \Exception('Неправильный тип сообщения');
            }

            // Сохраняем обновление
            $update->json = $updateJSON;
            $update->save();
            $updateJSON['update_id'] = $update->id;
            $updateJSON['published'] = true;

            // Сохраняем сообщение
            $message->info = $updateJSON;
            $message->status = 0;
            $message->save();

            $eventData->connection->send(json_encode([
                'channel' => $channel,
                'event' => 'MessageReceived',
                'data' => [
                    'message_id'=>$message->id,
                    'update_id'=>$update->id,
                    'temp_id'=>$data->temp_id
                ]
            ]));
        }
        elseif($event == 'getMessages')
        {
            $messages = collect();  // Коллекция для хранения найденных сообщений
            $lastMessageId = null;  // Для отслеживания последнего обработанного сообщения
            $limit = 10;  // Количество сообщений, которые нужно найти
            $counter = 0;  // Счетчик сообщений с ключом 'published'

            // Цикл до тех пор, пока не получим 10 сообщений с ключом 'published'
            while ($counter < $limit)
            {
                // Получаем следующее сообщение
                $query = MessagesModel::where('soc',38)
                    ->where('chat_id', strrev($session_id))  // strrev для инвертированного chat_id
                    ->orderBy('id', 'desc')  // Сортируем по дате (создания) в убывающем порядке
                    ->take(10);  // Ограничиваем 10 сообщениями

                // Если уже были получены сообщения, ограничиваем запрос на ID
                if ($lastMessageId) {
                    $query->where('id', '<', $lastMessageId);  // Только те, у которых id меньше, чем у последнего обработанного
                }

                $newMessages = $query->get();

                echo "Получил ".$newMessages->count(). "сообщений\n";
                // Если сообщений не осталось — выходим из цикла
                if ($newMessages->isEmpty())
                {
                    break;
                }

                // Перебираем найденные сообщения
                foreach ($newMessages as $message) {
                    echo "$message->id\n";
                    //dd($message->info,isset($message->info['published']),$message->id);
                    // Проверяем, есть ли в info ключ 'published'
                    if (isset($message->info['published'])) {
                        // Если ключ есть, увеличиваем счетчик и добавляем сообщение в коллекцию
                        $messages->push([
                            'id' => $message->id,
                            'text' => $message->text,
                            'status' => $message->status,
                            'isMyMessage' => isset($message->info['isBot']) ? false : true, // Если isBot существует, то false, иначе true
                        ]);
                        $counter++;
                    }
            
                    // Сохраняем ID последнего обработанного сообщения для ограничения следующего запроса
                    $lastMessageId = $message->id;
            
                    // Если уже нашли 10 сообщений с ключом 'published', выходим из цикла
                    if ($counter >= $limit) {
                        break;
                    }
                }
            }
            //dd($messages);
            // Если удалось найти хотя бы одно сообщение
            if($messages->isNotEmpty())
            {

                // Отправляем ответ в сокет
                $eventData->connection->send(json_encode([
                    'channel' => $channel,
                    'event' => 'Messages', 
                    'data' => $messages
                ]));
            }
            else
            {
                // Если не было найдено сообщений, можно отправить соответствующий ответ
                $eventData->connection->send(json_encode([
                    'channel' => $channel,
                    'event' => 'Messages', 
                    'data' => []
                ]));
            }
        }
        return;
    }
}