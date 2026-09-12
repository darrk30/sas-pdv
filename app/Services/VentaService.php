<?php

namespace App\Services;

use App\Enums\EstadoMovimiento;
use App\Enums\EstadoSesion;
use App\Enums\EstadoVenta;
use App\Enums\TipoComprobante;
use App\Enums\TipoItem;
use App\Enums\TipoMovimiento;
use App\Enums\TipoPago;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\Serie;
use App\Models\SesionCaja;
use App\Models\Transaccion;
use App\Models\Variante;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use Illuminate\Support\Facades\DB;

class VentaService
{
    public function __construct(private KardexService $kardex) {}

    /**
     * Crea una Venta completa con todos sus detalles, pagos, stock y kardex.
     *
     * @param  array  $items   [ tipo, id, nombre, precio, cantidad, costo?, cortesia?, producto_id? ]
     *                          tipo: 'producto' | 'variante' | 'promocion'
     *                          costo: si se omite o null, el servicio lo consulta de Producto/Variante
     * @param  array  $pagos   [ metodo_pago_id, monto, referencia?, condicion_pago? ]
     *                          condicion_pago: 'contado' (default) | 'credito'
     * @param  float  $igvPct  Porcentaje IGV (ej. 18.0).  Usa 0 para tickets sin IGV es automático.
     * @param  string $conceptoPrefix  Prefijo para Transaccion.concepto (ej. "Mesa 1").
     *                                 Si vacío, usa "Venta SERIE-CORR".
     *
     * @throws \RuntimeException con mensaje '__SIN_SESION__' si no hay sesión de caja activa.
     * @throws \RuntimeException con mensaje de stock insuficiente si aplica.
     */
    public function procesar(
        int     $empresaId,
        int     $serieId,
        ?int    $clienteId,
        ?string $clienteNombre,
        ?string $clienteTipoDoc,
        array   $items,
        array   $pagos,
        float   $descuento          = 0.0,
        bool    $despachoRequerido  = false,
        string  $despachoDireccion  = '',
        string  $conceptoPrefix     = '',
        float   $igvPct             = 18.0,
        bool    $stockYaReservado   = false,
    ): Venta {
        $venta = null;

        DB::transaction(function () use (
            $empresaId, $serieId, $clienteId, $clienteNombre, $clienteTipoDoc,
            $items, $pagos, $descuento, $despachoRequerido, $despachoDireccion,
            $conceptoPrefix, $igvPct, $stockYaReservado, &$venta
        ) {
            // ── Serie / correlativo ───────────────────────────────────────────

            $serie       = Serie::lockForUpdate()->findOrFail($serieId);
            $nuevoNum    = $serie->numero + 1;
            $serie->update(['numero' => $nuevoNum]);
            $correlativo = str_pad($nuevoNum, 8, '0', STR_PAD_LEFT);

            // ── Sesión de caja ────────────────────────────────────────────────

            $sesionCaja = SesionCaja::where('empresa_id', $empresaId)
                ->where('user_id', auth()->id())
                ->where('estado', EstadoSesion::Abierta->value)
                ->latest()
                ->lockForUpdate()
                ->first();

            if (! $sesionCaja) {
                throw new \RuntimeException('__SIN_SESION__');
            }

            // ── Tasas ─────────────────────────────────────────────────────────

            $esTicket = $serie->tipo === TipoComprobante::Ticket;
            $tasaIgv  = $esTicket ? 0.0 : ($igvPct / 100);

            // ── Totales ───────────────────────────────────────────────────────

            $totalBruto        = round(array_sum(array_map(
                fn($i) => (float) $i['precio'] * (float) $i['cantidad'],
                $items
            )), 2);
            $totalConDescuento = round(max(0.0, $totalBruto - $descuento), 2);
            $opGravadas        = $esTicket ? 0.0 : round($totalConDescuento / (1 + $tasaIgv), 2);
            $opInafectas       = $esTicket ? $totalConDescuento : 0.0;
            $igv               = $esTicket ? 0.0 : round($totalConDescuento - $opGravadas, 2);

            $pagosContado   = array_values(array_filter($pagos, fn($p) => ($p['condicion_pago'] ?? 'contado') !== 'credito'));
            $pagosCredito   = array_values(array_filter($pagos, fn($p) => ($p['condicion_pago'] ?? 'contado') === 'credito'));
            $montoContado   = round(array_sum(array_column($pagosContado, 'monto')), 2);
            $montoPagado    = min($montoContado, $totalConDescuento);
            $saldoPendiente = round(max(0.0, $totalConDescuento - $montoPagado), 2);
            $tipoPago       = count($pagosCredito) > 0 ? TipoPago::Credito : TipoPago::Contado;
            $estadoPago     = $saldoPendiente > 0.01 ? 'pendiente' : 'pagado';

            // ── Cliente ───────────────────────────────────────────────────────

            $cliente             = $clienteId ? Cliente::find($clienteId) : null;
            $clienteNombreFinal  = $cliente?->nombre_completo ?? $clienteNombre;
            $clienteTipoDocFinal = $cliente?->tipo_documento?->value ?? $clienteTipoDoc;
            $clienteNumDocFinal  = $cliente?->numero_documento ?? '00000000';

            // ── Venta ─────────────────────────────────────────────────────────

            $venta = Venta::create([
                'empresa_id'         => $empresaId,
                'sesion_caja_id'     => $sesionCaja->id,
                'cliente_id'         => $clienteId,
                'cliente_nombre'     => $clienteNombreFinal,
                'cliente_tipo_doc'   => $clienteTipoDocFinal,
                'cliente_num_doc'    => $clienteNumDocFinal,
                'serie_id'           => $serieId,
                'correlativo'        => $correlativo,
                'tipo_pago'          => $tipoPago,
                'op_gravadas'        => $opGravadas,
                'op_exoneradas'      => 0,
                'op_inafectas'       => $opInafectas,
                'descuento_total'    => $descuento,
                'igv'                => $igv,
                'total'              => $totalConDescuento,
                'costo_total'        => 0,
                'monto_pagado'       => $montoPagado,
                'saldo_pendiente'    => $saldoPendiente,
                'estado_pago'        => $estadoPago,
                'estado'             => EstadoVenta::Completada,
                'estado_despacho'    => $despachoRequerido ? 'pendiente_envio' : null,
                'despacho_direccion' => $despachoRequerido && $despachoDireccion !== '' ? $despachoDireccion : null,
            ]);

            // ── VentaDetalles ─────────────────────────────────────────────────

            $costoTotalVenta = 0.0;

            foreach ($items as $item) {
                $tipo     = $item['tipo'];
                $cantidad = (float) $item['cantidad'];
                $precio   = (float) $item['precio'];

                if (isset($item['costo'])) {
                    $costoUnitario = (float) $item['costo'];
                } elseif ($tipo === 'variante') {
                    $v             = Variante::with('producto')->find($item['id']);
                    $costoUnitario = (float) ($v?->precio_costo ?? $v?->producto?->precio_costo ?? 0);
                } elseif ($tipo === 'producto') {
                    $costoUnitario = (float) (Producto::find($item['id'])?->precio_costo ?? 0);
                } else {
                    $costoUnitario = 0.0;
                }

                $calc = VentaDetalle::calcular(
                    cantidad: $cantidad,
                    precioUnitario: $precio,
                    costoUnitario: $costoUnitario,
                    tasaIgv: $tasaIgv,
                );

                $tipoItem   = match ($tipo) {
                    'variante'  => TipoItem::Variante,
                    'promocion' => TipoItem::Promocion,
                    default     => TipoItem::Producto,
                };
                $esCortesia = $item['cortesia'] ?? false;

                $dd = [
                    'venta_id'        => $venta->id,
                    'tipo_item'       => $tipoItem,
                    'descripcion'     => $esCortesia ? $item['nombre'] . ' (Cortesía)' : $item['nombre'],
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precio,
                    'valor_unitario'  => $calc['valorUnitario'],
                    'costo_unitario'  => $costoUnitario,
                    'descuento'       => 0,
                    'subtotal'        => $calc['subtotal'],
                    'valor_total'     => $calc['valorTotal'],
                    'igv'             => $calc['igv'],
                    'total'           => $calc['total'],
                    'costo_total'     => $calc['costoTotal'],
                ];

                if ($tipo === 'producto') {
                    $dd['producto_id'] = $item['id'];
                } elseif ($tipo === 'variante') {
                    $dd['variante_id'] = $item['id'];
                    $dd['producto_id'] = $item['producto_id'] ?? Variante::find($item['id'])?->producto_id;
                } elseif ($tipo === 'promocion') {
                    $dd['promocion_id'] = $item['id'];
                }

                VentaDetalle::create($dd);
                $costoTotalVenta += $calc['costoTotal'];
            }

            $venta->update(['costo_total' => round($costoTotalVenta, 2)]);

            // ── VentaPagos + Transacciones ────────────────────────────────────

            $comprobante  = $serie->serie . '-' . $correlativo;
            $conceptoCont = $conceptoPrefix !== ''
                ? $conceptoPrefix . ' · ' . $comprobante
                : 'Venta ' . $comprobante;
            $conceptoCred = 'Crédito ' . $comprobante;

            foreach ($pagosContado as $pago) {
                VentaPago::create([
                    'venta_id'       => $venta->id,
                    'sesion_caja_id' => $sesionCaja->id,
                    'metodo_pago_id' => $pago['metodo_pago_id'],
                    'monto'          => $pago['monto'],
                    'referencia'     => $pago['referencia'] ?: null,
                ]);
                Transaccion::create([
                    'empresa_id'           => $empresaId,
                    'sesion_caja_id'       => $sesionCaja->id,
                    'transaccionable_type' => Venta::class,
                    'transaccionable_id'   => $venta->id,
                    'tipo'                 => TipoMovimiento::Ingreso,
                    'concepto'             => $conceptoCont,
                    'monto'                => $pago['monto'],
                    'metodo_pago_id'       => $pago['metodo_pago_id'],
                    'estado'               => EstadoMovimiento::Aprobado,
                    'fecha'                => now(),
                ]);
            }

            foreach ($pagosCredito as $pago) {
                VentaPago::create([
                    'venta_id'       => $venta->id,
                    'sesion_caja_id' => $sesionCaja->id,
                    'metodo_pago_id' => $pago['metodo_pago_id'],
                    'monto'          => $pago['monto'],
                    'referencia'     => $pago['referencia'] ?: null,
                ]);
                Transaccion::create([
                    'empresa_id'           => $empresaId,
                    'sesion_caja_id'       => $sesionCaja->id,
                    'transaccionable_type' => Venta::class,
                    'transaccionable_id'   => $venta->id,
                    'tipo'                 => TipoMovimiento::Ingreso,
                    'concepto'             => $conceptoCred,
                    'monto'                => $pago['monto'],
                    'metodo_pago_id'       => $pago['metodo_pago_id'],
                    'estado'               => EstadoMovimiento::PorCobrar,
                    'fecha'                => now(),
                ]);
            }

            // ── Stock + Kardex ────────────────────────────────────────────────

            foreach ($items as $item) {
                $tipo     = $item['tipo'];
                $cantidad = (float) $item['cantidad'];

                if ($tipo === 'producto') {
                    $this->reducirStockProducto(
                        $empresaId, $item['id'], $item['nombre'],
                        $cantidad, (float) $item['precio'], $venta, $comprobante, $stockYaReservado
                    );
                } elseif ($tipo === 'variante') {
                    $this->reducirStockVariante(
                        $empresaId, $item['id'], $item['nombre'],
                        $cantidad, (float) $item['precio'], $venta, $comprobante, $stockYaReservado
                    );
                } elseif ($tipo === 'promocion') {
                    $this->procesarPromocion(
                        $empresaId, $item['id'], $item['nombre'],
                        $cantidad, $venta, $comprobante, $stockYaReservado
                    );
                }
            }
        });

        return $venta;
    }

