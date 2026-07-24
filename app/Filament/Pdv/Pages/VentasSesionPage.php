<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoMovimiento;
use App\Enums\EstadoOrden;
use App\Enums\EstadoSesion;
use App\Enums\EstadoSunat;
use App\Enums\EstadoVenta;
use Filament\Forms\Components\TextInput;
use App\Enums\TipoItem;
use App\Enums\TipoMovimiento;
use App\Models\Inventario;
use App\Models\Orden;
use App\Models\Promocion;
use App\Models\SesionCaja;
use App\Models\Transaccion;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Services\KardexService;
use App\Services\PdfVentaService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use App\Filament\Pdv\Concerns\HasAnulacionSunat;
use App\Filament\Pdv\Concerns\HasConvertirComprobante;
use App\Filament\Pdv\Concerns\HasEmisionNota;
use App\Filament\Pdv\Concerns\HasEnvioVentaDirecto;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use App\Filament\Pdv\Concerns\HasImpresionTicket;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class VentasSesionPage extends Page implements HasTable
{
    use InteractsWithTable;
    use HasAnulacionSunat;
    use HasConvertirComprobante;
    use HasEmisionNota;
    use HasEnvioVentaDirecto;
    use HasFullWidthPage;
    use HasImpresionTicket;

    protected string $view = 'filament.pdv.pages.ventas-sesion';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Ventas del Turno';
    protected static string|UnitEnum|null $navigationGroup = 'Punto de Venta';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Ventas del Turno';

    public static function canAccess(): bool { return Filament::getTenant()->tieneModulo('ventas_turno') && (auth()->user()?->can('caja.ventas_turno') ?? false); }


    // ── Filtros ───────────────────────────────────────────────────────────────
    public string $busqueda     = '';
    public string $filtroEstado = '';

    // ── Modal detalle ─────────────────────────────────────────────────────────
    public ?int $ventaModalId = null;

    // ── Modal anular ──────────────────────────────────────────────────────────
    public ?int  $ventaAnularId  = null;
    public bool  $modalAnular    = false;
    public bool  $revertirStock  = true;
    public string $motivoBaja    = 'Error en emisión';


    public function updatedBusqueda(): void     { }
    public function updatedFiltroEstado(): void { }

    public function limpiarFiltros(): void
    {
        $this->busqueda     = '';
        $this->filtroEstado = '';
    }

    // ── Sesión activa ─────────────────────────────────────────────────────────

    public function getSesionActiva(): ?SesionCaja
    {
        return SesionCaja::where('empresa_id', Filament::getTenant()->id)
            ->where('user_id', auth()->id())
            ->where('estado', EstadoSesion::Abierta->value)
            ->with('caja')
            ->latest()
            ->first();
    }

    // ── Tabla Filament ────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $sesion = $this->getSesionActiva();

                $q = Venta::where('empresa_id', Filament::getTenant()->id)
                    ->with(['serie', 'pagos.metodoPago'])
                    ->withCount([
                        'detalles',
                        'notas',
                        'detalles as cortesias_count' => fn($q) => $q->where('precio_unitario', 0),
                    ]);

                if ($sesion) {
                    $q->where('sesion_caja_id', $sesion->id);
                } else {
                    $q->whereRaw('0=1');
                }

                if ($this->busqueda !== '') {
                    $b = $this->busqueda;
                    $q->where(function ($sub) use ($b) {
                        $sub->where('cliente_nombre', 'like', "%{$b}%")
                            ->orWhere('cliente_num_doc', 'like', "%{$b}%")
                            ->orWhere('correlativo', 'like', "%{$b}%");
                    });
                }

                if ($this->filtroEstado !== '') {
                    $q->where('estado', $this->filtroEstado);
                }

                return $q;
            })
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('comprobante')
                    ->label('Comprobante')
                    ->state(fn (Venta $r): string => ($r->serie?->serie ?? '---') . '-' . str_pad((string) $r->correlativo, 8, '0', STR_PAD_LEFT))
                    ->weight('medium')
                    ->searchable(false)
                    ->sortable(false),

                TextColumn::make('cliente_nombre')
                    ->label('Cliente')
                    ->description(fn (Venta $r): string => strtoupper($r->cliente_tipo_doc) . ' ' . $r->cliente_num_doc)
                    ->searchable(false),

                TextColumn::make('detalles_count')
                    ->label('Ítems')
                    ->alignCenter()
                    ->sortable(false),

                TextColumn::make('cortesias_count')
                    ->label('Cortesía')
                    ->alignCenter()
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? 'Sí' : '—')
                    ->sortable(false),

                TextColumn::make('metodo')
                    ->label('Método')
                    ->state(fn (Venta $r): string => $r->pagos->map(fn ($p) => $p->metodoPago?->nombre)->filter()->unique()->implode(', ') ?: '—')
                    ->searchable(false)
                    ->sortable(false),

                TextColumn::make('op_gravadas')
                    ->label('Op. Gravada')
                    ->money('PEN')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('igv')
                    ->label('IGV')
                    ->money('PEN')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('descuento_total')
                    ->label('Descuento')
                    ->money('PEN')
                    ->alignEnd()
                    ->color('danger')
                    ->formatStateUsing(fn ($state): string => $state > 0 ? '- ' . number_format((float) $state, 2) : '—')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->alignEnd()
                    ->weight('semibold'),

                TextColumn::make('created_at')
                    ->label('Hora')
                    ->time('H:i')
                    ->alignCenter(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge(),

                TextColumn::make('estado_sunat')
                    ->label('SUNAT')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('notas_count')
                    ->label('NC')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? $state . ' NC' : '—')
                    ->tooltip('Notas de Crédito emitidas sobre esta venta')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sunat_descripcion')
                    ->label('Desc. SUNAT')
                    ->wrap()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('ver')
                        ->label('Ver detalle')
                        ->icon('heroicon-o-eye')
                        ->action(fn (Venta $record) => $this->abrirDetalle($record->id)),

                    $this->buildImprimirTicketAction(),

                    Action::make('pdf')
                        ->label('Descargar PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Venta $record) {
                            $record->loadMissing('empresa');
                            $service = app(PdfVentaService::class);
                            $pdf     = $service->generar($record, $record->empresa);
                            $nombre  = $service->nombreArchivo($record);

                            return response()->streamDownload(
                                fn () => print($pdf->output()),
                                $nombre,
                                ['Content-Type' => 'application/pdf'],
                            );
                        }),

                    Action::make('enviarSunat')
                        ->label('Enviar a SUNAT')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->visible(fn (Venta $record): bool => $record->estado_sunat === \App\Enums\EstadoSunat::PorEnviar)
                        ->requiresConfirmation()
                        ->modalHeading('Enviar comprobante a SUNAT')
                        ->modalDescription(fn (Venta $record): string => 'Se enviará ' . ($record->serie?->serie ?? '') . '-' . str_pad((string) $record->correlativo, 8, '0', STR_PAD_LEFT) . ' directamente a SUNAT.')
                        ->action(fn (Venta $record) => $this->enviarAhora($record)),

                    Action::make('reintentarEnvio')
                        ->label('Reintentar envío')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (Venta $record): bool => $record->estado_sunat === \App\Enums\EstadoSunat::Error)
                        ->requiresConfirmation()
                        ->modalHeading('Reintentar envío a SUNAT')
                        ->modalDescription(fn (Venta $record): string => 'Se reintentará el envío de ' . ($record->serie?->serie ?? '') . '-' . str_pad((string) $record->correlativo, 8, '0', STR_PAD_LEFT) . '.')
                        ->action(fn (Venta $record) => $this->enviarAhora($record)),

                    Action::make('anular')
                        ->label('Anular')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Venta $record): bool => ! $record->estaAnulada())
                        ->action(fn (Venta $record) => $this->abrirAnular($record->id)),

                    Action::make('enviarBajaSunat')
                        ->label('Enviar baja a SUNAT')
                        ->icon('heroicon-o-archive-box-x-mark')
                        ->color('warning')
                        ->visible(fn (Venta $record): bool =>
                            $record->estaAnulada() && (
                                $this->estadoNecesitaBaja($record) ||
                                ($record->estado_sunat === EstadoSunat::PorDarBaja && is_null($record->resumen_sunat_id))
                            )
                        )
                        ->form([
                            TextInput::make('motivo')
                                ->label('Motivo de anulación')
                                ->default('Error en emisión')
                                ->required()
                                ->maxLength(100)
                                ->helperText('Solo aplica para facturas. Las boletas usan el Resumen Diario (RC).'),
                        ])
                        ->modalHeading('Enviar baja a SUNAT')
                        ->modalDescription(fn (Venta $record): string =>
                            'Se enviará la baja para ' .
                            ($record->serie?->serie ?? '') . '-' . str_pad((string) $record->correlativo, 8, '0', STR_PAD_LEFT) .
                            '. Boletas: nuevo RC con estado="3". Facturas: Comunicación de Baja (RA).'
                        )
                        ->action(fn (Venta $record, array $data) => $this->enviarBajaASunat($record, $data['motivo'])),

                    Action::make('consultarBaja')
                        ->label('Consultar baja SUNAT')
                        ->icon('heroicon-o-arrow-down-circle')
                        ->color('info')
                        ->visible(fn (Venta $record): bool =>
                            $record->estado_sunat === EstadoSunat::PorDarBaja
                            && $this->esFactura($record)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Consultar estado de baja (RA)')
                        ->modalDescription(fn (Venta $record): string =>
                            'Se consultará el ticket de la Comunicación de Baja para ' .
                            ($record->serie?->serie ?? '') . '-' . str_pad((string) $record->correlativo, 8, '0', STR_PAD_LEFT) . '.'
                        )
                        ->action(fn (Venta $record) => $this->consultarBajaVenta($record)),

                    Action::make('descargarXml')
                        ->label('Descargar XML')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('gray')
                        ->visible(fn (Venta $record): bool => ! empty($record->path_xml))
                        ->url(fn (Venta $record) => route('fe.comprobante.download', [$record->id, 'xml']))
                        ->openUrlInNewTab(),

                    Action::make('descargarCdr')
                        ->label('Descargar CDR')
                        ->icon('heroicon-o-document-check')
                        ->color('success')
                        ->visible(fn (Venta $record): bool => ! empty($record->path_cdr_zip))
                        ->url(fn (Venta $record) => route('fe.comprobante.download', [$record->id, 'cdr']))
                        ->openUrlInNewTab(),

                    $this->buildConvertirAction(),

                    $this->buildNotaAction(),
                ]),
            ])
            ->paginated([20, 50, 100])
            ->emptyStateHeading('Sin ventas en este turno')
            ->emptyStateIcon('heroicon-o-receipt-percent');
    }

    // ── Resumen del turno ─────────────────────────────────────────────────────

    public function getResumen(): array
    {
        $sesion = $this->getSesionActiva();

        if (! $sesion) {
            return ['count' => 0, 'total' => 0.0, 'anuladas' => 0, 'despacho' => 0, 'porMetodo' => []];
        }

        $base = Venta::where('empresa_id', Filament::getTenant()->id)
            ->where('sesion_caja_id', $sesion->id);

        $completadas    = (clone $base)->where('estado', EstadoVenta::Completada->value);
        $count          = (clone $completadas)->count();
        $total          = (float) (clone $completadas)->sum('total');
        $descuentoTotal = (float) (clone $completadas)->sum('descuento_total');
        $cortesias      = (clone $completadas)->whereHas('detalles', fn($q) => $q->where('precio_unitario', 0))->count();
        $anuladas       = (clone $base)->where('estado', EstadoVenta::Anulada->value)->count();
        $despacho       = (clone $base)->where('estado_despacho', EstadoVenta::PendienteEnvio->value)->count();

        $porMetodo = VentaPago::whereHas('venta', fn($q) => $q
                ->where('sesion_caja_id', $sesion->id)
                ->where('estado', EstadoVenta::Completada->value)
            )
            ->with('metodoPago:id,nombre')
            ->get()
            ->groupBy('metodo_pago_id')
            ->map(fn($pagos) => [
                'nombre' => $pagos->first()->metodoPago?->nombre ?? 'N/A',
                'total'  => (float) $pagos->sum('monto'),
            ])
            ->values()
            ->toArray();

        return compact('count', 'total', 'descuentoTotal', 'cortesias', 'anuladas', 'despacho', 'porMetodo');
    }

    // ── Modal detalle ─────────────────────────────────────────────────────────

    public function abrirDetalle(int $ventaId): void
    {
        $this->ventaModalId = $ventaId;
    }

    public function cerrarDetalle(): void
    {
        $this->ventaModalId = null;
    }

    public function getVentaModal(): ?Venta
    {
        if (! $this->ventaModalId) return null;

        return Venta::with([
            'serie',
            'detalles',
            'pagos.metodoPago',
        ])->find($this->ventaModalId);
    }

    // ── Modal anular ──────────────────────────────────────────────────────────

    public function abrirAnular(int $ventaId): void
    {
        $this->ventaAnularId = $ventaId;
        $this->revertirStock = true;
        $this->modalAnular   = true;
    }

    public function cerrarAnular(): void
    {
        $this->ventaAnularId = null;
        $this->modalAnular   = false;
        $this->revertirStock = true;
        $this->motivoBaja    = 'Error en emisión';
    }

    public function getVentaAnular(): ?Venta
    {
        if (! $this->ventaAnularId) return null;

        return Venta::with([
            'serie',
            'detalles.producto',
            'detalles.variante.producto',
            'pagos.metodoPago',
        ])->find($this->ventaAnularId);
    }

    public function necesitaBajaSunat(): bool
    {
        $venta = $this->getVentaAnular();
        if (! $venta) return false;
        $empresa = Filament::getTenant();
        return $empresa->tieneFacturacionElectronica() && $this->estadoNecesitaBaja($venta);
    }

    public function tieneItemsConStock(): bool
    {
        $venta = $this->getVentaAnular();
        if (! $venta) return false;

        foreach ($venta->detalles as $d) {
            if ($d->tipo_item === TipoItem::Promocion)                    return true;
            if ($d->producto?->control_de_stock)                          return true;
            if ($d->variante?->producto?->control_de_stock)               return true;
        }

        return false;
    }

    public function confirmarAnular(): void
    {
        if (! $this->ventaAnularId) return;

        $venta = Venta::with([
            'serie',
            'detalles.producto',
            'detalles.variante.producto',
            'pagos',
        ])->find($this->ventaAnularId);

        if (! $venta || $venta->empresa_id !== Filament::getTenant()->id) {
            Notification::make()->title('Venta no encontrada')->danger()->send();
            $this->cerrarAnular();
            return;
        }

        if ($venta->estaAnulada()) {
            Notification::make()->title('Esta venta ya está anulada')->warning()->send();
            $this->cerrarAnular();
            return;
        }

        $empresaId   = Filament::getTenant()->id;
        $comprobante = ($venta->serie?->serie ?? '---') . '-' . $venta->correlativo;
        $sesion      = $this->getSesionActiva();
        $revertir    = $this->revertirStock;

        try {
            DB::transaction(function () use ($venta, $empresaId, $comprobante, $sesion, $revertir) {

                // 1 ── Marcar venta como anulada
                $venta->update(['estado' => EstadoVenta::Anulada]);

                Orden::where('venta_id', $venta->id)
                    ->update(['estado' => EstadoOrden::Cancelada]);

                // 2 ── Anular transacciones de ingreso originales
                // (suficiente para quitar el monto del saldo de caja;
                //  no se crea egreso para evitar doble descuento)
                Transaccion::where('transaccionable_type', Venta::class)
                    ->where('transaccionable_id', $venta->id)
                    ->update(['estado' => EstadoMovimiento::Anulado->value]);

                // 3 ── Revertir stock + kardex (solo si el usuario lo eligió)
                if ($revertir) {
                    $kardex      = app(KardexService::class);
                    $conceptoRev = "Reversión {$comprobante}";

                    foreach ($venta->detalles as $detalle) {
                        $cantidad = (float) $detalle->cantidad;

                        // ── Producto simple ───────────────────────────────────
                        if ($detalle->tipo_item === TipoItem::Producto && $detalle->producto_id) {
                            if ($detalle->producto?->control_de_stock) {
                                $inv = Inventario::where('empresa_id', $empresaId)
                                    ->where('producto_id', $detalle->producto_id)
                                    ->whereNull('variante_id')
                                    ->lockForUpdate()
                                    ->first();

                                if ($inv) {
                                    $antes   = (float) $inv->stock_real;
                                    $despues = $antes + $cantidad;
                                    $inv->update([
                                        'stock_real'    => $despues,
                                        'stock_reserva' => max(0, (float) $inv->stock_reserva + ($despues - $antes)),
                                    ]);
                                    $kardex->registrar([
                                        'empresa_id'        => $empresaId,
                                        'user_id'           => auth()->id(),
                                        'movible'           => $venta,
                                        'producto_id'       => $detalle->producto_id,
                                        'variante_id'       => null,
                                        'producto_nombre'   => $detalle->descripcion,
                                        'tipo'              => 'entrada',
                                        'concepto'          => $conceptoRev,
                                        'cantidad'          => $cantidad,
                                        'unidad'            => 'unidad',
                                        'factor_conversion' => 1,
                                        'cantidad_base'     => $cantidad,
                                        'precio_unitario'   => (float) $detalle->precio_unitario,
                                        'precio_total'      => (float) $detalle->total,
                                        'stock_antes'       => $antes,
                                        'stock_despues'     => $despues,
                                        'fecha'             => now(),
                                    ]);
                                }
                            }
                        }

                        // ── Variante ──────────────────────────────────────────
                        elseif ($detalle->tipo_item === TipoItem::Variante && $detalle->variante_id) {
                            $varianteProd = $detalle->variante?->producto;
                            if ($varianteProd?->control_de_stock) {
                                $productoId = $detalle->variante->producto_id;
                                $inv = Inventario::where('empresa_id', $empresaId)
                                    ->where('producto_id', $productoId)
                                    ->where('variante_id', $detalle->variante_id)
                                    ->lockForUpdate()
                                    ->first();

                                if ($inv) {
                                    $antes   = (float) $inv->stock_real;
                                    $despues = $antes + $cantidad;
                                    $inv->update([
                                        'stock_real'    => $despues,
                                        'stock_reserva' => max(0, (float) $inv->stock_reserva + ($despues - $antes)),
                                    ]);
                                    $kardex->registrar([
                                        'empresa_id'        => $empresaId,
                                        'user_id'           => auth()->id(),
                                        'movible'           => $venta,
                                        'producto_id'       => $productoId,
                                        'variante_id'       => $detalle->variante_id,
                                        'producto_nombre'   => $detalle->descripcion,
                                        'tipo'              => 'entrada',
                                        'concepto'          => $conceptoRev,
                                        'cantidad'          => $cantidad,
                                        'unidad'            => 'unidad',
                                        'factor_conversion' => 1,
                                        'cantidad_base'     => $cantidad,
                                        'precio_unitario'   => (float) $detalle->precio_unitario,
                                        'precio_total'      => (float) $detalle->total,
                                        'stock_antes'       => $antes,
                                        'stock_despues'     => $despues,
                                        'fecha'             => now(),
                                    ]);
                                }
                            }
                        }

                        // ── Promoción: revertir cada componente del combo ─────
                        elseif ($detalle->tipo_item === TipoItem::Promocion && $detalle->promocion_id) {
                            Promocion::where('id', $detalle->promocion_id)
                                ->decrement('usos_actuales', (int) $cantidad);

                            $promo = Promocion::with([
                                'detalles.producto',
                                'detalles.variante.producto',
                            ])->find($detalle->promocion_id);

                            if (! $promo) continue;

                            foreach ($promo->detalles as $comp) {
                                $cantComp = $cantidad * (float) $comp->cantidad;

                                if ($comp->variante_id) {
                                    $prodComp = $comp->variante?->producto;
                                    if ($prodComp?->control_de_stock) {
                                        $inv = Inventario::where('empresa_id', $empresaId)
                                            ->where('variante_id', $comp->variante_id)
                                            ->lockForUpdate()->first();
                                        if ($inv) {
                                            $antes   = (float) $inv->stock_real;
                                            $despues = $antes + $cantComp;
                                            $inv->update([
                                        'stock_real'    => $despues,
                                        'stock_reserva' => max(0, (float) $inv->stock_reserva + ($despues - $antes)),
                                    ]);
                                            $kardex->registrar([
                                                'empresa_id'        => $empresaId,
                                                'user_id'           => auth()->id(),
                                                'movible'           => $venta,
                                                'producto_id'       => $comp->variante->producto_id,
                                                'variante_id'       => $comp->variante_id,
                                                'tipo'              => 'entrada',
                                                'concepto'          => $conceptoRev,
                                                'notas'             => "Promo: {$detalle->descripcion}",
                                                'cantidad'          => $cantComp,
                                                'unidad'            => 'unidad',
                                                'factor_conversion' => 1,
                                                'cantidad_base'     => $cantComp,
                                                'stock_antes'       => $antes,
                                                'stock_despues'     => $despues,
                                                'fecha'             => now(),
                                            ]);
                                        }
                                    }
                                } elseif ($comp->producto_id) {
                                    $prodComp = $comp->producto;
                                    if ($prodComp?->control_de_stock) {
                                        $inv = Inventario::where('empresa_id', $empresaId)
                                            ->where('producto_id', $comp->producto_id)
                                            ->whereNull('variante_id')
                                            ->lockForUpdate()->first();
                                        if ($inv) {
                                            $antes   = (float) $inv->stock_real;
                                            $despues = $antes + $cantComp;
                                            $inv->update([
                                        'stock_real'    => $despues,
                                        'stock_reserva' => max(0, (float) $inv->stock_reserva + ($despues - $antes)),
                                    ]);
                                            $kardex->registrar([
                                                'empresa_id'        => $empresaId,
                                                'user_id'           => auth()->id(),
                                                'movible'           => $venta,
                                                'producto_id'       => $comp->producto_id,
                                                'variante_id'       => null,
                                                'tipo'              => 'entrada',
                                                'concepto'          => $conceptoRev,
                                                'notas'             => "Promo: {$detalle->descripcion}",
                                                'cantidad'          => $cantComp,
                                                'unidad'            => 'unidad',
                                                'factor_conversion' => 1,
                                                'cantidad_base'     => $cantComp,
                                                'stock_antes'       => $antes,
                                                'stock_despues'     => $despues,
                                                'fecha'             => now(),
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            });
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al anular la venta')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        $motivoBaja = $this->motivoBaja;
        $this->cerrarAnular();

        Notification::make()
            ->title("Venta {$comprobante} anulada")
            ->body($revertir ? 'Se registró la devolución y se revirtió el inventario.' : 'Se registró la devolución. El inventario no fue modificado.')
            ->warning()
            ->send();

        // Si el comprobante ya fue enviado a SUNAT, enviar Comunicación de Baja (RA)
        if ($this->estadoNecesitaBaja($venta)) {
            $this->enviarBajaASunat($venta, $motivoBaja);
        }
    }

}
