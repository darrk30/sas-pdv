<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use App\Filament\Pdv\Widgets\ReporteProductosStatsWidget;
use App\Models\Categoria;
use App\Models\VentaDetalle;
use App\Services\ReporteProductosExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReporteProductosPage extends Page implements HasTable
{
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.reporte-productos';
    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel  = 'Productos más vendidos';
    protected static string|UnitEnum|null $navigationGroup  = 'Reportes';
    protected static ?int $navigationSort = 4;
    protected static ?string $title = 'Productos más vendidos';

    public function getHeading(): string
    {
        return 'Productos más vendidos';
    }

    public static function canAccess(): bool
    {
        return Filament::getTenant()->tieneModulo('reporte_productos')
            && (auth()->user()?->can('reportes.productos') ?? false);
    }

    // ── Widgets ────────────────────────────────────────────────────────────────

    protected function getHeaderWidgets(): array
    {
        return [ReporteProductosStatsWidget::class];
    }

    public function getWidgetData(): array
    {
        return [
            'desde'       => $this->filterDesde() ?? now()->startOfMonth()->format('Y-m-d'),
            'hasta'       => $this->filterHasta() ?? now()->format('Y-m-d'),
            'categoriaId' => $this->filterCategoria() ?? '',
        ];
    }

    public function updatedTableFilters(): void
    {
        $this->dispatch('reporte-productos-filtros',
            desde:       $this->filterDesde()    ?? now()->startOfMonth()->format('Y-m-d'),
            hasta:       $this->filterHasta()    ?? now()->format('Y-m-d'),
            categoriaId: $this->filterCategoria() ?? '',
        );
    }

    // ── Lectura de filtros activos ─────────────────────────────────────────────

    private function filterDesde(): ?string
    {
        $filters = $this->tableFilters ?? [];
        return ($filters['fecha']['desde'] ?? null) ?: null;
    }

    private function filterHasta(): ?string
    {
        $filters = $this->tableFilters ?? [];
        return ($filters['fecha']['hasta'] ?? null) ?: null;
    }

    private function filterCategoria(): ?string
    {
        $filters = $this->tableFilters ?? [];
        return ($filters['categoria']['value'] ?? null) ?: null;
    }

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
                        [],
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
        if ($desde = $this->filterDesde()) {
            $info['Desde'] = \Carbon\Carbon::parse($desde)->format('d/m/Y');
        }
        if ($hasta = $this->filterHasta()) {
            $info['Hasta'] = \Carbon\Carbon::parse($hasta)->format('d/m/Y');
        }
        if ($catId = $this->filterCategoria()) {
            $info['Categoría'] = Categoria::find($catId)?->nombre ?? $catId;
        }
        return $info;
    }

    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model|array $record): string
    {
        $desc = is_array($record) ? ($record['descripcion'] ?? '') : $record->descripcion;
        $cat  = is_array($record) ? ($record['categoria'] ?? '')   : $record->categoria;
        return md5($desc . '|' . $cat);
    }

    // ── Query base ────────────────────────────────────────────────────────────

    private function buildQuery(): Builder
    {
        $empresaId  = Filament::getTenant()->id;
        $desde      = $this->filterDesde();
        $hasta      = $this->filterHasta();
        $categoriaId = $this->filterCategoria();

        return VentaDetalle::query()
            ->join('ventas as v', 'venta_detalles.venta_id', '=', 'v.id')
            ->leftJoin('productos as p', 'venta_detalles.producto_id', '=', 'p.id')
            ->leftJoin('categorias as c', 'p.categoria_id', '=', 'c.id')
            ->leftJoin('unidades_medidas as u', 'p.unidad_medida_id', '=', 'u.id')
            ->selectRaw("
                MD5(CONCAT(venta_detalles.descripcion, '|', COALESCE(ANY_VALUE(c.nombre), 'Sin categoría'))) AS id,
                venta_detalles.descripcion,
                COALESCE(ANY_VALUE(c.nombre), 'Sin categoría')                   AS categoria,
                COALESCE(ANY_VALUE(u.simbolo), 'und')                            AS unidad,
                COALESCE(SUM(venta_detalles.cantidad), 0)                        AS qty,
                COALESCE(SUM(venta_detalles.total), 0)                           AS ingresos,
                COALESCE(SUM(venta_detalles.costo_total), 0)                     AS costo,
                COALESCE(SUM(venta_detalles.total - venta_detalles.costo_total), 0) AS utilidad
            ")
            ->where('v.empresa_id', $empresaId)
            ->where('v.estado', EstadoVenta::Completada->value)
            ->where('venta_detalles.precio_unitario', '>', 0)
            ->when($desde,       fn ($q) => $q->where('v.fecha_emision', '>=', $desde))
            ->when($hasta,       fn ($q) => $q->where('v.fecha_emision', '<=', $hasta))
            ->when($categoriaId, fn ($q) => $q->where('p.categoria_id', $categoriaId))
            ->groupBy('venta_detalles.descripcion', 'p.categoria_id');
    }

    // ── Tabla Filament ────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $sortColumn, ?string $sortDirection, int|string $recordsPerPage, int|string $page) {
                $perPage     = is_numeric($recordsPerPage) ? (int) $recordsPerPage : 25;
                $currentPage = is_numeric($page) ? (int) $page : 1;

                return $this->buildQuery()
                    ->orderBy($sortColumn ?? 'qty', $sortDirection ?? 'desc')
                    ->paginate($perPage, ['*'], 'tablePage', $currentPage);
            })
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
            ->filters([
                Filter::make('fecha')
                    ->label('Período')
                    ->schema([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->displayFormat('d/m/Y')
                            ->default(now()->startOfMonth()),
                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->displayFormat('d/m/Y')
                            ->default(now()),
                    ])
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (! empty($data['desde'])) {
                            $indicators[] = 'Desde ' . \Carbon\Carbon::parse($data['desde'])->format('d/m/Y');
                        }
                        if (! empty($data['hasta'])) {
                            $indicators[] = 'Hasta ' . \Carbon\Carbon::parse($data['hasta'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),

                SelectFilter::make('categoria')
                    ->label('Categoría')
                    ->options(fn () => Categoria::where('empresa_id', Filament::getTenant()->id)
                        ->orderBy('nombre')->pluck('nombre', 'id')->toArray())
                    ->placeholder('Todas las categorías')
                    ->searchable(),
            ])
            ->filtersFormColumns(3)
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Sin ventas')
            ->emptyStateDescription('No hay productos vendidos para los filtros seleccionados.')
            ->emptyStateIcon('heroicon-o-chart-bar');
    }
}
