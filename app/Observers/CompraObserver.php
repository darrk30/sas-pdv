<?php

namespace App\Observers;

use App\Models\Compra;
use App\Services\InventarioCoreService;

class CompraObserver
{
    public function creating(Compra $compra): void
    {
        $siguiente = Compra::where('empresa_id', $compra->empresa_id)->count() + 1;
        $compra->codigo = 'CO-' . str_pad($siguiente, 5, '0', STR_PAD_LEFT);
    }

    public function deleting(Compra $compra): void
    {
        // Si fue recibida, revertir el stock antes de eliminar
        if ($compra->estaRecibida()) {
            app(InventarioCoreService::class)->revertirCompra($compra);
        }
    }
}
