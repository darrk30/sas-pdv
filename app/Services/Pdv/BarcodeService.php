<?php

namespace App\Services\Pdv;

use App\Models\Producto;
use App\Models\Variante;

class BarcodeService
{
    /**
     * Busca el código en variantes primero, luego en productos.
     *
     * Devuelve:
     *   ['type' => 'variante', 'data' => Variante]
     *   ['type' => 'producto', 'data' => Producto]
     *   null si no se encuentra nada
     */
    public function lookup(string $code, int $empresaId): ?array
    {
        // 1. Variante con ese código de barras específico
        $variante = Variante::where('empresa_id', $empresaId)
            ->where('estado', 'activo')
            ->where('codigo_barras', $code)
            ->with([
                'producto',
                'producto.unidadMedida.dimension',
                'inventario',
                'valores.valor',
            ])
            ->first();

        if ($variante) {
            return ['type' => 'variante', 'data' => $variante];
        }

        // 2. Producto por codigo_barras o codigo_interno
        $producto = Producto::where('empresa_id', $empresaId)
            ->where('estado', 'activo')
            ->where(fn ($q) => $q->where('codigo_barras', $code)->orWhere('codigo_interno', $code))
            ->with([
                'inventario',
                'variantesActivas.inventario',
                'variantesActivas.valores',
                'atributos.atributo',
                'atributos.detallesPrecios.valor',
                'atributos.detallesExclusiones',
                'unidadMedida.dimension',
            ])
            ->first();

        if ($producto) {
            return ['type' => 'producto', 'data' => $producto];
        }

        return null;
    }

    /**
     * Construye el array variantData para Alpine $store.vm.abrir().
     * Replica la misma estructura que genera product-catalog.blade.php.
     */
    public function buildVariantData(Producto $producto): array
    {
        $precioNorm  = (float) $producto->precio_venta;
        $precioFinal = ($producto->porcentaje_descuento > 0 && $producto->precio_con_descuento)
            ? (float) $producto->precio_con_descuento
            : $precioNorm;
        $esDecimal = $producto->unidadMedida?->esContinua() ?? false;

        $pavIdsUsados = $producto->variantesActivas
            ->flatMap(fn ($v) => $v->valores->pluck('id'))
            ->unique()->flip()->toArray();

        return [
            'productoId'         => $producto->id,
            'nombre'             => $producto->nombre,
            'precioBase'         => $precioFinal,
            'precioBaseOriginal' => $precioNorm,
            'controlStock'       => (bool) $producto->control_de_stock,
            'ventaSinStock'      => (bool) $producto->venta_sin_stock,
            'esCortesia'         => (bool) $producto->es_cortesia,
            'esDecimal'          => $esDecimal,
            'unidadSimbolo'      => $producto->unidadMedida?->simbolo ?? '',
            'atributos'          => $producto->atributos
                ->map(fn ($pa) => [
                    'id'      => $pa->id,
                    'nombre'  => $pa->atributo->nombre,
                    'valores' => $pa->detallesPrecios
                        ->filter(fn ($pav) => isset($pavIdsUsados[$pav->id]))
                        ->map(fn ($pav) => [
                            'id'               => $pav->id,
                            'valor_id'         => $pav->valor_id,
                            'nombre'           => $pav->valor->nombre,
                            'precio_adicional' => (float) $pav->precio_adicional,
                        ])->values()->all(),
                ])
                ->filter(fn ($a) => count($a['valores']) > 0)
                ->values()->all(),
            'variantes'          => $producto->variantesActivas
                ->map(fn ($v) => [
                    'id'      => $v->id,
                    'pav_ids' => $v->valores->pluck('id')->toArray(),
                    'stock'   => (float) ($v->inventario?->stock_reserva ?? 0),
                ])->values()->all(),
            'exclusiones'        => $producto->atributos->reduce(function ($carry, $pa) {
                foreach ($pa->detallesExclusiones as $ex) {
                    $carry[(string) $ex->valor_base_id][] = $ex->valor_exluido_id;
                }
                return $carry;
            }, []),
        ];
    }
}
