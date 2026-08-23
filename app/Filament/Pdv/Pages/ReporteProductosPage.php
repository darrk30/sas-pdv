<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use App\Models\Categoria;
use App\Models\VentaDetalle;
use App\Services\ReporteProductosExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ReporteProductosPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.reporte-productos';
    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel  = 'Productos más vendidos';
    protected static string|UnitEnum|null $navigationGroup  = 'Reportes';
    protected static ?int $navigationSort = 4;
    protected static ?string $title = 'Productos más vendidos';

    public static function canAccess(): bool
    {
        return Filament::getTenant()->tieneModulo('reporte_productos')
            && (auth()->user()?->can('reportes.productos') ?? false);
    }

    // ── Filtros ───────────────────────────────────────────────────────────────

    public ?string $filtroFechaDesde = null;
    public ?string $filtroFechaHasta = null;
    public ?string $filtroCategoria  = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 3])->schema([

                DateTimePicker::make('filtroFechaDesde')
                    ->label('Desde')
                    ->displayFormat('d/m/Y H:i')
                    ->format('Y-m-d H:i:s')
                    ->seconds(false)
                    ->live(),

                DateTimePicker::make('filtroFechaHasta')
                    ->label('Hasta')
                    ->displayFormat('d/m/Y H:i')
                    ->format('Y-m-d H:i:s')
                    ->seconds(false)
                    ->live(),

                Select::make('filtroCategoria')
                    ->label('Categoría')
                    ->placeholder('Todas las categorías')
                    ->options(fn() => Categoria::where('empresa_id', Filament::getTenant()->id)
                        ->orderBy('nombre')->pluck('nombre', 'id')->toArray())
                    ->native(false)
                    ->searchable()
                    ->live(),

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
        $this->form->fill();
    }

    // ── Query base ────────────────────────────────────────────────────────────

    // ── Exportación ───────────────────────────────────────────────────────────

    private function accionesExportacion(): array
    {
        return [
            Action::make('descargarPdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(function () {
                    return app(ReporteProductosExportService::class)->generarPdf(
                        $this->getProductosParaExportar(),
                        $this->getFiltrosInfo(),
                        $this->getColumnasVisibles(),
                        $this->getResumen(),
                        Filament::getTenant(),
                    );
                }),
        ];
    }

    private function getProductosParaExportar(): \Illuminate\Support\Collection
    {
        return $this->buildQuery()
            ->orderBy($this->getTableSortColumn() ?? 'qty', $this->getTableSortDirection() ?? 'desc')
            ->get();
    }

    private function getFiltrosInfo(): array
    {
        $info = [];
        if (! empty($this->filtroFechaDesde)) {
            $info['Desde'] = \Illuminate\Support\Carbon::parse($this->filtroFechaDesde)->format('d/m/Y H:i');
        }
        if (! empty($this->filtroFechaHasta)) {
            $info['Hasta'] = \Illuminate\Support\Carbon::parse($this->filtroFechaHasta)->format('d/m/Y H:i');
        }
        if (! empty($this->filtroCategoria)) {
            $info['Categoría'] = Categoria::find($this->filtroCategoria)?->nombre ?? $this->filtroCategoria;
        }
        return $info;
    }

    private function getColumnasVisibles(): array
    {
        if (! property_exists($this, 'tableColumns')) {
            return [];
        }
        $visible = [];
        foreach ($this->tableColumns as $item) {
            if (($item['type'] ?? '') === 'column' && ($item['isToggled'] ?? false)) {
                $visible[] = $item['name'];
            }
        }
        return $visible;
    }

    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model|array $record): string
    {
        $desc = is_array($record) ? ($record['descripcion'] ?? '') : $record->descripcion;
        $cat  = is_array($record) ? ($record['categoria'] ?? '')   : $record->categoria;
        return md5($desc . '|' . $cat);
    }

    private function buildQuery(): Builder
    {
        $empresaId = Filament::getTenant()->id;

        return VentaDetalle::query()
            ->join('ventas as v', 'venta_detalles.venta_id', '=', 'v.id')
            ->leftJoin('productos as p', 'venta_detalles.producto_id', '=', 'p.id')
            ->leftJoin('categorias as c', 'p.categoria_id', '=', 'c.id')
            ->leftJoin('unidades_medidas as u', 'p.unidad_medida_id', '=', 'u.id')
            ->selectRaw("
                venta_detalles.descripcion,
                COALESCE(c.nombre, 'Sin categoría')                              AS categoria,
                COALESCE(ANY_VALUE(u.simbolo), 'und')                            AS unidad,
                COALESCE(SUM(venta_detalles.cantidad), 0)                        AS qty,
                COALESCE(SUM(venta_detalles.total), 0)                           AS ingresos,
                COALESCE(SUM(venta_detalles.costo_total), 0)                     AS costo,
                COALESCE(SUM(venta_detalles.total - venta_detalles.costo_total), 0) AS utilidad
            ")
            ->where('v.empresa_id', $empresaId)
            ->where('v.estado', EstadoVenta::Completada->value)
            ->where('venta_detalles.precio_unitario', '>', 0)
            ->when(! empty($this->filtroFechaDesde), fn ($q) => $q->where('v.created_at', '>=', $this->filtroFechaDesde))
            ->when(! empty($this->filtroFechaHasta), fn ($q) => $q->where('v.created_at', '<=', $this->filtroFechaHasta))
            ->when(! empty($this->filtroCategoria),  fn ($q) => $q->where('p.categoria_id', $this->filtroCategoria))
            ->groupBy('venta_detalles.descripcion', DB::raw("COALESCE(c.nombre, 'Sin categoría')"));
    }

    // ── KPIs encabezado ───────────────────────────────────────────────────────

    public function getResumen(): array
    {
        $row = DB::table('venta_detalles as vd')
            ->join('ventas as v', 'vd.venta_id', '=', 'v.id')
            ->leftJoin('productos as p', 'vd.producto_id', '=', 'p.id')
            ->where('v.empresa_id', Filament::getTenant()->id)
            ->where('v.estado', EstadoVenta::Completada->value)
            ->where('vd.precio_unitario', '>', 0)
            ->when(! empty($this->filtroFechaDesde), fn ($q) => $q->where('v.created_at', '>=', $this->filtroFechaDesde))
            ->when(! empty($this->filtroFechaHasta), fn ($q) => $q->where('v.created_at', '<=', $this->filtroFechaHasta))
            ->when(! empty($this->filtroCategoria),  fn ($q) => $q->where('p.categoria_id', $this->filtroCategoria))
            ->selectRaw("
                COUNT(DISTINCT vd.descripcion)          AS productos,
                COALESCE(SUM(vd.cantidad), 0)           AS unidades,
                COALESCE(SUM(vd.total), 0)              AS ingresos,
                COALESCE(SUM(vd.costo_total), 0)        AS costo,
                COALESCE(SUM(vd.total - vd.costo_total), 0) AS utilidad
            ")
            ->first();

        $ingresos  = (float) $row->ingresos;
        $utilidad  = (float) $row->utilidad;
        $margenPct = $ingresos > 0 ? round($utilidad / $ingresos * 100, 1) : 0.0;

        return [
            'productos' => (int)   $row->productos,
            'unidades'  => (float) $row->unidades,
            'ingresos'  => $ingresos,
            'costo'     => (float) $row->costo,
            'utilidad'  => $utilidad,
            'margenPct' => $margenPct,
        ];
    }

    // ── Tabla Filament ────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        // Usamos ->records() para pasar el paginador directamente y evitar que
        // Filament agregue "ORDER BY venta_detalles.id" (tiebreaker) que es
        // inválido en queries con GROUP BY bajo ONLY_FULL_GROUP_BY.
        $sortCol = $this->getTableSortColumn() ?? 'qty';
        $sortDir = $this->getTableSortDirection() ?? 'desc';
        $perPage = is_numeric($this->tableRecordsPerPage ?? null) ? (int) $this->tableRecordsPerPage : 25;

        return $table
            ->records(fn () => $this->buildQuery()
                ->orderBy($sortCol, $sortDir)
                ->paginate($perPage)
            )
            ->toolbarActions($this->accionesExportacion())
            ->defaultSort('qty', 'desc')
            ->columns([

                TextColumn::make('descripcion')
                    ->label('Producto')
                    ->searchable(false)
                    ->sortable()
                    ->weight('medium')
                    ->description(fn ($record): string => $record->categoria)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('categoria')
                    ->label('Categoría')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('qty')
                    ->label('Unidades')
                    ->state(fn ($record): string => number_format((float) $record->qty, 2) . ' ' . $record->unidad)
                    ->sortable()
                    ->alignEnd()
                    ->weight('semibold')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('ingresos')
                    ->label('Ventas')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd()
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('costo')
                    ->label('Costo')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd()
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('utilidad')
                    ->label('Utilidad')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd()
                    ->color(fn ($record): string => (float) $record->utilidad >= 0 ? 'success' : 'danger')
                    ->weight('semibold')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('margen')
                    ->label('Margen %')
                    ->state(function ($record): string {
                        $i = (float) $record->ingresos;
                        if ($i <= 0) return '—';
                        return number_format((float) $record->utilidad / $i * 100, 1) . '%';
                    })
                    ->badge()
                    ->color(function ($record): string {
                        $i = (float) $record->ingresos;
                        if ($i <= 0) return 'gray';
                        $m = (float) $record->utilidad / $i * 100;
                        return match (true) {
                            $m >= 30 => 'success',
                            $m >= 10 => 'info',
                            $m > 0   => 'warning',
                            default  => 'danger',
                        };
                    })
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: false),

            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Sin ventas')
            ->emptyStateDescription('No hay productos vendidos para los filtros seleccionados.')
            ->emptyStateIcon('heroicon-o-chart-bar');
    }
}