    // ── Helpers de stock ─────────────────────────────────────────────────────────

    private function reducirStockProducto(
        int $empresaId, int $productoId, string $nombre,
        float $cantidad, float $precio, Venta $venta, string $comprobante,
        bool $stockYaReservado = false
    ): void {
        $producto = Producto::with('unidadMedida')->find($productoId);
        if (! $producto?->control_de_stock) return;

        $inv = Inventario::where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->whereNull('variante_id')
            ->lockForUpdate()->first();

        if (! $inv) return;

        $stockAntes = (float) $inv->stock_real;

        if (! $producto->venta_sin_stock && $stockAntes < $cantidad) {
            throw new \RuntimeException(
                "Stock insuficiente para \"{$nombre}\": disponible {$stockAntes}, solicitado {$cantidad}."
            );
        }

        $stockDespues = $producto->venta_sin_stock
            ? $stockAntes - $cantidad
            : max(0, $stockAntes - $cantidad);

        $update = ['stock_real' => $stockDespues];
        if (! $stockYaReservado) {
            $update['stock_reserva'] = max(0, (float) $inv->stock_reserva - ($stockAntes - $stockDespues));
        }
        $inv->update($update);

        $this->kardex->registrar([
            'empresa_id'        => $empresaId,
            'user_id'           => auth()->id(),
            'movible'           => $venta,
            'producto_id'       => $productoId,
            'variante_id'       => null,
            'producto_nombre'   => $nombre,
            'tipo'              => 'salida',
            'concepto'          => $comprobante,
            'cantidad'          => $cantidad,
            'unidad'            => $producto->unidadMedida?->nombre ?? 'unidad',
            'factor_conversion' => 1,
            'cantidad_base'     => $cantidad,
            'precio_unitario'   => $precio,
            'precio_total'      => $precio * $cantidad,
            'stock_antes'       => $stockAntes,
            'stock_despues'     => $stockDespues,
        ]);
    }

