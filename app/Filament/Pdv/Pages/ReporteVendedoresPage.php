<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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

class ReporteVendedoresPage extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected string $view = 'filament.pdv.pages.reporte-vendedores';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'Vendedores';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 7;
    protected static ?string $title = 'Reporte de Vendedores';

    public static function canAccess(): bool { return auth()->user()?->can('caja.reporte_vendedores') ?? false; }

    public function getHeading(): string          { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }

    public ?string $filtroRango      = 'hoy';
    public ?string $filtroFechaDesde = null;
    public ?string $filtroFechaHasta = null;
    public ?string $filtroVendedor   = null;

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
            Grid::make(['default' => 1, 'sm' => 2, 'md' => 5])->schema([

                Select::make('filtroRango')
                    ->label('Período')
                    ->options([
                        'hoy'           => 'Hoy',
                        'semana'        => 'Esta semana',
                        'mes'           => 'Este mes',
                        'personalizado' => 'Personalizado',
                    ])
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn(string $state) => $this->aplicarRango($state)),

                Select::make('filtroVendedor')
                    ->label('Vendedor')
                    ->placeholder('Todos los vendedores')
                    ->options(fn() => DB::table('users')
                        ->join('ventas', 'users.id', '=', 'ventas.vendedor_id')
                        ->where('ventas.empresa_id', Filament::getTenant()->id)
                        ->distinct()->orderBy('users.name')
                        ->pluck('users.name', 'users.id')->toArray())
                    ->native(false)->searchable()
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

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
        return !empty($this->filtroVendedor)
            || $this->filtroRango !== 'hoy';
    }

    public function limpiarFiltros(): void
    {
        $hoy = today()->toDateString();
        $this->filtroVendedor   = null;
        $this->filtroRango      = 'hoy';
        $this->filtroFechaDesde = $hoy;
        $this->filtroFechaHasta = $hoy;
        $this->form->fill([
            'filtroRango'      => 'hoy',
            'filtroVendedor'   => null,
            'filtroFechaDesde' => $hoy,
            'filtroFechaHasta' => $hoy,
        ]);
        $this->resetPage();
    }

    private function baseQuery(): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('ventas as v')
            ->join('users as u', 'v.vendedor_id', '=', 'u.id')
            ->where('v.empresa_id', Filament::getTenant()->id)
            ->where('v.estado', EstadoVenta::Completada->value);

        if (!empty($this->filtroFechaDesde)) {
            $q->whereDate('v.created_at', '>=', $this->filtroFechaDesde);
        }
        if (!empty($this->filtroFechaHasta)) {
            $q->whereDate('v.created_at', '<=', $this->filtroFechaHasta);
        }
        if (!empty($this->filtroVendedor)) {
            $q->where('v.vendedor_id', $this->filtroVendedor);
        }

        return $q;
    }

    public function getResumen(): array
    {
        $row = (clone $this->baseQuery())
            ->selectRaw("
                COUNT(DISTINCT v.vendedor_id)              AS total_vendedores,
                COUNT(*)                                   AS cantidad,
                COALESCE(SUM(v.total), 0)                 AS ingresos_brutos,
                COALESCE(SUM(v.total - v.igv), 0)         AS ventas_netas,
                COALESCE(SUM(v.costo_total), 0)           AS costo_total,
                COALESCE(SUM(v.total - v.igv - v.costo_total), 0) AS utilidad_bruta,
                COALESCE(SUM(v.monto_pagado), 0)                   AS cobrado,
                COALESCE(SUM(v.saldo_pendiente), 0)                AS credito_pendiente
            ")
            ->first();

        return [
            'totalVendedores'  => (int) ($row->total_vendedores ?? 0),
            'cantidad'         => (int) ($row->cantidad ?? 0),
            'ingresosBrutos'   => (float) ($row->ingresos_brutos ?? 0),
            'cobrado'          => (float) ($row->cobrado ?? 0),
            'costoTotal'       => (float) ($row->costo_total ?? 0),
            'utilidadBruta'    => (float) ($row->utilidad_bruta ?? 0),
            'creditoPendiente' => (float) ($row->credito_pendiente ?? 0),
        ];
    }

    public function getVendedores(): LengthAwarePaginator
    {
        return (clone $this->baseQuery())
            ->selectRaw("
                v.vendedor_id,
                u.name                                             AS vendedor,
                COUNT(*)                                           AS cantidad,
                COALESCE(SUM(v.total), 0)                         AS ingresos,
                COALESCE(SUM(v.monto_pagado), 0)                  AS cobrado,
                COALESCE(SUM(v.saldo_pendiente), 0)               AS credito_pendiente,
                COALESCE(SUM(v.costo_total), 0)                   AS costo,
                COALESCE(SUM(v.total - v.igv - v.costo_total), 0) AS utilidad,
                MAX(v.created_at)                                  AS ultima_venta
            ")
            ->groupBy('v.vendedor_id', 'u.name')
            ->orderByDesc('ingresos')
            ->paginate(25);
    }
}
