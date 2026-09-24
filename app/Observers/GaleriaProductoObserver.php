<?php

namespace App\Observers;

use App\Models\GaleriaProducto;
use Illuminate\Support\Facades\Storage;

class GaleriaProductoObserver
{
    public function deleted(GaleriaProducto $galeria): void
    {
        if ($galeria->imagen_path) {
            Storage::disk('public')->delete($galeria->imagen_path);
        }
    }
}
