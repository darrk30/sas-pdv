<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use UnitEnum;

class ReporteGananciasPage extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected string $view = 'filament.pdv.pages.reporte-ganancias';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Reporte de Ganancias';
    protected static string|UnitEnum|null $navigationGroup = 'Caja';
    protected static ?int $navigationSort = 8;
    protected static ?string $title = 'Reporte de Ganancias';

    public static function canAccess(): bool { return auth()->user()?->can('caja.reporte_ganancias') ?? false; }

    public function getHeading(): string { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }

    public ?string $filtroFechaDesde = null;
    public ?string $filtroFechaHasta = null;
    public ?string $filtroVendedor   = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 3])->schema([

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

                Select::make('filtroVendedor')
                    ->label('Vendedor')
                    ->placeholder('Todos los vendedores')
                    ->options(fn() => User::whereHas('empresas', fn($q) => $q->where('empresa_id', Filament::getTenant()->id))
                        ->orderBy('name')->pluck('name', 'id')->toArray())
                    ->native(false)
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn() => $this->resetPage()),

            ]),
        ]);
    }

    public function hayFiltros(): bool
    {
        return ! empty($this->filtroFechaDesde)
            || ! empty($this->filtroFechaHasta)
            || ! empty($this->filtroVendedor);
    }

    public function limpiarFiltros(): void
    {
        $this->filtroFechaDesde = null;
        $this->filtroFechaHasta = null;
        $this->filtroVendedor   = null;
        $this->form->fill();
        $this->resetPage();
    }

    // ── Query base ────────────────────────────────────────────────────────────

    private function baseQuery(): Builder
    {
        $q = Venta::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', EstadoVenta::Completada->value);

        if (! empty($this->filtroFechaDesde)) {
            $q->whereDate('created_at', '>=', $this->filtroFechaDesde);
        }
        if (! empty($this->filtroFechaHasta)) {
            $q->whereDate('created_at', '<=', $this->filtroFechaHasta);
        }
        if (! empty($this->filtroVendedor)) {
            $q->where('vendedor_id', $this->filtroVendedor);
        }

        return $q;
    }

    // ── KPIs del encabezado ───────────────────────────────────────────────────

    public function getResumen(): array
    {
        $row = (clone $this->baseQuery())
            ->selectRaw("
                COUNT(*)                                                                                          AS cantidad,
                COALESCE(SUM(total), 0)                                                                           AS ingresos_brutos,
                COALESCE(SUM(total - igv), 0)                                                                     AS ventas_netas,
                COALESCE(SUM(costo_total), 0)                                                                     AS costo_total,
                COALESCE(SUM(CASE WHEN estado_pago = 'pendiente' THEN saldo_pendiente    ELSE 0 END), 0)          AS credito_pendiente,
                COALESCE(SUM(CASE WHEN estado_pago = 'pendiente' THEN (total - igv - costo_total) ELSE 0 END), 0) AS utilidad_en_riesgo
            ")
            ->first();

        $cantidad          = (int)   $row->cantidad;
        $ingresosBrutos    = (float) $row->ingresos_brutos;
        $ventasNetas       = (float) $row->ventas_netas;
        $costoTotal        = (float) $row->costo_total;
        $creditoPendiente  = (float) $row->credito_pendiente;
        $utilidadEnRiesgo  = (float) $row->utilidad_en_riesgo;
        $utilidadBruta     = $ventasNetas - $costoTotal;
        $utilidadRealizada = $utilidadBruta - $utilidadEnRiesgo;
        $margenPct         = $ventasNetas > 0
            ? round($utilidadRealizada / $ventasNetas * 100, 1)
            : 0.0;

        return compact(
            'cantidad', 'ingresosBrutos', 'ventasNetas', 'costoTotal',
            'utilidadBruta', 'utilidadRealizada', 'creditoPendiente', 'utilidadEnRiesgo',
            'margenPct'
        );
    }

    // ── Top productos más rentables ───────────────────────────────────────────

    public function getTopProductos(): \Illuminate\Support\Collection
    {
        $empresaId = Filament::getTenant()->id;

        $q = VentaDetalle::whereHas('venta', function ($sub) use ($empresaId) {
                $sub->where('empresa_id', $empresaId)
                    ->where('estado', EstadoVenta::Completada->value);
                if (! empty($this->filtroFechaDesde)) {
                    $sub->whereDate('created_at', '>=', $this->filtroFechaDesde);
                }
                if (! empty($this->filtroFechaHasta)) {
                    $sub->whereDate('created_at', '<=', $this->filtroFechaHasta);
                }
                if (! empty($this->filtroVendedor)) {
                    $sub->where('vendedor_id', $this->filtroVendedor);
                }
            })
            ->selectRaw('
                descripcion,
                COUNT(*)                                    AS veces,
                COALESCE(SUM(cantidad), 0)                  AS total_qty,
                COALESCE(SUM(total - costo_total), 0)       AS utilidad,
                COALESCE(SUM(total), 0)                     AS ingresos
            ')
            ->groupBy('descripcion')
            ->orderByDesc('utilidad')
            ->limit(8)
            ->get();

        return $q;
    }

    // ── Tabla paginada ────────────────────────────────────────────────────────

    public function getVentas(): LengthAwarePaginator
    {
        return (clone $this->baseQuery())
            ->with(['serie', 'vendedor:id,name'])
            ->selectRaw('ventas.*, (total - igv) AS venta_neta')
            ->orderBy('created_at', 'desc')
            ->paginate(30);
    }
}
