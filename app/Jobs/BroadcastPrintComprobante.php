<?php

namespace App\Jobs;

use App\Events\PrintComprobanteJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BroadcastPrintComprobante implements ShouldQueue
{
    use Queueable;

    public function __construct(public array $data) {}

    public function handle(): void
    {
        event(new PrintComprobanteJob($this->data));
    }
}