    private function reducirStockVariante(
        int $empresaId, int $varianteId, string $nombre,
        float $cantidad, float $precio, Venta $venta, string $comprobante,
        bool $stockYaReservado = false
    ): void {
        $variante = Variante::find($varianteId);
        if (! $variante) return;

        $producto = Producto::with('unidadMedida')->find($variante->producto_id);
        if (! $producto?->control_de_stock) return;

        $inv = Inventario::where('empresa_id', $empresaId)
            ->where('producto_id', $variante->producto_id)
            ->where('variante_id', $varianteId)
            ->lockForUpdate()->first();

        if (! $inv) return;

        $stockAntes = (float) $inv->stock_real;

        if (! $producto->venta_sin_stock && $stockAntes < $cantidad) {
            throw new \RuntimeException(
                "Stock insuficiente para \"{$nombre}\": disponible {$stockAntes}, solicitado {$cantidad}."
            );
        }

        $stockDespues = $producto->venta_sin_stock
            ? $stockAntes - $cantidad
            : max(0, $stockAntes - $cantidad);

        $update = ['stock_real' => $stockDespues];
        if (! $stockYaReservado) {
            $update['stock_reserva'] = max(0, (float) $inv->stock_reserva - ($stockAntes - $stockDespues));
        }
        $inv->update($update);

        $this->kardex->registrar([
            'empresa_id'        => $empresaId,
            'user_id'           => auth()->id(),
            'movible'           => $venta,
            'producto_id'       => $variante->producto_id,
            'variante_id'       => $varianteId,
            'producto_nombre'   => $nombre,
            'tipo'              => 'salida',
            'concepto'          => $comprobante,
            'cantidad'          => $cantidad,
            'unidad'            => $producto->unidadMedida?->nombre ?? 'unidad',
            'factor_conversion' => 1,
            'cantidad_base'     => $cantidad,
            'precio_unitario'   => $precio,
            'precio_total'      => $precio * $cantidad,
            'stock_antes'       => $stockAntes,
            'stock_despues'     => $stockDespues,
        ]);
    }

