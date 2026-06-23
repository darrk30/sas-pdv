<?php

namespace App\Observers;

use App\Models\Ajuste;
use App\Services\InventarioCoreService;

class AjusteObserver
{
    /**
     * Se dispara ANTES de eliminar el Ajuste.
     * En este punto los AjusteDetalles aún existen en la BD
     * (el CASCADE de la FK los borra después).
     */
    public function deleting(Ajuste $ajuste): void
    {
        app(InventarioCoreService::class)->revertirAjuste($ajuste);
    }
}
