<?php

namespace App\Services;

use App\Models\Kardex;
use App\Models\Producto;
use App\Models\Variante;

class KardexService
{
    public function registrar(array $datos): Kardex
    {
        $productoNombre = $datos['producto_nombre']
            ?? ($datos['producto_id'] ? Producto::find($datos['producto_id'])?->nombre : null)
            ?? 'Producto';

        $varianteNombre = null;
        if (!empty($datos['variante_id'])) {
            $varianteNombre = $datos['variante_nombre'] ?? $this->resolverNombreVariante((int) $datos['variante_id']);
        }

        $movible = $datos['movible'] ?? null;

        return Kardex::create([
            'empresa_id'      => $datos['empresa_id'],
            'user_id'         => $datos['user_id'] ?? null,
            'movible_type'    => $movible ? get_class($movible) : null,
            'movible_id'      => $movible?->id ?? null,
            'producto_id'     => $datos['producto_id'] ?? null,
            'variante_id'     => $datos['variante_id'] ?? null,
            'producto_nombre' => $productoNombre,
            'variante_nombre' => $varianteNombre,
            'tipo'            => $datos['tipo'],
            'concepto'        => $datos['concepto'],
            'notas'           => $datos['notas'] ?? null,
            'cantidad'        => $datos['cantidad'],
            'unidad'          => $datos['unidad'] ?? 'unidad',
            'factor_conversion' => $datos['factor_conversion'] ?? 1,
            'cantidad_base'   => $datos['cantidad_base'],
            'costo_unitario'  => $datos['costo_unitario'] ?? null,
            'costo_total'     => $datos['costo_total'] ?? null,
            'precio_unitario' => $datos['precio_unitario'] ?? null,
            'precio_total'    => $datos['precio_total'] ?? null,
            'stock_antes'     => $datos['stock_antes'],
            'stock_despues'   => $datos['stock_despues'],
            'fecha'           => $datos['fecha'] ?? now(),
        ]);
    }

    private function resolverNombreVariante(int $varianteId): ?string
    {
        $variante = Variante::with('valores.valor')->find($varianteId);
        if (! $variante) return null;

        $atributos = $variante->valores
            ->map(fn($pav) => $pav->valor?->nombre)
            ->filter()
            ->implode(' - ');

        return $atributos ?: null;
    }
}
