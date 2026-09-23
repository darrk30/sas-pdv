<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MesaActualizada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly int $empresaId) {}

    public function broadcastOn(): Channel
    {
        return new Channel('mesas.' . $this->empresaId);
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
