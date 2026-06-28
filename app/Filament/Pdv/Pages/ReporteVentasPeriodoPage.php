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

class ReporteVentasPeriodoPage extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected string $view = 'filament.pdv.pages.reporte-ventas-periodo';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Ventas por período';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 4;
    protected static ?string $title = 'Ventas por período';

    public function getHeading(): string          { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }

    public ?string $filtroAgrupacion = 'dia';
    public ?string $filtroRango      = 'hoy';
    public ?string $filtroFechaDesde = null;
    public ?string $filtroFechaHasta = null;

    public function mount(): void
    {
        $hoy = today()->toDateString();
        $this->filtroFechaDesde = $hoy;
        $this->filtroFechaHasta = $hoy;
        $this->form->fill([
            'filtroAgrupacion' => 'dia',
            'filtroRango'      => 'hoy',
            'filtroFechaDesde' => $hoy,
            'filtroFechaHasta' => $hoy,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 2, 'md' => 5])->schema([

                Select::make('filtroAgrupacion')
                    ->label('Agrupar por')
                    ->options(['dia' => 'Día', 'mes' => 'Mes'])
                    ->native(false)
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

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
            || ($this->filtroAgrupacion ?? 'dia') !== 'dia';
    }

    public function limpiarFiltros(): void
    {
        $hoy = today()->toDateString();
        $this->filtroAgrupacion = 'dia';
        $this->filtroRango      = 'hoy';
        $this->filtroFechaDesde = $hoy;
        $this->filtroFechaHasta = $hoy;
        $this->form->fill([
            'filtroAgrupacion' => 'dia',
            'filtroRango'      => 'hoy',
            'filtroFechaDesde' => $hoy,
            'filtroFechaHasta' => $hoy,
        ]);
        $this->resetPage();
    }

    private function baseQuery(): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('ventas as v')
            ->where('v.empresa_id', Filament::getTenant()->id)
            ->where('v.estado', EstadoVenta::Completada->value);

        if (!empty($this->filtroFechaDesde)) {
            $q->whereDate('v.created_at', '>=', $this->filtroFechaDesde);
        }
        if (!empty($this->filtroFechaHasta)) {
            $q->whereDate('v.created_at', '<=', $this->filtroFechaHasta);
        }

        return $q;
    }

    public function getResumen(): array
    {
        $row = (clone $this->baseQuery())
            ->selectRaw("
                COUNT(*) as cantidad,
                COALESCE(SUM(v.total), 0) as ingresos_brutos,
                COALESCE(SUM(v.total - v.igv), 0) as ventas_netas,
                COALESCE(SUM(v.costo_total), 0) as costo_total,
                COALESCE(SUM(v.total - v.igv - v.costo_total), 0) as utilidad_bruta
            ")
            ->first();

        return [
            'cantidad'       => (int) ($row->cantidad ?? 0),
            'ingresosBrutos' => (float) ($row->ingresos_brutos ?? 0),
            'ventasNetas'    => (float) ($row->ventas_netas ?? 0),
            'costoTotal'     => (float) ($row->costo_total ?? 0),
            'utilidadBruta'  => (float) ($row->utilidad_bruta ?? 0),
        ];
    }

    public function getPeriodos(): LengthAwarePaginator
    {
        $agrupacion = $this->filtroAgrupacion ?? 'dia';
        $groupExpr  = $agrupacion === 'mes'
            ? "DATE_FORMAT(v.created_at, '%Y-%m')"
            : "DATE(v.created_at)";

        return (clone $this->baseQuery())
            ->selectRaw("
                {$groupExpr} as periodo,
                COUNT(*) as cantidad,
                COALESCE(SUM(v.total), 0) as ingresos,
                COALESCE(SUM(v.igv), 0) as igv,
                COALESCE(SUM(v.costo_total), 0) as costo,
                COALESCE(SUM(v.total - v.igv - v.costo_total), 0) as utilidad
            ")
            ->groupByRaw($groupExpr)
            ->orderByRaw("{$groupExpr} DESC")
            ->paginate(31);
    }
}
