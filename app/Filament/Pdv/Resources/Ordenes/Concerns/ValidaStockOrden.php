<?php

namespace App\Filament\Pdv\Resources\Ordenes\Concerns;

use App\Models\Producto;
use App\Models\Promocion;
use App\Models\Variante;
use App\Services\EtiquetaStockService;
use Illuminate\Support\Facades\DB;

trait ValidaStockOrden
{
    /**
     * Verifica que haya stock_reserva suficiente para los nuevos detalles.
     * $nuevos: arrays con claves producto_id / variante_id / promocion_id / cantidad (del formulario).
     * $viejos: mismo formato pero del estado anterior en DB (vacío al crear).
     */
    protected function validarStockDetalles(array $nuevos, array $viejos, int $empresaId): array
    {
        // Expandir demanda vieja (lo que ya estaba reservado por esta orden)
        $demandaVieja = [];
        foreach ($viejos as $old) {
            $qty = (float) ($old['cantidad'] ?? 0);
            if (!empty($old['promocion_id'])) {
                $this->expandirPromoParaStock((int) $old['promocion_id'], $qty, $demandaVieja);
            } elseif (!empty($old['variante_id'])) {
                $k = "v:{$old['variante_id']}";
                $demandaVieja[$k] = ($demandaVieja[$k] ?? 0.0) + $qty;
            } elseif (!empty($old['producto_id'])) {
                $k = "p:{$old['producto_id']}";
                $demandaVieja[$k] = ($demandaVieja[$k] ?? 0.0) + $qty;
            }
        }

        // Expandir demanda nueva (lo que el formulario quiere reservar)
        $demandaNueva = [];
        foreach ($nuevos as $item) {
            $qty = (float) ($item['cantidad'] ?? 0);
            if ($qty <= 0) continue;
            $promoId    = !empty($item['promocion_id']) ? (int) $item['promocion_id'] : null;
            $varianteId = !empty($item['variante_id'])  ? (int) $item['variante_id']  : null;
            $productoId = !empty($item['producto_id'])  ? (int) $item['producto_id']  : null;

            if ($promoId) {
                $this->expandirPromoParaStock($promoId, $qty, $demandaNueva);
            } elseif ($varianteId) {
                $k = "v:{$varianteId}";
                $demandaNueva[$k] = ($demandaNueva[$k] ?? 0.0) + $qty;
            } elseif ($productoId) {
                $k = "p:{$productoId}";
                $demandaNueva[$k] = ($demandaNueva[$k] ?? 0.0) + $qty;
            }
        }

        // Para cada producto que AUMENTA, verificar stock disponible
        $errores = [];
        foreach ($demandaNueva as $key => $nuevaQty) {
            $viejaQty = $demandaVieja[$key] ?? 0.0;
            if ($nuevaQty <= $viejaQty) continue; // disminuye o igual → no hace falta validar

            [$tipo, $id] = explode(':', $key, 2);

            if ($tipo === 'v') {
                $variante = Variante::with('producto')->find($id);
                $producto = $variante?->producto;
                if (!$producto?->control_de_stock || $producto->venta_sin_stock) continue;
                $stockReserva = (float) (DB::table('inventarios')
                    ->where('empresa_id', $empresaId)
                    ->where('variante_id', $id)
                    ->value('stock_reserva') ?? 0);
                $nombre = $producto->nombre . ' (var. ' . $variante->id . ')';
            } else {
                $producto = Producto::find($id);
                if (!$producto?->control_de_stock || $producto->venta_sin_stock) continue;
                $stockReserva = (float) (DB::table('inventarios')
                    ->where('empresa_id', $empresaId)
                    ->where('producto_id', $id)
                    ->whereNull('variante_id')
                    ->value('stock_reserva') ?? 0);
                $nombre = $producto->nombre;
            }

            // efectivo disponible = stock_reserva actual + lo que esta orden ya tenía reservado
            $efectivo = $stockReserva + $viejaQty;
            if ($nuevaQty > $efectivo) {
                $disp    = number_format(max(0.0, $efectivo), 2);
                $errores[] = "«{$nombre}»: se necesitan {$nuevaQty} und. pero solo hay {$disp} disponibles.";
            }
        }

        return $errores;
    }

