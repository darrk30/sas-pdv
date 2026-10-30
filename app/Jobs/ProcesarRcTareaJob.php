<?php

namespace App\Jobs;

use App\Models\TareaProgramadaPdv;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcesarRcTareaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 900; // paso 1 (RCs pendientes) + paso 2 (nuevo RC + 90s espera + reintentos)

    public function __construct(private readonly int $tareaId) {}

    public function handle(): void
    {
        $tarea = TareaProgramadaPdv::find($this->tareaId);
        if (! $tarea) {
            return;
        }

        $tarea->ejecutar();
    }
}
