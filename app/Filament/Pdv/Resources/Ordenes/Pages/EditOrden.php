<?php

namespace App\Filament\Pdv\Resources\Ordenes\Pages;

use App\Enums\EstadoOrden;
use App\Enums\EstadoVenta;
use App\Enums\TipoPago;
use App\Filament\Pdv\Resources\Ordenes\Concerns\ValidaStockOrden;
use App\Filament\Pdv\Resources\Ordenes\OrdenResource;
use App\Models\Inventario;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\Serie;
use App\Models\Variante;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Services\EtiquetaStockService;
use App\Services\KardexService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditOrden extends EditRecord
{
    use ValidaStockOrden;

    protected static string $resource = OrdenResource::class;

    /** Snapshot de cantidades antes del guardado, para calcular deltas en afterSave. */
    protected array $oldDetalleQtys = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function beforeSave(): void
    {
        if ($this->record->estado !== EstadoOrden::PendientePago) {
            return;
        }

        $this->oldDetalleQtys = $this->record->detalles()
            ->get(['id', 'tipo_item', 'producto_id', 'variante_id', 'promocion_id', 'cantidad'])
            ->keyBy('id')
            ->map(fn($d) => [
                'tipo_item'    => $d->tipo_item,
                'producto_id'  => $d->producto_id,
                'variante_id'  => $d->variante_id,
                'promocion_id' => $d->promocion_id,
                'cantidad'     => (float) $d->cantidad,
            ])
            ->all();

        $detallesForm = array_values($this->data['detalles'] ?? []);

        if (empty($detallesForm)) {
            return;
        }

        $errores = $this->validarStockDetalles(
            $detallesForm,
            array_values($this->oldDetalleQtys),
            $this->record->empresa_id
        );

        if (! empty($errores)) {
            foreach ($errores as $msg) {
                Notification::make()->danger()->title('Stock insuficiente')->body($msg)->persistent()->send();
            }
            $this->halt();
        }
    }

    protected function afterSave(): void
    {
        if (empty($this->oldDetalleQtys)) {
            return;
        }

        $empresaId = $this->record->empresa_id;
        $productosAfectados = [];

        $newDetalles = $this->record->detalles()
            ->get(['id', 'tipo_item', 'producto_id', 'variante_id', 'promocion_id', 'cantidad']);

        foreach ($newDetalles as $det) {
            $newQty = (float) $det->cantidad;
            $id     = $det->id;

            if (isset($this->oldDetalleQtys[$id])) {
                $oldQty = $this->oldDetalleQtys[$id]['cantidad'];
                $delta  = $oldQty - $newQty;
                if (abs($delta) < 0.001) continue;
            } else {
                $delta = -$newQty;
            }

            $this->aplicarDeltaStockReserva($det, $delta, $empresaId, $productosAfectados);
        }

        // Ítems eliminados en la edición → liberar su stock reservado
        $newIds = $newDetalles->pluck('id')->all();
        foreach ($this->oldDetalleQtys as $id => $old) {
            if (in_array($id, $newIds)) continue;
            $this->aplicarDeltaStockReserva((object) $old, $old['cantidad'], $empresaId, $productosAfectados);
        }

        if ($productosAfectados) {
            $service = app(EtiquetaStockService::class);
            foreach (array_unique($productosAfectados) as $productoId) {
                $service->sincronizar($productoId, $empresaId);
            }
        }

        $this->oldDetalleQtys = [];
    }

    /**
     * Ajusta stock_reserva para un detalle dado un delta:
     *   delta > 0 → unidades liberadas (qty de orden bajó o ítem eliminado)
     *   delta < 0 → unidades adicionales reservadas (qty subió o ítem nuevo)
     */
    private function aplicarDeltaStockReserva(object $detalle, float $delta, int $empresaId, array &$productosAfectados): void
    {
        if (abs($delta) < 0.001) return;

        if ($detalle->promocion_id) {
            $promo = Promocion::with(['detalles.producto', 'detalles.variante.producto'])
                ->find($detalle->promocion_id);
            if (! $promo) return;

            foreach ($promo->detalles as $pd) {
                $cantDet = $delta * (float) $pd->cantidad;

                if ($pd->variante_id && $pd->variante?->producto?->control_de_stock) {
                    $expr = $pd->variante->producto->venta_sin_stock
                        ? "LEAST(stock_real, stock_reserva + {$cantDet})"
                        : "LEAST(stock_real, GREATEST(0, stock_reserva + {$cantDet}))";
                    DB::table('inventarios')
                        ->where('empresa_id', $empresaId)
                        ->where('variante_id', $pd->variante_id)
                        ->update(['stock_reserva' => DB::raw($expr)]);
                    if ($pd->variante->producto_id) {
                        $productosAfectados[] = $pd->variante->producto_id;
                    }
                } elseif ($pd->producto_id && $pd->producto?->control_de_stock) {
                    $expr = $pd->producto->venta_sin_stock
                        ? "LEAST(stock_real, stock_reserva + {$cantDet})"
                        : "LEAST(stock_real, GREATEST(0, stock_reserva + {$cantDet}))";
                    DB::table('inventarios')
                        ->where('empresa_id', $empresaId)
                        ->where('producto_id', $pd->producto_id)
                        ->whereNull('variante_id')
                        ->update(['stock_reserva' => DB::raw($expr)]);
                    $productosAfectados[] = $pd->producto_id;
                }
            }

        } elseif ($detalle->variante_id) {
            $variante = Variante::with('producto')->find($detalle->variante_id);
            if (! $variante?->producto?->control_de_stock) return;

            $expr = $variante->producto->venta_sin_stock
                ? "LEAST(stock_real, stock_reserva + {$delta})"
                : "LEAST(stock_real, GREATEST(0, stock_reserva + {$delta}))";
            DB::table('inventarios')
                ->where('empresa_id', $empresaId)
                ->where('variante_id', $detalle->variante_id)
                ->update(['stock_reserva' => DB::raw($expr)]);
            $productosAfectados[] = $variante->producto_id;

        } elseif ($detalle->producto_id) {
            $producto = Producto::find($detalle->producto_id);
            if (! $producto?->control_de_stock) return;

            $expr = $producto->venta_sin_stock
                ? "LEAST(stock_real, stock_reserva + {$delta})"
                : "LEAST(stock_real, GREATEST(0, stock_reserva + {$delta}))";
            DB::table('inventarios')
                ->where('empresa_id', $empresaId)
                ->where('producto_id', $detalle->producto_id)
                ->whereNull('variante_id')
                ->update(['stock_reserva' => DB::raw($expr)]);
            $productosAfectados[] = $detalle->producto_id;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmarPago')
                ->label('Confirmar Pago')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn() => $this->record->estado === EstadoOrden::PendientePago)
                ->modalHeading('Confirmar pago — generar venta')
                ->modalDescription('Registra los pagos recibidos y genera la venta vinculada a esta orden.')
                ->modalWidth('2xl')
                ->fillForm(fn(): array => [
                    'pagos' => [
                        [
                            'metodo_pago_id' => $this->record->metodo_pago_id,
                            'monto'          => (string) $this->record->total,
                            'referencia'     => null,
                        ],
                    ],
                    'listo_para_despachar' => true,
                ])
                ->form([
                    Select::make('serie_id')
                        ->label('Serie del comprobante')
                        ->options(function (): array {
                            return Serie::where('empresa_id', Filament::getTenant()->id)
                                ->where('estado', true)
                                ->get()
                                ->mapWithKeys(fn(Serie $s) => [
                                    $s->id => "{$s->serie} — {$s->tipo->getLabel()}",
                                ])
                                ->all();
                        })
                        ->required()
                        ->native(false),

                    Repeater::make('pagos')
                        ->label('Pagos recibidos')
                        ->minItems(1)
                        ->schema([
                            Select::make('metodo_pago_id')
                                ->label('Método de pago')
                                ->options(function (): array {
                                    return MetodoPago::where('empresa_id', Filament::getTenant()->id)
                                        ->where('estado', 'activo')
                                        ->orderBy('nombre')
                                        ->get()
                                        ->mapWithKeys(fn(MetodoPago $m) => [
                                            $m->id => $m->nombre,
                                        ])
                                        ->all();
                                })
                                ->required()
                                ->native(false),

                            TextInput::make('monto')
                                ->label('Monto')
                                ->numeric()
                                ->prefix('S/')
                                ->required()
                                ->minValue(0.01),

                            TextInput::make('referencia')
                                ->label('N° operación / referencia')
                                ->nullable()
                                ->maxLength(100),
                        ]),

                    Toggle::make('listo_para_despachar')
                        ->label('Por entregar')
                        ->helperText('Aparecerá en la página de Despacho para gestionar el envío.')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    DB::transaction(function () use ($data): void {
                        $orden   = $this->record->load('detalles');
                        $empresa = Filament::getTenant();

                        $serie       = Serie::lockForUpdate()->findOrFail($data['serie_id']);
                        $nuevoNumero = $serie->numero + 1;
                        $serie->update(['numero' => $nuevoNumero]);
                        $correlativo = str_pad($nuevoNumero, 8, '0', STR_PAD_LEFT);

                        $montoPagado = collect($data['pagos'])->sum('monto');
                        $total       = (float) $orden->total;
                        $saldo       = round($total - $montoPagado, 2);

                        $costoTotalOrden = $orden->detalles->sum(fn($d) => (float) $d->costo_total);

                        $venta = Venta::create([
                            'empresa_id'       => $empresa->id,
                            'vendedor_id'      => auth()->id(),
                            'sesion_caja_id'   => null,
                            'cliente_id'       => $orden->cliente_id,
                            'tipo'             => 'web',
                            'cliente_nombre'   => $orden->cliente_nombre,
                            'cliente_tipo_doc' => $orden->cliente_tipo_doc,
                            'cliente_num_doc'  => $orden->cliente_num_doc,
                            'serie_id'         => $serie->id,
                            'correlativo'      => $correlativo,
                            'fecha_emision'    => now(),
                            'tipo_pago'        => TipoPago::Contado,
                            'op_gravadas'      => $orden->subtotal,
                            'op_exoneradas'    => 0,
                            'op_inafectas'     => 0,
                            'descuento_total'  => $orden->descuento_total,
                            'igv'              => $orden->igv,
                            'total'            => $orden->total,
                            'costo_total'      => $costoTotalOrden,
                            'monto_pagado'     => $montoPagado,
                            'saldo_pendiente'  => max(0, $saldo),
                            'estado_pago'      => $saldo <= 0 ? 'pagado' : 'parcial',
                            'estado'           => EstadoVenta::Completada,
                            'estado_despacho'  => ($data['listo_para_despachar'] ?? false)
                                                    ? 'pendiente_envio'
                                                    : null,
                            'notas'            => $orden->notas,
                        ]);

                        foreach ($orden->detalles as $detalle) {
                            $costoUnitario = (float) $detalle->costo_unitario;
                            $calc = VentaDetalle::calcular(
                                (float) $detalle->cantidad,
                                (float) $detalle->precio_unitario,
                                $costoUnitario,
                                (float) $detalle->descuento,
                            );

                            VentaDetalle::create([
                                'venta_id'        => $venta->id,
                                'tipo_item'       => $detalle->tipo_item,
                                'producto_id'     => $detalle->producto_id,
                                'variante_id'     => $detalle->variante_id,
                                'promocion_id'    => $detalle->promocion_id,
                                'descripcion'     => $detalle->descripcion,
                                'cantidad'        => $detalle->cantidad,
                                'precio_unitario' => $detalle->precio_unitario,
                                'valor_unitario'  => $calc['valorUnitario'],
                                'costo_unitario'  => $costoUnitario,
                                'descuento'       => $detalle->descuento,
                                'subtotal'        => $calc['subtotal'],
                                'valor_total'     => $calc['valorTotal'],
                                'igv'             => $calc['igv'],
                                'total'           => $calc['total'],
                                'costo_total'     => $calc['costoTotal'],
                            ]);
                        }

                        foreach ($data['pagos'] as $pago) {
                            VentaPago::create([
                                'venta_id'       => $venta->id,
                                'sesion_caja_id' => null,
                                'metodo_pago_id' => $pago['metodo_pago_id'],
                                'tipo'           => 'web',
                                'monto'          => $pago['monto'],
                                'referencia'     => $pago['referencia'] ?? null,
                            ]);
                        }

                        $orden->update([
                            'venta_id' => $venta->id,
                            'estado'   => EstadoOrden::PagoConfirmado,
                        ]);

                        $this->descontarStockReal($orden, $empresa->id, $venta, "{$serie->serie}-{$correlativo}");
                    });

                    Notification::make()
                        ->success()
                        ->title('Pago confirmado')
                        ->body('La venta fue generada y vinculada a la orden.')
                        ->send();

                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Action::make('cancelar')
                ->label('Cancelar orden')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('¿Cancelar esta orden?')
                ->modalDescription('La orden será marcada como cancelada. Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, cancelar')
                ->visible(fn() => $this->record->estado === EstadoOrden::PendientePago)
                ->action(function (): void {
                    $this->record->restaurarStockReserva();
                    $this->record->update(['estado' => EstadoOrden::Cancelada]);

                    Notification::make()
                        ->warning()
                        ->title('Orden ' . $this->record->codigo . ' cancelada')
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),
        ];
    }

    // Descuenta stock_real y registra kardex al confirmar pago.
    // stock_reserva NO se toca aquí (ya fue decrementado al crear la orden web).
    private function descontarStockReal($orden, int $empresaId, Venta $venta, string $concepto): void
    {
        $kardex = app(KardexService::class);

        foreach ($orden->detalles as $detalle) {
            $cantidad = (float) $detalle->cantidad;
            $precio   = (float) $detalle->precio_unitario;

            // ── Promoción ─────────────────────────────────────────
            if ($detalle->promocion_id) {
                $promo = Promocion::with([
                    'detalles.producto.unidadMedida',
                    'detalles.variante.producto.unidadMedida',
                ])->find($detalle->promocion_id);

                if (! $promo) continue;

                foreach ($promo->detalles as $pd) {
                    $cantDet = $cantidad * (float) $pd->cantidad;

                    if ($pd->variante_id) {
                        $var  = $pd->variante;
                        $prod = $var?->producto;
                        if (! $prod?->control_de_stock) continue;
                        $inv = Inventario::where('empresa_id', $empresaId)
                            ->where('variante_id', $pd->variante_id)
                            ->lockForUpdate()->first();
                        if (! $inv) continue;
                        $antes   = (float) $inv->stock_real;
                        $despues = max(0, $antes - $cantDet);
                        $inv->update(['stock_real' => $despues]);
                        $kardex->registrar([
                            'empresa_id'        => $empresaId,
                            'movible'           => $venta,
                            'producto_id'       => $var->producto_id,
                            'variante_id'       => $pd->variante_id,
                            'tipo'              => 'salida',
                            'concepto'          => $concepto,
                            'notas'             => "Promo: {$promo->nombre}",
                            'cantidad'          => $cantDet,
                            'unidad'            => $prod->unidadMedida?->nombre ?? 'unidad',
                            'factor_conversion' => 1,
                            'cantidad_base'     => $cantDet,
                            'precio_unitario'   => $precio,
                            'stock_antes'       => $antes,
                            'stock_despues'     => $despues,
                        ]);
                    } elseif ($pd->producto_id) {
                        $prod = $pd->producto;
                        if (! $prod?->control_de_stock) continue;
                        $inv = Inventario::where('empresa_id', $empresaId)
                            ->where('producto_id', $pd->producto_id)
                            ->whereNull('variante_id')
                            ->lockForUpdate()->first();
                        if (! $inv) continue;
                        $antes   = (float) $inv->stock_real;
                        $despues = max(0, $antes - $cantDet);
                        $inv->update(['stock_real' => $despues]);
                        $kardex->registrar([
                            'empresa_id'        => $empresaId,
                            'movible'           => $venta,
                            'producto_id'       => $pd->producto_id,
                            'variante_id'       => null,
                            'tipo'              => 'salida',
                            'concepto'          => $concepto,
                            'notas'             => "Promo: {$promo->nombre}",
                            'cantidad'          => $cantDet,
                            'unidad'            => $prod->unidadMedida?->nombre ?? 'unidad',
                            'factor_conversion' => 1,
                            'cantidad_base'     => $cantDet,
                            'precio_unitario'   => $precio,
                            'stock_antes'       => $antes,
                            'stock_despues'     => $despues,
                        ]);
                    }
                }

            // ── Variante ──────────────────────────────────────────
            } elseif ($detalle->variante_id) {
                $variante = Variante::with('producto.unidadMedida')->find($detalle->variante_id);
                if (! $variante?->producto?->control_de_stock) continue;
                $inv = Inventario::where('empresa_id', $empresaId)
                    ->where('producto_id', $variante->producto_id)
                    ->where('variante_id', $detalle->variante_id)
                    ->lockForUpdate()->first();
                if (! $inv) continue;
                $antes   = (float) $inv->stock_real;
                $despues = max(0, $antes - $cantidad);
                $inv->update(['stock_real' => $despues]);
                $kardex->registrar([
                    'empresa_id'        => $empresaId,
                    'movible'           => $venta,
                    'producto_id'       => $variante->producto_id,
                    'variante_id'       => $detalle->variante_id,
                    'producto_nombre'   => $detalle->descripcion,
                    'tipo'              => 'salida',
                    'concepto'          => $concepto,
                    'cantidad'          => $cantidad,
                    'unidad'            => $variante->producto->unidadMedida?->nombre ?? 'unidad',
                    'factor_conversion' => 1,
                    'cantidad_base'     => $cantidad,
                    'precio_unitario'   => $precio,
                    'precio_total'      => $precio * $cantidad,
                    'stock_antes'       => $antes,
                    'stock_despues'     => $despues,
                ]);

            // ── Producto simple ────────────────────────────────────
            } elseif ($detalle->producto_id) {
                $producto = Producto::with('unidadMedida')->find($detalle->producto_id);
                if (! $producto?->control_de_stock) continue;
                $inv = Inventario::where('empresa_id', $empresaId)
                    ->where('producto_id', $detalle->producto_id)
                    ->whereNull('variante_id')
                    ->lockForUpdate()->first();
                if (! $inv) continue;
                $antes   = (float) $inv->stock_real;
                $despues = max(0, $antes - $cantidad);
                $inv->update(['stock_real' => $despues]);
                $kardex->registrar([
                    'empresa_id'        => $empresaId,
                    'movible'           => $venta,
                    'producto_id'       => $detalle->producto_id,
                    'variante_id'       => null,
                    'producto_nombre'   => $detalle->descripcion,
                    'tipo'              => 'salida',
                    'concepto'          => $concepto,
                    'cantidad'          => $cantidad,
                    'unidad'            => $producto->unidadMedida?->nombre ?? 'unidad',
                    'factor_conversion' => 1,
                    'cantidad_base'     => $cantidad,
                    'precio_unitario'   => $precio,
                    'precio_total'      => $precio * $cantidad,
                    'stock_antes'       => $antes,
                    'stock_despues'     => $despues,
                ]);
            }
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['ubicacion_cliente'] = $this->record->notas_internas;
        return $data;
    }
}
