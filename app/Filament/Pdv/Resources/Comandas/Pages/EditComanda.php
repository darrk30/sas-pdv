<?php

namespace App\Filament\Pdv\Resources\Comandas\Pages;

use App\Enums\EstadoMovimiento;
use App\Enums\EstadoOrden;
use App\Enums\EstadoSesion;
use App\Enums\EstadoVenta;
use App\Enums\TipoComprobante;
use App\Enums\TipoItem;
use App\Enums\TipoMovimiento;
use App\Enums\TipoPago;
use App\Events\VentaCompletada;
use App\Filament\Pdv\Pages\MapaMesasPage;
use App\Filament\Pdv\Resources\Comandas\ComandaResource;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MetodoPago;
use App\Models\OrdenDetalle;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\SesionCaja;
use App\Models\Transaccion;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Services\ImpresionDirectaService;
use App\Services\KardexService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EditComanda extends EditRecord
{
    protected static string $resource = ComandaResource::class;

    protected string $view = 'filament.pdv.pages.edit-comanda';

    // ── Estado reactivo ───────────────────────────────────────────────────────

    public string $busqueda      = '';
    public ?int   $categoriaId   = null;
    public array  $notasTemp     = [];
    public bool   $verOrden      = true;

    // ── Modal cobro ───────────────────────────────────────────────────────────

    public bool    $modalCobro        = false;
    public ?int    $cobroSerieId      = null;
    public ?int    $cobroMetodoPagoId = null;
    public string  $cobroMonto        = '';
    public string  $cobroReferencia   = '';
    public ?int    $cobroClienteId    = null;

    // ── Filament form vacío (no usamos el form estándar) ─────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    // ── Datos para la vista ───────────────────────────────────────────────────

    public function getComanda(): \App\Models\Orden
    {
        return $this->record->load(['detalles.producto', 'detalles.variante', 'mesa.piso']);
    }

    public function getCategorias(): Collection
    {
        return Categoria::where('empresa_id', Filament::getTenant()->id)
            ->whereHas('productos', fn ($q) => $q
                ->where('vendible', true)
                ->where('estado', 'activo')
            )
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    public function getProductos(): Collection
    {
        $empresaId = Filament::getTenant()->id;

        $query = Producto::where('empresa_id', $empresaId)
            ->where('vendible', true)
            ->where('estado', 'activo')
            ->orderBy('nombre')
            ->with('produccion');

        if ($this->busqueda !== '') {
            $query->where('nombre', 'like', '%' . $this->busqueda . '%');
        }

        if ($this->categoriaId !== null) {
            $query->where('categoria_id', $this->categoriaId);
        }

        return $query->get(['id', 'nombre', 'precio_venta', 'precio_costo', 'logo', 'categoria_id', 'produccion_id', 'empresa_id']);
    }

    // ── Acciones ─────────────────────────────────────────────────────────────

    public function agregarProducto(int $productoId): void
    {
        $orden   = $this->record;
        $empresa = Filament::getTenant();

        if ($orden->estado === EstadoOrden::PagoConfirmado) {
            Notification::make()->title('Comanda ya cobrada')->danger()->send();
            return;
        }

        $producto = Producto::findOrFail($productoId);

        // Variantes: notificar pero no bloquear (se añade el producto base)
        // TODO Etapa 4+: modal de selección de variante

        $igvRate = ($empresa->igv_porcentaje ?? 18) / 100;
        $precio  = (float) $producto->precio_venta;
        $costo   = (float) ($producto->precio_costo ?? 0);

        $existente = $orden->detalles()
            ->where('producto_id', $productoId)
            ->whereNull('variante_id')
            ->first();

        if ($existente) {
            $nuevaCantidad = (float) $existente->cantidad + 1;
            $calc = OrdenDetalle::calcular($nuevaCantidad, $precio, $costo, 0, $igvRate);
            $existente->update([
                'cantidad'       => $nuevaCantidad,
                'valor_unitario' => $calc['valorUnitario'],
                'subtotal'       => $calc['subtotal'],
                'igv'            => $calc['igv'],
                'total'          => $calc['total'],
                'costo_total'    => $calc['costoTotal'],
            ]);
        } else {
            $calc = OrdenDetalle::calcular(1, $precio, $costo, 0, $igvRate);
            $orden->detalles()->create([
                'tipo_item'       => TipoItem::Producto->value,
                'producto_id'     => $productoId,
                'descripcion'     => $producto->nombre,
                'cantidad'        => 1,
                'precio_unitario' => $precio,
                'valor_unitario'  => $calc['valorUnitario'],
                'costo_unitario'  => $costo,
                'descuento'       => 0,
                'subtotal'        => $calc['subtotal'],
                'igv'             => $calc['igv'],
                'total'           => $calc['total'],
                'costo_total'     => $calc['costoTotal'],
                'enviado_cocina'  => false,
            ]);
        }

        $orden->recalcularTotales();
        $this->record->refresh();
    }

    public function incrementar(int $detalleId): void
    {
        $detalle = OrdenDetalle::where('orden_id', $this->record->id)->findOrFail($detalleId);
        $empresa = Filament::getTenant();
        $igvRate = ($empresa->igv_porcentaje ?? 18) / 100;

        $nuevaCantidad = (float) $detalle->cantidad + 1;
        $calc = OrdenDetalle::calcular($nuevaCantidad, (float) $detalle->precio_unitario, (float) $detalle->costo_unitario, 0, $igvRate);
        $detalle->update([
            'cantidad'    => $nuevaCantidad,
            'valor_unitario' => $calc['valorUnitario'],
            'subtotal'    => $calc['subtotal'],
            'igv'         => $calc['igv'],
            'total'       => $calc['total'],
            'costo_total' => $calc['costoTotal'],
        ]);

        $this->record->recalcularTotales();
        $this->record->refresh();
    }

    public function decrementar(int $detalleId): void
    {
        $detalle = OrdenDetalle::where('orden_id', $this->record->id)->findOrFail($detalleId);

        if ((float) $detalle->cantidad <= 1) {
            $this->eliminarItem($detalleId);
            return;
        }

        $empresa = Filament::getTenant();
        $igvRate = ($empresa->igv_porcentaje ?? 18) / 100;

        $nuevaCantidad = (float) $detalle->cantidad - 1;
        $calc = OrdenDetalle::calcular($nuevaCantidad, (float) $detalle->precio_unitario, (float) $detalle->costo_unitario, 0, $igvRate);
        $detalle->update([
            'cantidad'    => $nuevaCantidad,
            'valor_unitario' => $calc['valorUnitario'],
            'subtotal'    => $calc['subtotal'],
            'igv'         => $calc['igv'],
            'total'       => $calc['total'],
            'costo_total' => $calc['costoTotal'],
        ]);

        $this->record->recalcularTotales();
        $this->record->refresh();
    }

    public function eliminarItem(int $detalleId): void
    {
        OrdenDetalle::where('orden_id', $this->record->id)->where('id', $detalleId)->delete();
        $this->record->recalcularTotales();
        $this->record->refresh();
    }

    public function guardarNota(int $detalleId): void
    {
        $nota = $this->notasTemp[$detalleId] ?? '';
        OrdenDetalle::where('orden_id', $this->record->id)
            ->where('id', $detalleId)
            ->update(['notas_item' => $nota ?: null]);
        $this->record->refresh();
    }

    public function enviarACocina(): void
    {
        $orden   = $this->record;
        $empresa = Filament::getTenant();

        if (! $orden->detalles()->where('enviado_cocina', false)->exists()) {
            Notification::make()->title('No hay ítems nuevos para enviar')->warning()->send();
            return;
        }

        app(ImpresionDirectaService::class)->imprimirComandaOrden($orden, $empresa);

        $orden->detalles()->where('enviado_cocina', false)->update(['enviado_cocina' => true]);

        $this->record->refresh();

        Notification::make()->title('Comanda enviada a cocina')->success()->send();
    }

    public function imprimirPreCuenta(): void
    {
        $orden   = $this->record;
        $empresa = Filament::getTenant();

        $imprimio = app(ImpresionDirectaService::class)->imprimirPreCuentaMesa($orden, $empresa);

        if ($imprimio) {
            Notification::make()->title('Pre-cuenta enviada a impresora')->success()->send();
        } else {
            Notification::make()
                ->title('Sin impresora configurada')
                ->body('Configura una impresora en el piso para imprimir pre-cuentas.')
                ->warning()
                ->send();
        }
    }

    public function cobrarMesa(): void
    {
        $this->abrirModalCobro();
    }

    public function getSeries(): Collection
    {
        return Serie::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', true)
            ->whereIn('tipo', [
                TipoComprobante::Ticket->value,
                TipoComprobante::Boleta->value,
                TipoComprobante::Factura->value,
            ])
            ->orderByRaw("FIELD(tipo, 'ticket', 'boleta', 'factura')")
            ->get(['id', 'serie', 'tipo', 'numero']);
    }

    public function getMetodosPago(): Collection
    {
        return MetodoPago::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', 'activo')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'requiere_referencia', 'condicion_pago']);
    }

    public function abrirModalCobro(): void
    {
        $orden = $this->record;

        if ($orden->detalles()->count() === 0) {
            Notification::make()->title('La comanda está vacía')->warning()->send();
            return;
        }

        // Pre-seleccionar primer comprobante disponible
        $primerasSerie = $this->getSeries()->first();
        $this->cobroSerieId      = $primerasSerie?->id;
        $this->cobroMetodoPagoId = $this->getMetodosPago()->first()?->id;
        $this->cobroMonto        = number_format((float) $orden->total, 2, '.', '');
        $this->cobroReferencia   = '';

        // Pre-seleccionar cliente general si existe
        $clienteGeneral = Cliente::where('empresa_id', Filament::getTenant()->id)
            ->where('numero_documento', '99999999')
            ->first();
        $this->cobroClienteId = $clienteGeneral?->id;

        $this->resetValidation(['cobroSerieId', 'cobroMetodoPagoId', 'cobroMonto']);
        $this->modalCobro = true;
    }

    public function cerrarModalCobro(): void
    {
        $this->modalCobro = false;
    }

    public function procesarCobro(): void
    {
        $this->validate([
            'cobroSerieId'      => 'required|integer',
            'cobroMetodoPagoId' => 'required|integer',
            'cobroMonto'        => 'required|numeric|min:0.01',
        ], [
            'cobroSerieId.required'      => 'Selecciona un comprobante.',
            'cobroMetodoPagoId.required' => 'Selecciona un método de pago.',
            'cobroMonto.required'        => 'Ingresa el monto.',
            'cobroMonto.min'             => 'El monto debe ser mayor a 0.',
        ]);

        $orden     = $this->record;
        $empresa   = Filament::getTenant();
        $empresaId = $empresa->id;

        $montoCobrado = (float) $this->cobroMonto;
        $totalOrden   = (float) $orden->total;

        if ($montoCobrado < $totalOrden - 0.01) {
            Notification::make()
                ->title('Monto insuficiente')
                ->body('El total es S/ ' . number_format($totalOrden, 2))
                ->warning()
                ->send();
            return;
        }

        $sesionActiva = SesionCaja::where('empresa_id', $empresaId)
            ->where('user_id', auth()->user()?->getAuthIdentifier())
            ->where('estado', EstadoSesion::Abierta->value)
            ->exists();

        if (! $sesionActiva) {
            Notification::make()
                ->title('Sin sesión de caja activa')
                ->body('Abre una sesión de caja antes de cobrar.')
                ->warning()
                ->send();
            return;
        }

        $venta   = null;
        $mesaNombre = $orden->mesa?->nombre ?? '—';

        try {
            DB::transaction(function () use ($orden, $empresa, $empresaId, $montoCobrado, &$venta) {
                $serie = Serie::lockForUpdate()->findOrFail($this->cobroSerieId);
                $nuevoNumero = $serie->numero + 1;
                $serie->update(['numero' => $nuevoNumero]);
                $correlativo = str_pad($nuevoNumero, 8, '0', STR_PAD_LEFT);

                $sesionCaja = SesionCaja::where('empresa_id', $empresaId)
                    ->where('user_id', auth()->user()?->getAuthIdentifier())
                    ->where('estado', EstadoSesion::Abierta->value)
                    ->latest()->lockForUpdate()->first();

                if (! $sesionCaja) {
                    throw new \RuntimeException('__SIN_SESION__');
                }

                $esTicket = $serie->tipo === TipoComprobante::Ticket;
                $tasaIgv  = $esTicket ? 0.0 : (($empresa->igv_porcentaje ?? 18) / 100);

                $orden->loadMissing(['detalles.producto.unidadMedida', 'detalles.variante.producto.unidadMedida', 'mesa']);

                // Recalcular totales con la tasa correcta del comprobante elegido
                $opGravadas  = 0.0;
                $igvTotal    = 0.0;
                $totalFinal  = 0.0;
                $costoTotalV = 0.0;

                foreach ($orden->detalles as $det) {
                    $calc = VentaDetalle::calcular(
                        cantidad: (float) $det->cantidad,
                        precioUnitario: (float) $det->precio_unitario,
                        costoUnitario: (float) ($det->costo_unitario ?? 0),
                        tasaIgv: $tasaIgv,
                    );
                    $opGravadas  += $esTicket ? 0.0 : $calc['subtotal'];
                    $igvTotal    += $calc['igv'];
                    $totalFinal  += $calc['total'];
                    $costoTotalV += $calc['costoTotal'];
                }

                $totalFinal  = round($totalFinal, 2);
                $montoVenta  = min($montoCobrado, $totalFinal);

                $clienteNombreFinal  = null;
                $clienteNumDocFinal  = '00000000';
                $clienteTipoDocFinal = 'dni';
                if ($this->cobroClienteId) {
                    $cli = Cliente::find($this->cobroClienteId);
                    $clienteNombreFinal  = $cli?->nombre_completo;
                    $clienteNumDocFinal  = $cli?->numero_documento ?? '00000000';
                    $clienteTipoDocFinal = $cli?->tipo_documento?->value ?? 'dni';
                }

                $venta = Venta::create([
                    'empresa_id'       => $empresaId,
                    'sesion_caja_id'   => $sesionCaja->id,
                    'cliente_id'       => $this->cobroClienteId,
                    'cliente_nombre'   => $clienteNombreFinal ?? ($orden->cliente_nombre ?? 'Cliente General'),
                    'cliente_tipo_doc' => $clienteTipoDocFinal,
                    'cliente_num_doc'  => $clienteNumDocFinal,
                    'serie_id'         => $this->cobroSerieId,
                    'correlativo'      => $correlativo,
                    'tipo_pago'        => TipoPago::Contado,
                    'op_gravadas'      => round($opGravadas, 2),
                    'op_exoneradas'    => 0,
                    'op_inafectas'     => $esTicket ? $totalFinal : 0,
                    'descuento_total'  => 0,
                    'igv'              => round($igvTotal, 2),
                    'total'            => $totalFinal,
                    'costo_total'      => round($costoTotalV, 2),
                    'monto_pagado'     => $montoVenta,
                    'saldo_pendiente'  => 0,
                    'estado_pago'      => 'pagado',
                    'estado'           => EstadoVenta::Completada,
                ]);

                // VentaDetalle desde OrdenDetalle
                foreach ($orden->detalles as $det) {
                    $calc = VentaDetalle::calcular(
                        cantidad: (float) $det->cantidad,
                        precioUnitario: (float) $det->precio_unitario,
                        costoUnitario: (float) ($det->costo_unitario ?? 0),
                        tasaIgv: $tasaIgv,
                    );

                    VentaDetalle::create([
                        'venta_id'        => $venta->id,
                        'tipo_item'       => $det->tipo_item ?? TipoItem::Producto,
                        'producto_id'     => $det->producto_id,
                        'variante_id'     => $det->variante_id,
                        'descripcion'     => $det->descripcion,
                        'cantidad'        => $det->cantidad,
                        'precio_unitario' => $det->precio_unitario,
                        'valor_unitario'  => $calc['valorUnitario'],
                        'costo_unitario'  => $det->costo_unitario ?? 0,
                        'descuento'       => 0,
                        'subtotal'        => $calc['subtotal'],
                        'valor_total'     => $calc['valorTotal'],
                        'igv'             => $calc['igv'],
                        'total'           => $calc['total'],
                        'costo_total'     => $calc['costoTotal'],
                    ]);
                }

                // Movimiento de stock + kardex
                $kardex   = app(KardexService::class);
                $concepto = $serie->serie . '-' . $correlativo;

                foreach ($orden->detalles as $det) {
                    $cantidad = (float) $det->cantidad;

                    if ($det->variante_id) {
                        $variante = $det->variante?->producto?->control_de_stock ? $det->variante : null;
                        if ($variante) {
                            $inv = Inventario::where('empresa_id', $empresaId)
                                ->where('variante_id', $det->variante_id)
                                ->lockForUpdate()->first();
                            if ($inv) {
                                $antes    = (float) $inv->stock_real;
                                $despues  = max(0, $antes - $cantidad);
                                $inv->update([
                                    'stock_real'    => $despues,
                                    'stock_reserva' => max(0, (float) $inv->stock_reserva - ($antes - $despues)),
                                ]);
                                $kardex->registrar([
                                    'empresa_id'        => $empresaId,
                                    'user_id'           => auth()->user()?->getAuthIdentifier(),
                                    'movible'           => $venta,
                                    'producto_id'       => $variante->producto_id,
                                    'variante_id'       => $det->variante_id,
                                    'producto_nombre'   => $det->descripcion,
                                    'tipo'              => 'salida',
                                    'concepto'          => $concepto,
                                    'cantidad'          => $cantidad,
                                    'unidad'            => $det->variante->producto->unidadMedida?->nombre ?? 'unidad',
                                    'factor_conversion' => 1,
                                    'cantidad_base'     => $cantidad,
                                    'precio_unitario'   => (float) $det->precio_unitario,
                                    'precio_total'      => (float) $det->precio_unitario * $cantidad,
                                    'stock_antes'       => $antes,
                                    'stock_despues'     => $despues,
                                ]);
                            }
                        }
                    } elseif ($det->producto_id) {
                        $producto = $det->producto;
                        if ($producto?->control_de_stock) {
                            $inv = Inventario::where('empresa_id', $empresaId)
                                ->where('producto_id', $det->producto_id)
                                ->whereNull('variante_id')
                                ->lockForUpdate()->first();
                            if ($inv) {
                                $antes   = (float) $inv->stock_real;
                                $despues = max(0, $antes - $cantidad);
                                $inv->update([
                                    'stock_real'    => $despues,
                                    'stock_reserva' => max(0, (float) $inv->stock_reserva - ($antes - $despues)),
                                ]);
                                $kardex->registrar([
                                    'empresa_id'        => $empresaId,
                                    'user_id'           => auth()->user()?->getAuthIdentifier(),
                                    'movible'           => $venta,
                                    'producto_id'       => $det->producto_id,
                                    'variante_id'       => null,
                                    'producto_nombre'   => $det->descripcion,
                                    'tipo'              => 'salida',
                                    'concepto'          => $concepto,
                                    'cantidad'          => $cantidad,
                                    'unidad'            => $producto->unidadMedida?->nombre ?? 'unidad',
                                    'factor_conversion' => 1,
                                    'cantidad_base'     => $cantidad,
                                    'precio_unitario'   => (float) $det->precio_unitario,
                                    'precio_total'      => (float) $det->precio_unitario * $cantidad,
                                    'stock_antes'       => $antes,
                                    'stock_despues'     => $despues,
                                ]);
                            }
                        }
                    }
                }

                // Pago + transacción de caja
                VentaPago::create([
                    'venta_id'       => $venta->id,
                    'sesion_caja_id' => $sesionCaja->id,
                    'metodo_pago_id' => $this->cobroMetodoPagoId,
                    'monto'          => $montoVenta,
                    'referencia'     => $this->cobroReferencia ?: null,
                ]);

                Transaccion::create([
                    'empresa_id'           => $empresaId,
                    'sesion_caja_id'       => $sesionCaja->id,
                    'transaccionable_type' => Venta::class,
                    'transaccionable_id'   => $venta->id,
                    'tipo'                 => TipoMovimiento::Ingreso,
                    'concepto'             => 'Mesa ' . ($orden->mesa?->nombre ?? '—') . ' · ' . $serie->serie . '-' . $correlativo,
                    'monto'                => $montoVenta,
                    'metodo_pago_id'       => $this->cobroMetodoPagoId,
                    'estado'               => EstadoMovimiento::Aprobado,
                    'fecha'                => now(),
                ]);

                // Vincular orden y cerrar mesa
                $orden->update([
                    'venta_id' => $venta->id,
                    'estado'   => EstadoOrden::PagoConfirmado,
                ]);

                $orden->mesa?->marcarLibre();
            });
        } catch (\Exception $e) {
            if ($e->getMessage() === '__SIN_SESION__') {
                Notification::make()->title('Sin sesión de caja activa')->warning()->send();
                return;
            }
            Notification::make()
                ->title('Error al procesar el cobro')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        // Emisión electrónica (boleta / factura)
        if ($venta) {
            $venta->loadMissing('serie');
            if (in_array($venta->serie?->tipo, [TipoComprobante::Boleta, TipoComprobante::Factura])) {
                VentaCompletada::dispatch($venta);
            }
            try {
                app(ImpresionDirectaService::class)->imprimirComprobante($venta, $empresa);
            } catch (\Throwable) {}
        }

        $this->cerrarModalCobro();

        Notification::make()
            ->title('Cobro realizado ✓')
            ->body("Mesa {$mesaNombre} liberada correctamente.")
            ->success()
            ->send();

        $this->redirect(MapaMesasPage::getUrl(tenant: $empresa));
    }

    public function toggleOrden(): void
    {
        $this->verOrden = ! $this->verOrden;
    }

    // ── Helpers para la vista ─────────────────────────────────────────────────

    public function hayItemsSinEnviar(): bool
    {
        return $this->record->detalles()->where('enviado_cocina', false)->exists();
    }

    public function volverAlMapa(): void
    {
        $empresa = Filament::getTenant();
        $this->redirect(MapaMesasPage::getUrl(tenant: $empresa));
    }
}
