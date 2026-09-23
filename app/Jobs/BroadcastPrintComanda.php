<?php

namespace App\Jobs;

use App\Events\PrintComandaJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BroadcastPrintComanda implements ShouldQueue
{
    use Queueable;

    public function __construct(public array $data) {}

    public function handle(): void
    {
        event(new PrintComandaJob($this->data));
    }
}
