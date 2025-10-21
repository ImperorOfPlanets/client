<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PublicNewEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $data;

    protected $event; // Имя события

    /**
     * Create a new event instance.
     */
    public function __construct($event,$data)
    {
        $this->data = $data; // Передаем данные
        $this->event = $event; // Устанавливаем имя события (по умолчанию 'new.update')
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('public-new')];
    }

    public function broadcastAs()
    {
        return $this->event;
    }
}
