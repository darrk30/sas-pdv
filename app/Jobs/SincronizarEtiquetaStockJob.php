<?php

namespace App\Jobs;

use App\Services\EtiquetaStockService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SincronizarEtiquetaStockJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $productoId,
        public readonly int $empresaId,
    ) {}

    public function handle(EtiquetaStockService $service): void
    {
        $service->sincronizar($this->productoId, $this->empresaId);
    }
}
