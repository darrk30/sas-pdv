<?php

namespace App\Services;

use App\Enums\ProductoEtiqueta;
use App\Models\Inventario;
use App\Models\Producto;

class EtiquetaStockService
{
    /**
     * Verifica stock_reserva y actualiza la etiqueta del producto si corresponde.
     * Solo actúa sobre productos con control_de_stock=true y venta_sin_stock=false.
     * Respeta etiquetas manuales (NUEVO, OFERTA, etc.) — solo toca AGOTADO.
     */
    public function sincronizar(int $productoId, int $empresaId): void
    {
        $producto = Producto::where('id', $productoId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (! $producto || ! $producto->control_de_stock || $producto->venta_sin_stock) {
            return;
        }

        $agotado = $this->estaAgotado($productoId, $empresaId);

        if ($agotado && $producto->etiqueta !== ProductoEtiqueta::AGOTADO) {
            $producto->update(['etiqueta' => ProductoEtiqueta::AGOTADO]);
        } elseif (! $agotado && $producto->etiqueta === ProductoEtiqueta::AGOTADO) {
            $producto->update(['etiqueta' => null]);
        }
    }

    /**
     * Determina si el producto está agotado según stock_reserva.
     * Con variantes: agotado si TODAS las variantes activas tienen stock_reserva <= 0.
     * Sin variantes: agotado si el inventario del producto tiene stock_reserva <= 0.
     */
    private function estaAgotado(int $productoId, int $empresaId): bool
    {
        // Verificar si tiene variantes activas
        $variantes = \App\Models\Variante::where('producto_id', $productoId)
            ->where('estado', 'activo')
            ->pluck('id');

        if ($variantes->isNotEmpty()) {
            // Agotado si TODAS las variantes tienen stock_reserva <= 0
            return Inventario::where('empresa_id', $empresaId)
                ->whereIn('variante_id', $variantes)
                ->where('stock_reserva', '>', 0)
                ->doesntExist();
        }

        // Producto simple
        $inv = Inventario::where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->whereNull('variante_id')
            ->first();

        return (float) ($inv?->stock_reserva ?? 0) <= 0;
    }
}
