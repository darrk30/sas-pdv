<?php

namespace App\Events;

use App\Models\Venta;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VentaCompletada
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Venta $venta,
        public readonly bool $enviarSunatInmediato = false,
    ) {}
}
