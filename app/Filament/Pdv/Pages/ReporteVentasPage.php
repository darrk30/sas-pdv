<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\CondicionPago;
use App\Enums\EstadoMovimiento;
use App\Enums\EstadoOrden;
use App\Enums\EstadoSesion;
use App\Enums\EstadoVenta;
use App\Enums\TipoItem;
use App\Enums\TipoMovimiento;
use App\Models\Inventario;
use App\Models\MetodoPago;
use App\Models\Orden;
use App\Models\Promocion;
use App\Models\Serie;
use App\Models\SesionCaja;
use App\Models\Transaccion;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Services\KardexService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use UnitEnum;

class ReporteVentasPage extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.reporte-ventas';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Reporte de Ventas';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Reporte de Ventas';

    public static function canAccess(): bool { return Filament::getTenant()->tieneModulo('reporte_ventas') && (auth()->user()?->can('caja.reporte_ventas') ?? false); }


    // ── Estado de filtros (nullable: Select/DatePicker devuelven null al limpiar)
    public ?string $filtroCliente     = null;
    public ?string $filtroSerie       = null;
    public ?string $filtroCorrelativo = null;
    public ?string $filtroMetodo      = null;
    public ?string $filtroEstado      = null;
    public ?string $filtroFechaDesde  = null;
    public ?string $filtroFechaHasta  = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    // ── Form con componentes Filament ─────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 2, 'md' => 4])->schema([

                TextInput::make('filtroCliente')
                    ->label('Cliente')
                    ->placeholder('Nombre o documento…')
                    ->prefixIcon('heroicon-o-magnifying-glass')
                    ->live(debounce: 300)
                    ->afterStateUpdated(fn() => $this->resetPage())
                    ->columnSpan(['default' => 1, 'sm' => 2, 'md' => 2]),

                TextInput::make('filtroCorrelativo')
                    ->label('Correlativo')
                    ->placeholder('Ej: 00001')
                    ->live(debounce: 300)
                    ->afterStateUpdated(fn() => $this->resetPage()),

                Select::make('filtroEstado')
                    ->label('Estado')
                    ->placeholder('Todos los estados')
                    ->options(['completada' => 'Completadas', 'anulada' => 'Anuladas'])
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn() => $this->resetPage()),

                Select::make('filtroSerie')
                    ->label('Serie')
                    ->placeholder('Todas las series')
                    ->options(fn() => Serie::where('empresa_id', Filament::getTenant()->id)
                        ->orderBy('serie')->pluck('serie', 'serie')->toArray())
                    ->native(false)
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn() => $this->resetPage()),

                Select::make('filtroMetodo')
                    ->label('Método de pago')
                    ->placeholder('Todos los métodos')
                    ->options(fn() => MetodoPago::where('empresa_id', Filament::getTenant()->id)
                        ->orderBy('nombre')->pluck('nombre', 'id')->toArray())
                    ->native(false)
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn() => $this->resetPage()),

                DatePicker::make('filtroFechaDesde')
                    ->label('Desde')
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->afterStateUpdated(fn() => $this->resetPage()),

                DatePicker::make('filtroFechaHasta')
                    ->label('Hasta')
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->afterStateUpdated(fn() => $this->resetPage()),

            ]),
        ]);
    }

    public function hayFiltros(): bool
    {
        return ! empty($this->filtroCliente)
            || ! empty($this->filtroSerie)
            || ! empty($this->filtroCorrelativo)
            || ! empty($this->filtroMetodo)
            || ! empty($this->filtroEstado)
            || ! empty($this->filtroFechaDesde)
            || ! empty($this->filtroFechaHasta);
    }

    public function limpiarFiltros(): void
    {
        $this->filtroCliente     = null;
        $this->filtroSerie       = null;
        $this->filtroCorrelativo = null;
        $this->filtroMetodo      = null;
        $this->filtroEstado      = null;
        $this->filtroFechaDesde  = null;
        $this->filtroFechaHasta  = null;
        $this->form->fill();
        $this->resetPage();
    }

    // ── Opciones de filtros ───────────────────────────────────────────────────

    // ── Query base con filtros ────────────────────────────────────────────────

    private function aplicarFiltros(Builder $q): Builder
    {
        if (! empty($this->filtroCliente)) {
            $b = $this->filtroCliente;
            $q->where(function ($sub) use ($b) {
                $sub->where('cliente_nombre', 'like', "%{$b}%")
                    ->orWhere('cliente_num_doc', 'like', "%{$b}%");
            });
        }

        if (! empty($this->filtroSerie)) {
            $s = $this->filtroSerie;
            $q->whereHas('serie', fn($sub) => $sub->where('serie', $s));
        }

        if (! empty($this->filtroCorrelativo)) {
            $q->where('correlativo', 'like', "%{$this->filtroCorrelativo}%");
        }

        if (! empty($this->filtroMetodo)) {
            $m = $this->filtroMetodo;
            $q->whereHas('pagos', fn($sub) => $sub->where('metodo_pago_id', $m));
        }

        if (! empty($this->filtroEstado)) {
            $q->where('estado', $this->filtroEstado);
        }

        if (! empty($this->filtroFechaDesde)) {
            $q->whereDate('created_at', '>=', $this->filtroFechaDesde);
        }

        if (! empty($this->filtroFechaHasta)) {
            $q->whereDate('created_at', '<=', $this->filtroFechaHasta);
        }

        return $q;
    }

    // ── Ventas paginadas ──────────────────────────────────────────────────────

    public function getVentas(): LengthAwarePaginator
    {
        $q = Venta::where('empresa_id', Filament::getTenant()->id)
            ->with(['serie', 'pagos.metodoPago'])
            ->orderBy('created_at', 'desc');

        $this->aplicarFiltros($q);

        return $q->withCount('detalles')->paginate(25);
    }

    // ── Resumen (refleja los filtros activos) ─────────────────────────────────

    public function getResumen(): array
    {
        $base = Venta::where('empresa_id', Filament::getTenant()->id);
        $this->aplicarFiltros($base);

        $completadas      = (clone $base)->where('estado', EstadoVenta::Completada->value);
        $count            = (clone $completadas)->count();
        $total            = (float) (clone $completadas)->sum('monto_pagado');
        $creditoPendiente = (float) (clone $completadas)->whereIn('estado_pago', ['pendiente', 'parcial'])->sum('saldo_pendiente');
        $anuladas         = (clone $base)->where('estado', EstadoVenta::Anulada->value)->count();

        $porMetodo = VentaPago::whereHas('venta', function ($q) {
                $q->where('empresa_id', Filament::getTenant()->id)
                  ->where('estado', EstadoVenta::Completada->value);
                $this->aplicarFiltros($q);
            })
            ->with(['metodoPago:id,nombre,condicion_pago', 'venta:id,estado_pago'])
            ->get()
            ->filter(fn ($p) =>
                $p->metodoPago?->condicion_pago !== CondicionPago::Credito
                || $p->venta?->estado_pago === 'pendiente'
            )
            ->groupBy('metodo_pago_id')
            ->map(fn($pagos) => [
                'nombre' => $pagos->first()->metodoPago?->nombre ?? 'N/A',
                'total'  => (float) $pagos->sum('monto'),
            ])
            ->values()
            ->toArray();

        return compact('count', 'total', 'anuladas', 'porMetodo', 'creditoPendiente');
    }

    // ── Modal detalle ─────────────────────────────────────────────────────────

    public ?int $ventaModalId = null;

    public function abrirDetalle(int $ventaId): void  { $this->ventaModalId = $ventaId; }
    public function cerrarDetalle(): void              { $this->ventaModalId = null; }

    public function getVentaModal(): ?Venta
    {
        if (! $this->ventaModalId) return null;

        return Venta::with([
            'serie',
            'detalles.producto.unidadMedida:id,simbolo',
            'detalles.variante.producto.unidadMedida:id,simbolo',
            'pagos.metodoPago',
        ])->find($this->ventaModalId);
    }

    // ── Modal anular ──────────────────────────────────────────────────────────

    public ?int  $ventaAnularId  = null;
    public bool  $modalAnular    = false;
    public bool  $revertirStock  = true;

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

    public function tieneItemsConStock(): bool
    {
        $venta = $this->getVentaAnular();
        if (! $venta) return false;

        foreach ($venta->detalles as $d) {
            if ($d->tipo_item === TipoItem::Promocion)          return true;
            if ($d->producto?->control_de_stock)                return true;
            if ($d->variante?->producto?->control_de_stock)     return true;
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
        $sesion      = SesionCaja::where('empresa_id', $empresaId)
                            ->where('user_id', auth()->id())
                            ->where('estado', EstadoSesion::Abierta->value)
                            ->latest()->first();
        $revertir    = $this->revertirStock;

        try {
            DB::transaction(function () use ($venta, $empresaId, $comprobante, $sesion, $revertir) {

                $venta->update(['estado' => EstadoVenta::Anulada]);

                Orden::where('venta_id', $venta->id)
                    ->update(['estado' => EstadoOrden::Cancelada]);

                Transaccion::where('transaccionable_type', Venta::class)
                    ->where('transaccionable_id', $venta->id)
                    ->where('tipo', TipoMovimiento::Ingreso->value)
                    ->update(['estado' => EstadoMovimiento::Anulado->value]);

                // Ventas web no tienen caja — se omite el egreso de devolución
                if ($venta->tipo !== 'web' && $sesion) {
                    foreach ($venta->pagos as $pago) {
                        Transaccion::create([
                            'empresa_id'           => $empresaId,
                            'sesion_caja_id'       => $sesion->id,
                            'transaccionable_type' => Venta::class,
                            'transaccionable_id'   => $venta->id,
                            'tipo'                 => TipoMovimiento::Egreso,
                            'concepto'             => "Devolución {$comprobante}",
                            'monto'                => $pago->monto,
                            'metodo_pago_id'       => $pago->metodo_pago_id,
                            'estado'               => EstadoMovimiento::Aprobado,
                            'fecha'                => now(),
                        ]);
                    }
                }

                if ($revertir) {
                    $kardex      = app(KardexService::class);
                    $conceptoRev = "Reversión {$comprobante}";

                    foreach ($venta->detalles as $detalle) {
                        $cantidad = (float) $detalle->cantidad;

                        if ($detalle->tipo_item === TipoItem::Producto && $detalle->producto_id) {
                            if ($detalle->producto?->control_de_stock) {
                                $inv = Inventario::where('empresa_id', $empresaId)
                                    ->where('producto_id', $detalle->producto_id)
                                    ->whereNull('variante_id')
                                    ->lockForUpdate()->first();

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
                        } elseif ($detalle->tipo_item === TipoItem::Variante && $detalle->variante_id) {
                            $varianteProd = $detalle->variante?->producto;
                            if ($varianteProd?->control_de_stock) {
                                $productoId = $detalle->variante->producto_id;
                                $inv = Inventario::where('empresa_id', $empresaId)
                                    ->where('producto_id', $productoId)
                                    ->where('variante_id', $detalle->variante_id)
                                    ->lockForUpdate()->first();

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
                        } elseif ($detalle->tipo_item === TipoItem::Promocion && $detalle->promocion_id) {
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

        $this->cerrarAnular();

        Notification::make()
            ->title("Venta {$comprobante} anulada")
            ->body($revertir
                ? 'Se registró la devolución y se revirtió el inventario.'
                : 'Se registró la devolución. El inventario no fue modificado.')
            ->warning()
            ->send();
    }
}
