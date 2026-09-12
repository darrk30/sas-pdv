<?php

namespace App\Observers;

use App\Events\MesaActualizada;
use App\Models\Mesa;

class MesaObserver
{
    public function updated(Mesa $mesa): void
    {
        if ($mesa->wasChanged('estado_ocupacion')) {
            MesaActualizada::dispatch($mesa->empresa_id);
        }
    }
}