    private function procesarPromocion(
        int $empresaId, int $promocionId, string $nombre,
        float $cantidad, Venta $venta, string $comprobante,
        bool $stockYaReservado = false
    ): void {
        Promocion::where('id', $promocionId)->increment('usos_actuales', (int) $cantidad);

        $promo = Promocion::with([
            'detalles.producto.unidadMedida',
            'detalles.variante.producto.unidadMedida',
        ])->find($promocionId);

        if (! $promo) return;

        foreach ($promo->detalles as $detalle) {
            $cantidadDetalle = $cantidad * (float) $detalle->cantidad;

            if ($detalle->variante_id) {
                $varianteDetalle = $detalle->variante;
                $prodDetalle     = $varianteDetalle?->producto;

                if ($prodDetalle?->control_de_stock) {
                    $inv = Inventario::where('empresa_id', $empresaId)
                        ->where('variante_id', $detalle->variante_id)
                        ->lockForUpdate()->first();

                    if ($inv) {
                        $stockAntes = (float) $inv->stock_real;
                        if (! ($prodDetalle->venta_sin_stock ?? false) && $stockAntes < $cantidadDetalle) {
                            throw new \RuntimeException(
                                "Stock insuficiente en combo \"{$nombre}\": disponible {$stockAntes}, solicitado {$cantidadDetalle}."
                            );
                        }
                        $stockDespues = ($prodDetalle->venta_sin_stock ?? false)
                            ? $stockAntes - $cantidadDetalle
                            : max(0, $stockAntes - $cantidadDetalle);
                        $invUpdate = ['stock_real' => $stockDespues];
                        if (! $stockYaReservado) {
                            $invUpdate['stock_reserva'] = max(0, (float) $inv->stock_reserva - ($stockAntes - $stockDespues));
                        }
                        $inv->update($invUpdate);
                        $this->kardex->registrar([
                            'empresa_id'        => $empresaId,
                            'user_id'           => auth()->id(),
                            'movible'           => $venta,
                            'producto_id'       => $varianteDetalle->producto_id,
                            'variante_id'       => $detalle->variante_id,
                            'tipo'              => 'salida',
                            'concepto'          => $comprobante,
                            'notas'             => "Promo: {$nombre}",
                            'cantidad'          => $cantidadDetalle,
                            'unidad'            => $prodDetalle?->unidadMedida?->nombre ?? 'unidad',
                            'factor_conversion' => 1,
                            'cantidad_base'     => $cantidadDetalle,
                            'stock_antes'       => $stockAntes,
                            'stock_despues'     => $stockDespues,
                        ]);
                    }
                }
            } elseif ($detalle->producto_id) {
                $prodDetalle = $detalle->producto;

                if ($prodDetalle?->control_de_stock) {
                    $inv = Inventario::where('empresa_id', $empresaId)
                        ->where('producto_id', $detalle->producto_id)
                        ->whereNull('variante_id')
                        ->lockForUpdate()->first();

                    if ($inv) {
                        $stockAntes = (float) $inv->stock_real;
                        if (! ($prodDetalle->venta_sin_stock ?? false) && $stockAntes < $cantidadDetalle) {
                            throw new \RuntimeException(
                                "Stock insuficiente en combo \"{$nombre}\": disponible {$stockAntes}, solicitado {$cantidadDetalle}."
                            );
                        }
                        $stockDespues = ($prodDetalle->venta_sin_stock ?? false)
                            ? $stockAntes - $cantidadDetalle
                            : max(0, $stockAntes - $cantidadDetalle);
                        $invUpdate = ['stock_real' => $stockDespues];
                        if (! $stockYaReservado) {
                            $invUpdate['stock_reserva'] = max(0, (float) $inv->stock_reserva - ($stockAntes - $stockDespues));
                        }
                        $inv->update($invUpdate);
                        $this->kardex->registrar([
                            'empresa_id'        => $empresaId,
                            'user_id'           => auth()->id(),
                            'movible'           => $venta,
                            'producto_id'       => $detalle->producto_id,
                            'variante_id'       => null,
                            'tipo'              => 'salida',
                            'concepto'          => $comprobante,
                            'notas'             => "Promo: {$nombre}",
                            'cantidad'          => $cantidadDetalle,
                            'unidad'            => $prodDetalle?->unidadMedida?->nombre ?? 'unidad',
                            'factor_conversion' => 1,
                            'cantidad_base'     => $cantidadDetalle,
                            'stock_antes'       => $stockAntes,
                            'stock_despues'     => $stockDespues,
                        ]);
                    }
                }
            }
        }
    }
}