    /**
     * Decrementa stock_reserva al crear una orden desde el admin.
     * Para venta_sin_stock=true permite stock_reserva negativo.
     */
    protected function reservarStockDetalles(array $detalles, int $empresaId): void
    {
        $productosAfectados = [];

        foreach ($detalles as $item) {
            $qty        = (float) ($item['cantidad'] ?? 0);
            $promoId    = !empty($item['promocion_id']) ? (int) $item['promocion_id'] : null;
            $varianteId = !empty($item['variante_id'])  ? (int) $item['variante_id']  : null;
            $productoId = !empty($item['producto_id'])  ? (int) $item['producto_id']  : null;

            if ($qty <= 0) continue;

            if ($promoId) {
                $promo = Promocion::with(['detalles.producto', 'detalles.variante.producto'])->find($promoId);
                if (!$promo) continue;
                foreach ($promo->detalles as $pd) {
                    $cantDet = $qty * (float) $pd->cantidad;
                    if ($pd->variante_id && $pd->variante?->producto?->control_de_stock) {
                        $expr = $pd->variante->producto->venta_sin_stock
                            ? "stock_reserva - {$cantDet}"
                            : "GREATEST(0, stock_reserva - {$cantDet})";
                        DB::table('inventarios')
                            ->where('empresa_id', $empresaId)
                            ->where('variante_id', $pd->variante_id)
                            ->update(['stock_reserva' => DB::raw($expr)]);
                        if ($pd->variante->producto_id) $productosAfectados[] = $pd->variante->producto_id;
                    } elseif ($pd->producto_id && $pd->producto?->control_de_stock) {
                        $expr = $pd->producto->venta_sin_stock
                            ? "stock_reserva - {$cantDet}"
                            : "GREATEST(0, stock_reserva - {$cantDet})";
                        DB::table('inventarios')
                            ->where('empresa_id', $empresaId)
                            ->where('producto_id', $pd->producto_id)
                            ->whereNull('variante_id')
                            ->update(['stock_reserva' => DB::raw($expr)]);
                        $productosAfectados[] = $pd->producto_id;
                    }
                }
            } elseif ($varianteId) {
                $variante = Variante::with('producto')->find($varianteId);
                if (!$variante?->producto?->control_de_stock) continue;
                $expr = $variante->producto->venta_sin_stock
                    ? "stock_reserva - {$qty}"
                    : "GREATEST(0, stock_reserva - {$qty})";
                DB::table('inventarios')
                    ->where('empresa_id', $empresaId)
                    ->where('variante_id', $varianteId)
                    ->update(['stock_reserva' => DB::raw($expr)]);
                $productosAfectados[] = $variante->producto_id;
            } elseif ($productoId) {
                $producto = Producto::find($productoId);
                if (!$producto?->control_de_stock) continue;
                $expr = $producto->venta_sin_stock
                    ? "stock_reserva - {$qty}"
                    : "GREATEST(0, stock_reserva - {$qty})";
                DB::table('inventarios')
                    ->where('empresa_id', $empresaId)
                    ->where('producto_id', $productoId)
                    ->whereNull('variante_id')
                    ->update(['stock_reserva' => DB::raw($expr)]);
                $productosAfectados[] = $productoId;
            }
        }

        if ($productosAfectados) {
            $service = app(EtiquetaStockService::class);
            foreach (array_unique($productosAfectados) as $pid) {
                $service->sincronizar($pid, $empresaId);
            }
        }
    }

    /**
     * Expande los productos internos de una promo en el mapa de demanda.
     * Solo incluye productos con control_de_stock habilitado.
     */
    private function expandirPromoParaStock(int $promoId, float $cantPromo, array &$mapa): void
    {
        static $cache = [];
        $cache[$promoId] ??= Promocion::with(['detalles.producto', 'detalles.variante.producto'])->find($promoId);
        $promo = $cache[$promoId];
        if (!$promo) return;

        foreach ($promo->detalles as $pd) {
            $cantDet = $cantPromo * (float) $pd->cantidad;
            if ($pd->variante_id && $pd->variante?->producto?->control_de_stock) {
                $k = "v:{$pd->variante_id}";
                $mapa[$k] = ($mapa[$k] ?? 0.0) + $cantDet;
            } elseif ($pd->producto_id && $pd->producto?->control_de_stock) {
                $k = "p:{$pd->producto_id}";
                $mapa[$k] = ($mapa[$k] ?? 0.0) + $cantDet;
            }
        }
    }
}
