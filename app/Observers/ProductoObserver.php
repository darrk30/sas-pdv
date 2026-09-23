<?php

namespace App\Observers;

use App\Models\Producto;
use Illuminate\Support\Facades\Storage;

class ProductoObserver
{
    public function updating(Producto $producto): void
    {
        if ($producto->isDirty('logo') && ($old = $producto->getOriginal('logo'))) {
            Storage::disk('public')->delete($old);
        }
    }

    public function deleted(Producto $producto): void
    {
        if ($producto->logo) {
            Storage::disk('public')->delete($producto->logo);
        }

        // Elimina físicamente las imágenes de galería (los registros se borran por cascada)
        $producto->galeriaProductos()->each(function ($galeria) {
            if ($galeria->imagen_path) {
                Storage::disk('public')->delete($galeria->imagen_path);
            }
        });
    }
}
