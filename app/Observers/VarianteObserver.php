<?php

namespace App\Observers;

use App\Models\Variante;
use Illuminate\Support\Facades\Storage;

class VarianteObserver
{
    public function updating(Variante $variante): void
    {
        if ($variante->isDirty('imagen') && ($old = $variante->getOriginal('imagen'))) {
            Storage::disk('public')->delete($old);
        }
    }

    public function deleted(Variante $variante): void
    {
        if ($variante->imagen) {
            Storage::disk('public')->delete($variante->imagen);
        }
    }
}
