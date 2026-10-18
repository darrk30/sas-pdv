<?php

namespace App\Services;

use App\Models\Receta;
use App\Models\RecetaDetalle;

class RecetaCostoService
{
    /**
     * Recalcula y sincroniza el precio_costo de todos los productos/variantes
     * cuya receta use como insumo al producto o variante indicado.
     *
     * Se llama desde los observers de Producto y Variante cuando cambia precio_costo.
     * También puede llamarse manualmente desde cualquier otro contexto.
     */
    public static function sincronizarPorInsumo(?int $productoId = null, ?int $varianteId = null): void
    {
        if (! $productoId && ! $varianteId) return;

        // Busca todos los detalles de receta que usen este insumo
        $recetaIds = RecetaDetalle::query()
            ->when($varianteId, fn ($q) => $q->where('insumo_variante_id', $varianteId))
            ->when(! $varianteId && $productoId, fn ($q) => $q->where('insumo_producto_id', $productoId))
            ->distinct()
            ->pluck('receta_id');

        if ($recetaIds->isEmpty()) return;

        Receta::whereIn('id', $recetaIds)
            ->with([
                'detalles.unidadMedida',
                'detalles.insumoProducto.unidadMedida',
                'detalles.insumoVariante.producto.unidadMedida',
            ])
            ->get()
            ->each(fn (Receta $receta) => $receta->sincronizarCosto());
    }
}
