<?php

namespace App\Observers;

use App\Models\Variante;
use App\Services\RecetaCostoService;
use Illuminate\Support\Facades\Storage;

class VarianteObserver
{
    public function updating(Variante $variante): void
    {
        if ($variante->isDirty('imagen') && ($old = $variante->getOriginal('imagen'))) {
            Storage::disk('public')->delete($old);
        }
    }

    public function updated(Variante $variante): void
    {
        if ($variante->wasChanged('precio_costo')) {
            RecetaCostoService::sincronizarPorInsumo(varianteId: $variante->id);
        }
    }

    public function deleted(Variante $variante): void
    {
        if ($variante->imagen) {
            Storage::disk('public')->delete($variante->imagen);
        }
    }
}
