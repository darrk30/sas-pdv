<?php

namespace App\Observers;

use App\Models\Ajuste;
use App\Services\InventarioCoreService;

class AjusteObserver
{
    /**
     * Genera el código único del ajuste antes de insertarlo.
     * Formato: AJ-00001, AJ-00002 ... por empresa.
     */
    public function creating(Ajuste $ajuste): void
    {
        $siguiente = Ajuste::where('empresa_id', $ajuste->empresa_id)->count() + 1;
        $ajuste->codigo = 'AJ-' . str_pad($siguiente, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Revierte el stock SOLO si el ajuste estaba confirmado.
     * Los borradores nunca aplicaron stock, así que no hay nada que revertir.
     * Se dispara antes de eliminar, mientras los AjusteDetalles aún existen en BD.
     */
    public function deleting(Ajuste $ajuste): void
    {
        if ($ajuste->estaConfirmado()) {
            app(InventarioCoreService::class)->revertirAjuste($ajuste);
        }
    }
}
