<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MesaActualizada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly int $empresaId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('mesas.' . $this->empresaId);
    }

    public function broadcastAs(): string
    {
        return 'MesaActualizada';
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
