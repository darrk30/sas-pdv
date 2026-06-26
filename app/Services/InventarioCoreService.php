<?php

namespace App\Services;

use App\Enums\EstadoStock;
use App\Models\Ajuste;
use App\Models\Compra;
use App\Models\Inventario;
use App\Models\Producto;
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
    public function aplicarDetalles(
        int $empresaId,
        string $tipo,
        Collection $detalles,
        ?Model $movible = null,
        ?string $concepto = null,
        ?int $userId = null,
    ): void {
        DB::transaction(function () use ($empresaId, $tipo, $detalles, $movible, $concepto, $userId) {
            foreach ($detalles as $detalle) {
                $unidad = $this->resolverUnidad($detalle);

                if (! $unidad) {
                    \Log::warning('InventarioCoreService: unidad_id no encontrada, ítem omitido.', [
                        'detalle' => is_array($detalle) ? $detalle : $detalle->toArray(),
                    ]);
                    continue;
                }

                [$productoId, $varianteId] = $this->resolverItem($detalle);
                $cantidadOrig = (float) $this->campo($detalle, 'cantidad');
                $cantidadBase = $this->convertirABase($cantidadOrig, $unidad);

                $kardexCtx = null;
                if ($movible !== null) {
                    $kardexCtx = [
                        'user_id'           => $userId,
                        'movible'           => $movible,
                        'concepto'          => $concepto ?? '',
                        'cantidad'          => $cantidadOrig,
                        'unidad'            => $unidad->nombre ?? 'unidad',
                        'factor_conversion' => $unidad->factor_base ?? 1,
                        'costo_unitario'    => $this->campo($detalle, 'costo_unitario'),
                        'costo_total'       => $this->campo($detalle, 'costo_total'),
                        'precio_unitario'   => $this->campo($detalle, 'precio_unitario'),
                        'precio_total'      => $this->campo($detalle, 'precio_total'),
                    ];
                }

                $this->actualizarStock($empresaId, $productoId, $varianteId, $cantidadBase, $tipo, $kardexCtx);
            }
        });
    }

    /**
     * Revierte movimientos de stock (inverso de aplicarDetalles).
     *
     * @param  Collection<int, Model|array>  $detalles
     * @param  string  $tipo  tipo ORIGINAL del movimiento ('entrada' | 'salida')
     */
    public function revertirDetalles(
        int $empresaId,
        string $tipo,
        Collection $detalles,
        ?Model $movible = null,
        ?string $concepto = null,
        ?int $userId = null,
    ): void {
        $inverso = $tipo === 'entrada' ? 'salida' : 'entrada';
        $this->aplicarDetalles($empresaId, $inverso, $detalles, $movible, $concepto, $userId);
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
        $concepto = $ajuste->codigo ?? ('AJU-' . str_pad($ajuste->id, 8, '0', STR_PAD_LEFT));
        $this->aplicarDetalles($ajuste->empresa_id, $ajuste->tipo, $detalles, $ajuste, $concepto, $ajuste->user_id);
    }

    /**
     * Revierte el movimiento de stock de todos los detalles de un Ajuste.
     */
    public function revertirAjuste(Ajuste $ajuste): void
    {
        $detalles = $ajuste->detalles()->with('unidad')->get();
        $concepto = ($ajuste->codigo ?? ('AJU-' . str_pad($ajuste->id, 8, '0', STR_PAD_LEFT))) . ' (reversión)';
        $this->revertirDetalles($ajuste->empresa_id, $ajuste->tipo, $detalles, $ajuste, $concepto, $ajuste->user_id);
    }

    // -------------------------------------------------------------------------
    // Helpers para Compra (atajos de alto nivel)
    // -------------------------------------------------------------------------

    /**
     * Aplica stock de una Compra recibida (siempre es entrada).
     */
    public function aplicarCompra(Compra $compra): void
    {
        $detalles = $compra->detalles()->with('unidad')->get();
        $concepto = $compra->codigo ?? ('COMPRA-' . str_pad($compra->id, 8, '0', STR_PAD_LEFT));
        $this->aplicarDetalles($compra->empresa_id, 'entrada', $detalles, $compra, $concepto, $compra->user_id);
    }

    /**
     * Revierte el stock de una Compra (cuando pasa de recibido → pendiente o se elimina).
     */
    public function revertirCompra(Compra $compra): void
    {
        $detalles = $compra->detalles()->with('unidad')->get();
        $concepto = ($compra->codigo ?? ('COMPRA-' . str_pad($compra->id, 8, '0', STR_PAD_LEFT))) . ' (reversión)';
        $this->revertirDetalles($compra->empresa_id, 'entrada', $detalles, $compra, $concepto, $compra->user_id);
    }

    // -------------------------------------------------------------------------
    // Internos
    // -------------------------------------------------------------------------

    /**
     * Actualiza el stock_real del registro de Inventario correspondiente.
     * Usa SELECT FOR UPDATE para evitar condiciones de carrera concurrentes.
     * El registro se crea si no existe.
     * Si se provee $kardexCtx, registra el movimiento en el Kardex.
     *
     * Nota: debe llamarse siempre dentro de un DB::transaction().
     */
    private function actualizarStock(
        int $empresaId,
        int $productoId,
        ?int $varianteId,
        float $cantidadBase,
        string $tipo,
        ?array $kardexCtx = null,
    ): void {
        $inv = Inventario::where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('variante_id', $varianteId)
            ->lockForUpdate()
            ->first();

        if (! $inv) {
            $stockMinimo = (int) (Inventario::where('empresa_id', $empresaId)
                ->where('producto_id', $productoId)
                ->max('stock_minimo') ?? 0);

            $inv = Inventario::create([
                'empresa_id'        => $empresaId,
                'producto_id'       => $productoId,
                'variante_id'       => $varianteId,
                'stock_real'        => 0,
                'stock_reserva'     => 0,
                'stock_minimo'      => $stockMinimo,
                'estado_inventario' => EstadoStock::Agotado,
            ]);
        }

        $stockAntes = (float) $inv->stock_real;
        $nuevoStock = $stockAntes + ($tipo === 'entrada' ? $cantidadBase : -$cantidadBase);

        $inv->update([
            'stock_real'        => $nuevoStock,
            'estado_inventario' => $this->calcularEstado($nuevoStock, (float) $inv->stock_minimo),
        ]);

        if ($tipo === 'entrada' && $kardexCtx !== null) {
            $costoNuevo = (float) ($kardexCtx['costo_unitario'] ?? 0);
            if ($costoNuevo > 0) {
                $this->actualizarCostoPromedio($productoId, $varianteId, $stockAntes, $cantidadBase, $costoNuevo);
            }
        }

        if ($kardexCtx !== null) {
            app(KardexService::class)->registrar([
                'empresa_id'        => $empresaId,
                'user_id'           => $kardexCtx['user_id'] ?? null,
                'movible'           => $kardexCtx['movible'] ?? null,
                'producto_id'       => $productoId,
                'variante_id'       => $varianteId,
                'tipo'              => $tipo,
                'concepto'          => $kardexCtx['concepto'] ?? '',
                'notas'             => $kardexCtx['notas'] ?? null,
                'cantidad'          => $kardexCtx['cantidad'] ?? $cantidadBase,
                'unidad'            => $kardexCtx['unidad'] ?? 'unidad',
                'factor_conversion' => $kardexCtx['factor_conversion'] ?? 1,
                'cantidad_base'     => $cantidadBase,
                'costo_unitario'    => $kardexCtx['costo_unitario'] ?? null,
                'costo_total'       => $kardexCtx['costo_total'] ?? null,
                'precio_unitario'   => $kardexCtx['precio_unitario'] ?? null,
                'precio_total'      => $kardexCtx['precio_total'] ?? null,
                'stock_antes'       => $stockAntes,
                'stock_despues'     => $nuevoStock,
                'fecha'             => now(),
            ]);
        }
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

    /**
     * Recalcula y persiste el costo promedio ponderado del producto o variante
     * después de registrar una entrada de stock.
     *
     * Fórmula: (stock_antes × costo_actual + cantidad_nueva × costo_nuevo) / (stock_antes + cantidad_nueva)
     * Si no había stock previo (o costo previo = 0), el costo nuevo pasa directo.
     */
    private function actualizarCostoPromedio(
        int $productoId,
        ?int $varianteId,
        float $stockAntes,
        float $cantidadNueva,
        float $costoNuevo,
    ): void {
        if ($varianteId) {
            $variante = Variante::find($varianteId);
            if (! $variante) return;

            $costoActual = (float) ($variante->precio_costo ?? $variante->producto?->precio_costo ?? 0);
            $promedio    = $this->calcularPromedioPonderado($stockAntes, $costoActual, $cantidadNueva, $costoNuevo);
            $variante->update(['precio_costo' => $promedio]);
        } else {
            $producto = Producto::find($productoId);
            if (! $producto) return;

            $costoActual = (float) ($producto->precio_costo ?? 0);
            $promedio    = $this->calcularPromedioPonderado($stockAntes, $costoActual, $cantidadNueva, $costoNuevo);
            $producto->update(['precio_costo' => $promedio]);
        }
    }

    private function calcularPromedioPonderado(
        float $stockAntes,
        float $costoActual,
        float $cantidadNueva,
        float $costoNuevo,
    ): float {
        if ($stockAntes <= 0 || $costoActual <= 0) {
            return round($costoNuevo, 2);
        }

        $valorActual = $stockAntes * $costoActual;
        $valorNuevo  = $cantidadNueva * $costoNuevo;

        return round(($valorActual + $valorNuevo) / ($stockAntes + $cantidadNueva), 2);
    }
}
