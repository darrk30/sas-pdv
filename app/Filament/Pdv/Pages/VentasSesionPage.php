<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoMovimiento;
use App\Enums\EstadoSesion;
use App\Enums\EstadoVenta;
use App\Enums\TipoItem;
use App\Enums\TipoMovimiento;
use App\Models\Inventario;
use App\Models\Promocion;
use App\Models\SesionCaja;
use App\Models\Transaccion;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Services\KardexService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use UnitEnum;

class VentasSesionPage extends Page
{
    use WithPagination;

    protected string $view = 'filament.pdv.pages.ventas-sesion';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Ventas del Turno';
    protected static string|UnitEnum|null $navigationGroup = 'Caja';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Ventas del Turno';

    public function getHeading(): string { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }

    // ── Filtros ───────────────────────────────────────────────────────────────
    public string $busqueda     = '';
    public string $filtroEstado = '';

    // ── Modal detalle ─────────────────────────────────────────────────────────
    public ?int $ventaModalId = null;

    // ── Modal anular ──────────────────────────────────────────────────────────
    public ?int  $ventaAnularId  = null;
    public bool  $modalAnular    = false;
    public bool  $revertirStock  = true;

    public function updatedBusqueda(): void     { $this->resetPage(); }
    public function updatedFiltroEstado(): void { $this->resetPage(); }

    public function limpiarFiltros(): void
    {
        $this->busqueda     = '';
        $this->filtroEstado = '';
        $this->resetPage();
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

    // ── Ventas paginadas ──────────────────────────────────────────────────────

    public function getVentas(): LengthAwarePaginator
    {
        $sesion = $this->getSesionActiva();

        $q = Venta::where('empresa_id', Filament::getTenant()->id)
            ->with(['serie', 'pagos.metodoPago'])
            ->orderBy('created_at', 'desc');

        if ($sesion) {
            $q->where('sesion_caja_id', $sesion->id);
        } else {
            $q->whereRaw('0 = 1');
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

        return $q->withCount('detalles')->paginate(20);
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

        $count    = (clone $base)->where('estado', EstadoVenta::Completada->value)->count();
        $total    = (float) (clone $base)->where('estado', EstadoVenta::Completada->value)->sum('total');
        $anuladas = (clone $base)->where('estado', EstadoVenta::Anulada->value)->count();
        $despacho = (clone $base)->where('estado_despacho', EstadoVenta::PendienteEnvio->value)->count();

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

        return compact('count', 'total', 'anuladas', 'despacho', 'porMetodo');
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
                                    $inv->update(['stock_real' => $despues]);
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
                                    $inv->update(['stock_real' => $despues]);
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
                                            $inv->update(['stock_real' => $despues]);
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
                                            $inv->update(['stock_real' => $despues]);
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
            ->body($revertir ? 'Se registró la devolución y se revirtió el inventario.' : 'Se registró la devolución. El inventario no fue modificado.')
            ->warning()
            ->send();
    }
}
