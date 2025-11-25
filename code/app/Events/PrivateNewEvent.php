<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
//Используется для мгновенной передачи
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
//Добавляет в очередь
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PrivateNewEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $data;
    public $channel_id;
    public $eventName;

    /**
     * Создаем новый экземпляр события.
     */
    public function __construct($channel_id, $data,$eventName = 'new_event')
    {
        $this->data = $data; // Данные события
        $this->channel_id = $channel_id; // Идентификатор пользователя для приватного канала
        $this->eventName = $eventName;
    }

    /**
     * Получаем каналы, на которых событие должно быть транслировано.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Используем приватный канал с userId (или session_id) в названии канала
        return [new PrivateChannel('chat.'. $this->channel_id)];
    }

    public function broadcastWith()
    {
        return $this->data;
    }

    public function broadcastAs(): string
    {
        return $this->eventName;
    }
}