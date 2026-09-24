<?php

namespace App\Observers;

use App\Models\ProductoAtributoValor;
use Illuminate\Support\Facades\Storage;

class ProductoAtributoValorObserver
{
    public function updating(ProductoAtributoValor $valor): void
    {
        if ($valor->isDirty('imagen') && ($old = $valor->getOriginal('imagen'))) {
            Storage::disk('public')->delete($old);
        }
    }

    public function deleted(ProductoAtributoValor $valor): void
    {
        if ($valor->imagen) {
            Storage::disk('public')->delete($valor->imagen);
        }
    }
}
