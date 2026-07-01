<?php

namespace App\Filament\Pdv\Pages;

use App\Models\Compra;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use UnitEnum;

class ReporteComprasPage extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected string $view = 'filament.pdv.pages.reporte-compras';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Reporte de Compras';
    protected static string|UnitEnum|null $navigationGroup = 'Compras';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Reporte de Compras';

    public function getHeading(): string          { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }

    // ── Filtros ───────────────────────────────────────────────────────────────

    public ?string $filtroRango           = 'hoy';
    public ?string $filtroFechaDesde      = null;
    public ?string $filtroFechaHasta      = null;
    public ?string $filtroUsuario         = null;
    public ?string $filtroEstado          = null;
    public ?string $filtroEstadoPago      = null;
    public ?string $filtroTipoComprobante = null;
    public ?string $filtroSerie           = null;
    public ?string $filtroCorrelativo     = null;

    // ── Estado de modales ─────────────────────────────────────────────────────

    public ?int $compraDetalleId = null;
    public ?int $compraPagosId   = null;

    public function mount(): void
    {
        $hoy = today()->toDateString();
        $this->filtroFechaDesde = $hoy;
        $this->filtroFechaHasta = $hoy;
        $this->form->fill([
            'filtroRango'      => 'hoy',
            'filtroFechaDesde' => $hoy,
            'filtroFechaHasta' => $hoy,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 2, 'md' => 4])->schema([

                Select::make('filtroRango')
                    ->label('Período')
                    ->options(['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes', 'personalizado' => 'Personalizado'])
                    ->native(false)
                    ->live()->afterStateUpdated(fn(?string $state) => $state ? $this->aplicarRango($state) : null),

                Select::make('filtroUsuario')
                    ->label('Registrado por')
                    ->placeholder('Todos los usuarios')
                    ->options(fn() => DB::table('users')
                        ->join('compras', 'users.id', '=', 'compras.user_id')
                        ->where('compras.empresa_id', Filament::getTenant()->id)
                        ->distinct()->orderBy('users.name')
                        ->pluck('users.name', 'users.id')->toArray())
                    ->native(false)->searchable()
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

                Select::make('filtroEstado')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->options(['borrador' => 'Borrador', 'confirmado' => 'Confirmado', 'anulado' => 'Anulado'])
                    ->native(false)
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

                Select::make('filtroEstadoPago')
                    ->label('Estado de pago')
                    ->placeholder('Todos')
                    ->options(['pendiente' => 'Pendiente', 'pagado' => 'Pagado'])
                    ->native(false)
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

                Select::make('filtroTipoComprobante')
                    ->label('Tipo de comprobante')
                    ->placeholder('Todos')
                    ->options(['factura' => 'Factura', 'boleta' => 'Boleta', 'ticket' => 'Ticket', 'sin_comprobante' => 'Sin comprobante'])
                    ->native(false)
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

                TextInput::make('filtroSerie')
                    ->label('Serie')
                    ->placeholder('Ej: F001')
                    ->live(debounce: 400)->afterStateUpdated(fn() => $this->resetPage()),

                TextInput::make('filtroCorrelativo')
                    ->label('Correlativo')
                    ->placeholder('Ej: 00001')
                    ->live(debounce: 400)->afterStateUpdated(fn() => $this->resetPage()),

                DatePicker::make('filtroFechaDesde')
                    ->label('Desde')->displayFormat('d/m/Y')
                    ->live()->afterStateUpdated(fn() => $this->resetPage())
                    ->hidden(fn() => $this->filtroRango !== 'personalizado'),

                DatePicker::make('filtroFechaHasta')
                    ->label('Hasta')->displayFormat('d/m/Y')
                    ->live()->afterStateUpdated(fn() => $this->resetPage())
                    ->hidden(fn() => $this->filtroRango !== 'personalizado'),

                Actions::make([
                    Action::make('limpiarFiltros')
                        ->label('Limpiar')
                        ->color('gray')->size('sm')->outlined()
                        ->icon('heroicon-o-x-mark')
                        ->visible(fn() => $this->hayFiltros())
                        ->action(fn() => $this->limpiarFiltros()),
                ])->verticallyAlignEnd(),

            ]),
        ]);
    }

    private function aplicarRango(string $rango): void
    {
        [$desde, $hasta] = match($rango) {
            'semana' => [today()->startOfWeek()->toDateString(), today()->endOfWeek()->toDateString()],
            'mes'    => [today()->startOfMonth()->toDateString(), today()->endOfMonth()->toDateString()],
            default  => [today()->toDateString(), today()->toDateString()],
        };

        if ($rango !== 'personalizado') {
            $this->filtroFechaDesde = $desde;
            $this->filtroFechaHasta = $hasta;
            $this->form->fill(['filtroFechaDesde' => $desde, 'filtroFechaHasta' => $hasta]);
        }

        $this->resetPage();
    }

    public function hayFiltros(): bool
    {
        return $this->filtroRango !== 'hoy'
            || !empty($this->filtroUsuario)
            || !empty($this->filtroEstado)
            || !empty($this->filtroEstadoPago)
            || !empty($this->filtroTipoComprobante)
            || !empty($this->filtroSerie)
            || !empty($this->filtroCorrelativo);
    }

    public function limpiarFiltros(): void
    {
        $hoy = today()->toDateString();
        $this->filtroRango           = 'hoy';
        $this->filtroFechaDesde      = $hoy;
        $this->filtroFechaHasta      = $hoy;
        $this->filtroUsuario         = null;
        $this->filtroEstado          = null;
        $this->filtroEstadoPago      = null;
        $this->filtroTipoComprobante = null;
        $this->filtroSerie           = null;
        $this->filtroCorrelativo     = null;
        $this->form->fill([
            'filtroRango' => 'hoy', 'filtroFechaDesde' => $hoy, 'filtroFechaHasta' => $hoy,
            'filtroUsuario' => null, 'filtroEstado' => null, 'filtroEstadoPago' => null,
            'filtroTipoComprobante' => null, 'filtroSerie' => null, 'filtroCorrelativo' => null,
        ]);
        $this->resetPage();
    }

    // ── Query base ────────────────────────────────────────────────────────────

    private function aplicarFiltros(\Illuminate\Database\Eloquent\Builder $q): void
    {
        // empresa_id ya la filtra el global scope de BelongsToEmpresa; cualificarla
        // aquí evita ambigüedad cuando se hace join con compra_pagos.
        $q->where('compras.empresa_id', Filament::getTenant()->id);

        if (!empty($this->filtroFechaDesde)) {
            $q->whereDate('compras.fecha_compra', '>=', $this->filtroFechaDesde);
        }
        if (!empty($this->filtroFechaHasta)) {
            $q->whereDate('compras.fecha_compra', '<=', $this->filtroFechaHasta);
        }
        if (!empty($this->filtroUsuario)) {
            $q->where('compras.user_id', $this->filtroUsuario);
        }
        if (!empty($this->filtroEstado)) {
            $q->where('compras.estado', $this->filtroEstado);
        }
        if (!empty($this->filtroEstadoPago)) {
            $q->where('compras.estado_pago', $this->filtroEstadoPago);
        }
        if (!empty($this->filtroTipoComprobante)) {
            $q->where('compras.tipo_comprobante', $this->filtroTipoComprobante);
        }
        if (!empty($this->filtroSerie)) {
            $q->where('compras.serie', 'like', $this->filtroSerie . '%');
        }
        if (!empty($this->filtroCorrelativo)) {
            $q->where('compras.correlativo', 'like', $this->filtroCorrelativo . '%');
        }
    }

    // ── Listado paginado ──────────────────────────────────────────────────────

    public function getCompras(): LengthAwarePaginator
    {
        $q = Compra::with(['proveedor:id,nombre', 'registradoPor:id,name'])
            ->withSum('pagos', 'monto')
            ->withCount('detalles')
            ->orderByDesc('fecha_compra')
            ->orderByDesc('id');

        $this->aplicarFiltros($q);

        return $q->paginate(25);
    }

    // ── Resumen ───────────────────────────────────────────────────────────────

    public function getResumen(): array
    {
        $q = Compra::query();
        $this->aplicarFiltros($q);

        $cantidad  = (clone $q)->count();
        $total     = (float) (clone $q)->sum('total');
        $pendiente = (int) (clone $q)->where('estado_pago', 'pendiente')->count();
        $pagado    = (float) (clone $q)->join('compra_pagos as cp', 'cp.compra_id', '=', 'compras.id')
            ->sum('cp.monto');

        return [
            'cantidad'  => $cantidad,
            'total'     => $total,
            'pendiente' => $pendiente,
            'pagado'    => $pagado,
            'saldo'     => round($total - $pagado, 2),
        ];
    }

    // ── Modal detalle ─────────────────────────────────────────────────────────

    public function abrirDetalle(int $id): void { $this->compraPagosId = null; $this->compraDetalleId = $id; }
    public function cerrarDetalle(): void        { $this->compraDetalleId = null; }

    public function getCompraDetalle(): ?Compra
    {
        if (!$this->compraDetalleId) return null;

        return Compra::with([
            'proveedor:id,nombre',
            'registradoPor:id,name',
            'detalles.unidad:id,simbolo',
        ])->find($this->compraDetalleId);
    }

    // ── Modal pagos ───────────────────────────────────────────────────────────

    public function abrirPagos(int $id): void { $this->compraDetalleId = null; $this->compraPagosId = $id; }
    public function cerrarPagos(): void        { $this->compraPagosId = null; }

    public function getCompraPagos(): ?Compra
    {
        if (!$this->compraPagosId) return null;

        return Compra::with([
            'proveedor:id,nombre',
            'pagos.metodoPago:id,nombre',
        ])->find($this->compraPagosId);
    }
}
