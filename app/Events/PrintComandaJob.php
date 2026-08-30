<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrintComandaJob implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('comanda.' . $this->data['api_token']);
    }

    public function broadcastAs(): string
    {
        return 'nueva-comanda';
    }
}
