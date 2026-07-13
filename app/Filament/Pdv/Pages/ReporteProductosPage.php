<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use App\Models\Categoria;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use UnitEnum;

class ReporteProductosPage extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.reporte-productos';
    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel  = 'Productos más vendidos';
    protected static string|UnitEnum|null $navigationGroup  = 'Reportes';
    protected static ?int $navigationSort = 4;
    protected static ?string $title            = 'Productos más vendidos';

    public static function canAccess(): bool { return auth()->user()?->can('reportes.productos') ?? false; }


    // ── Filtros ───────────────────────────────────────────────────────────────

    public ?string $filtroFechaDesde = null;
    public ?string $filtroFechaHasta = null;
    public ?string $filtroCategoria  = null;
    public ?string $filtroOrden      = 'qty';   // qty | ingresos | utilidad | margen

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 4])->schema([

                DatePicker::make('filtroFechaDesde')
                    ->label('Desde')->displayFormat('d/m/Y')
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

                DatePicker::make('filtroFechaHasta')
                    ->label('Hasta')->displayFormat('d/m/Y')
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

                Select::make('filtroCategoria')
                    ->label('Categoría')
                    ->placeholder('Todas las categorías')
                    ->options(fn() => Categoria::where('empresa_id', Filament::getTenant()->id)
                        ->orderBy('nombre')->pluck('nombre', 'id')->toArray())
                    ->native(false)->searchable()
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

                Select::make('filtroOrden')
                    ->label('Ordenar por')
                    ->options([
                        'qty'      => 'Unidades vendidas',
                        'ingresos' => 'Ventas (S/)',
                        'utilidad' => 'Utilidad (S/)',
                    ])
                    ->native(false)
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

            ]),
        ]);
    }

    public function hayFiltros(): bool
    {
        return ! empty($this->filtroFechaDesde)
            || ! empty($this->filtroFechaHasta)
            || ! empty($this->filtroCategoria);
    }

    public function limpiarFiltros(): void
    {
        $this->filtroFechaDesde = null;
        $this->filtroFechaHasta = null;
        $this->filtroCategoria  = null;
        $this->filtroOrden      = 'qty';
        $this->form->fill(['filtroOrden' => 'qty']);
        $this->resetPage();
    }

    // ── Query base ────────────────────────────────────────────────────────────

    private function baseQuery(): \Illuminate\Database\Query\Builder
    {
        $empresaId = Filament::getTenant()->id;

        $q = DB::table('venta_detalles as vd')
            ->join('ventas as v', 'vd.venta_id', '=', 'v.id')
            ->leftJoin('productos as p', 'vd.producto_id', '=', 'p.id')
            ->leftJoin('categorias as c', 'p.categoria_id', '=', 'c.id')
            ->where('v.empresa_id', $empresaId)
            ->where('v.estado', EstadoVenta::Completada->value)
            ->where('vd.precio_unitario', '>', 0);

        if (! empty($this->filtroFechaDesde)) {
            $q->whereDate('v.created_at', '>=', $this->filtroFechaDesde);
        }
        if (! empty($this->filtroFechaHasta)) {
            $q->whereDate('v.created_at', '<=', $this->filtroFechaHasta);
        }
        if (! empty($this->filtroCategoria)) {
            $q->where('p.categoria_id', $this->filtroCategoria);
        }

        return $q;
    }

    // ── Tabla paginada ────────────────────────────────────────────────────────

    public function getProductos(): LengthAwarePaginator
    {
        $orderCol = match ($this->filtroOrden ?? 'qty') {
            'ingresos' => 'ingresos',
            'utilidad' => 'utilidad',
            default    => 'qty',
        };

        return (clone $this->baseQuery())
            ->leftJoin('unidades_medidas as u', 'p.unidad_medida_id', '=', 'u.id')
            ->selectRaw("
                vd.descripcion,
                COALESCE(c.nombre, 'Sin categoría')          AS categoria,
                COALESCE(u.simbolo, 'und')                   AS unidad,
                COALESCE(SUM(vd.cantidad), 0)                AS qty,
                COALESCE(SUM(vd.total), 0)                   AS ingresos,
                COALESCE(SUM(vd.costo_total), 0)             AS costo,
                COALESCE(SUM(vd.total - vd.costo_total), 0)  AS utilidad
            ")
            ->groupBy('vd.descripcion', 'categoria', 'unidad')
            ->orderByDesc($orderCol)
            ->paginate(50);
    }
}
