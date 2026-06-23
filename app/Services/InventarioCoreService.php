<?php

namespace App\Services;

use App\Enums\EstadoStock;
use App\Models\Ajuste;
use App\Models\Inventario;
use App\Models\UnidadesMedida;
use App\Models\Variante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventarioCoreService
{
    // -------------------------------------------------------------------------
    // Conversión de unidades
    // -------------------------------------------------------------------------

    /**
     * Convierte una cantidad a la unidad base de su dimensión.
     * Usa el accesor recursivo factor_base del modelo UnidadesMedida.
     *
     * Ejemplo: 500 gramos → 500 * 0.001 = 0.5 kg (si kg es la base)
     */
    public function convertirABase(float $cantidad, UnidadesMedida $unidad): float
    {
        return $cantidad * $unidad->factor_base;
    }

    // -------------------------------------------------------------------------
    // Movimientos de stock genéricos
    // -------------------------------------------------------------------------

    /**
     * Aplica movimientos de stock para una colección de ítems.
     * Sirve para cualquier origen: ajustes, ventas, compras, devoluciones.
     *
     * Cada ítem debe tener los campos: producto_id, variante_id, unidad_id, cantidad.
     * Si el ítem es un modelo Eloquent con la relación 'unidad' cargada, la usa directamente.
     *
     * @param  Collection<int, Model|array>  $detalles
     * @param  string  $tipo  'entrada' | 'salida'
     */
    public function aplicarDetalles(int $empresaId, string $tipo, Collection $detalles): void
    {
        DB::transaction(function () use ($empresaId, $tipo, $detalles) {
            foreach ($detalles as $detalle) {
                $unidad = $this->resolverUnidad($detalle);

                if (! $unidad) {
                    continue;
                }

                [$productoId, $varianteId] = $this->resolverItem($detalle);
                $cantidadBase = $this->convertirABase((float) $this->campo($detalle, 'cantidad'), $unidad);

                $this->actualizarStock($empresaId, $productoId, $varianteId, $cantidadBase, $tipo);
            }
        });
    }

    /**
     * Revierte movimientos de stock (inverso de aplicarDetalles).
     *
     * @param  Collection<int, Model|array>  $detalles
     * @param  string  $tipo  tipo ORIGINAL del movimiento ('entrada' | 'salida')
     */
    public function revertirDetalles(int $empresaId, string $tipo, Collection $detalles): void
    {
        $inverso = $tipo === 'entrada' ? 'salida' : 'entrada';
        $this->aplicarDetalles($empresaId, $inverso, $detalles);
    }

    // -------------------------------------------------------------------------
    // Helpers para Ajuste (atajo de alto nivel)
    // -------------------------------------------------------------------------

    /**
     * Aplica el movimiento de stock de todos los detalles de un Ajuste.
     */
    public function aplicarAjuste(Ajuste $ajuste): void
    {
        $detalles = $ajuste->detalles()->with('unidad')->get();
        $this->aplicarDetalles($ajuste->empresa_id, $ajuste->tipo, $detalles);
    }

    /**
     * Revierte el movimiento de stock de todos los detalles de un Ajuste.
     */
    public function revertirAjuste(Ajuste $ajuste): void
    {
        $detalles = $ajuste->detalles()->with('unidad')->get();
        $this->revertirDetalles($ajuste->empresa_id, $ajuste->tipo, $detalles);
    }

    // -------------------------------------------------------------------------
    // Internos
    // -------------------------------------------------------------------------

    /**
     * Actualiza el stock_real del registro de Inventario correspondiente.
     * Usa SELECT FOR UPDATE para evitar condiciones de carrera concurrentes.
     * El registro se crea si no existe.
     *
     * Nota: debe llamarse siempre dentro de un DB::transaction().
     */
    private function actualizarStock(
        int $empresaId,
        int $productoId,
        ?int $varianteId,
        float $cantidadBase,
        string $tipo,
    ): void {
        $inv = Inventario::where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('variante_id', $varianteId)
            ->lockForUpdate()
            ->first();

        if (! $inv) {
            $inv = Inventario::create([
                'empresa_id'       => $empresaId,
                'producto_id'      => $productoId,
                'variante_id'      => $varianteId,
                'stock_real'       => 0,
                'stock_reserva'    => 0,
                'stock_minimo'     => 0,
                'estado_inventario' => EstadoStock::Agotado,
            ]);
        }

        $nuevoStock = (float) $inv->stock_real + ($tipo === 'entrada' ? $cantidadBase : -$cantidadBase);

        $inv->update([
            'stock_real'        => $nuevoStock,
            'estado_inventario' => $this->calcularEstado($nuevoStock, (float) $inv->stock_minimo),
        ]);
    }

    /**
     * Calcula el EstadoStock según el stock resultante y el mínimo configurado.
     *
     * - stock <= 0                          → agotado
     * - 0 < stock <= stock_minimo           → por_agotarse
     * - stock > stock_minimo                → disponible
     */
    private function calcularEstado(float $stockReal, float $stockMinimo): EstadoStock
    {
        if ($stockReal <= 0) {
            return EstadoStock::Agotado;
        }

        if ($stockMinimo > 0 && $stockReal <= $stockMinimo) {
            return EstadoStock::PorAgotarse;
        }

        return EstadoStock::Disponible;
    }

    /**
     * Resuelve el producto_id y variante_id para el inventario.
     *
     * En AjusteDetalle (y futuros detalles) una variante tiene producto_id=null.
     * Pero inventarios.producto_id es NOT NULL → se obtiene el padre desde la variante.
     *
     * @return array{0: int, 1: int|null}  [$productoId, $varianteId]
     */
    private function resolverItem(mixed $detalle): array
    {
        $productoId = (int) $this->campo($detalle, 'producto_id') ?: null;
        $varianteId = (int) $this->campo($detalle, 'variante_id') ?: null;

        // Para variantes sin producto_id, lo buscamos desde la variante
        if ($varianteId && ! $productoId) {
            $productoId = Variante::find($varianteId)?->producto_id;
        }

        return [$productoId, $varianteId];
    }

    /**
     * Resuelve la UnidadesMedida del detalle.
     * Si el modelo tiene la relación 'unidad' cargada la usa; de lo contrario hace DB query.
     */
    private function resolverUnidad(mixed $detalle): ?UnidadesMedida
    {
        if ($detalle instanceof Model && $detalle->relationLoaded('unidad')) {
            return $detalle->unidad;
        }

        $unidadId = $this->campo($detalle, 'unidad_id');

        return $unidadId ? UnidadesMedida::find($unidadId) : null;
    }

    /**
     * Lee un campo tanto de un Model Eloquent como de un array.
     */
    private function campo(mixed $item, string $campo): mixed
    {
        return $item instanceof Model ? $item->{$campo} : ($item[$campo] ?? null);
    }
}
