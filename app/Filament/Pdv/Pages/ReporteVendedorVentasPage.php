<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use App\Models\Serie;
use App\Services\ReporteVendedorVentasExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use UnitEnum;

class ReporteVendedorVentasPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.reporte-vendedor-ventas';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 4;
    protected static ?string $title = 'Ventas del vendedor';
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return Filament::getTenant()->tieneModulo('reporte_vendedores')
            && (auth()->user()?->can('caja.reporte_vendedores') ?? false);
    }

    #[Url] public ?int    $vendedorId     = null;
    #[Url] public ?string $vendedorNombre = null;
    #[Url] public ?string $fechaDesde     = null;
    #[Url] public ?string $fechaHasta     = null;

    public ?string $filtroSerie       = null;
    public ?string $filtroCorrelativo = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getBreadcrumbs(): array
    {
        return [
            ReporteVendedoresPage::getUrl() => 'Vendedores',
            $this->vendedorNombre ?? 'Ventas',
        ];
    }

    // ── Filtros ───────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 2])->schema([

                Select::make('filtroSerie')
                    ->label('Serie')
                    ->placeholder('Todas las series')
                    ->options(fn() => Serie::where('empresa_id', Filament::getTenant()->id)
                        ->orderBy('serie')->pluck('serie', 'serie')->toArray())
                    ->native(false)->searchable()
                    ->live(),

                TextInput::make('filtroCorrelativo')
                    ->label('Correlativo')
                    ->placeholder('Ej: 00001')
                    ->live(debounce: 400),

            ]),
        ]);
    }

    public function limpiarFiltros(): void
    {
        $this->filtroSerie       = null;
        $this->filtroCorrelativo = null;
        $this->form->fill();
    }

    public function hayFiltros(): bool
    {
        return ! empty($this->filtroSerie) || ! empty($this->filtroCorrelativo);
    }

    // ── Query ─────────────────────────────────────────────────────────────────

    private function buildQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('ventas as v')
            ->join('series as s', 'v.serie_id', '=', 's.id')
            ->where('v.empresa_id', Filament::getTenant()->id)
            ->where('v.estado', EstadoVenta::Completada->value)
            ->where('v.vendedor_id', $this->vendedorId)
            ->when($this->fechaDesde,        fn($q) => $q->whereDate('v.created_at', '>=', $this->fechaDesde))
            ->when($this->fechaHasta,        fn($q) => $q->whereDate('v.created_at', '<=', $this->fechaHasta))
            ->when($this->filtroSerie,       fn($q) => $q->where('s.serie', $this->filtroSerie))
            ->when($this->filtroCorrelativo, fn($q) => $q->where('v.correlativo', 'like', $this->filtroCorrelativo . '%'))
            ->selectRaw("
                v.id, s.serie, v.correlativo,
                v.cliente_nombre, v.total, v.igv, v.costo_total,
                v.monto_pagado, v.saldo_pendiente, v.estado_pago,
                v.total - v.igv - v.costo_total AS utilidad,
                v.created_at
            ");
    }

    // ── KPIs ──────────────────────────────────────────────────────────────────

    public function getResumen(): array
    {
        $row = DB::table('ventas as v')
            ->where('v.empresa_id', Filament::getTenant()->id)
            ->where('v.estado', EstadoVenta::Completada->value)
            ->where('v.vendedor_id', $this->vendedorId)
            ->when($this->fechaDesde, fn($q) => $q->whereDate('v.created_at', '>=', $this->fechaDesde))
            ->when($this->fechaHasta, fn($q) => $q->whereDate('v.created_at', '<=', $this->fechaHasta))
            ->selectRaw("
                COUNT(*) AS cantidad,
                COALESCE(SUM(v.monto_pagado), 0)                  AS cobrado,
                COALESCE(SUM(v.saldo_pendiente), 0)               AS credito_pendiente,
                COALESCE(SUM(v.total - v.igv - v.costo_total), 0) AS utilidad
            ")
            ->first();

        return [
            'cantidad'         => (int)   ($row->cantidad          ?? 0),
            'cobrado'          => (float) ($row->cobrado           ?? 0),
            'creditoPendiente' => (float) ($row->credito_pendiente ?? 0),
            'utilidad'         => (float) ($row->utilidad          ?? 0),
        ];
    }

    // ── Exportación ───────────────────────────────────────────────────────────

    private function getVentasParaExportar(): \Illuminate\Support\Collection
    {
        $sortCol = $this->getTableSortColumn() ?? 'v.created_at';
        $sortDir = $this->getTableSortDirection() ?? 'desc';

        return $this->buildQuery()->orderBy($sortCol, $sortDir)->get()
            ->map(fn($r) => (array) $r);
    }

    private function getFiltrosInfo(): array
    {
        $info = [];
        if ($this->fechaDesde) {
            $info['Desde'] = \Carbon\Carbon::parse($this->fechaDesde)->format('d/m/Y');
        }
        if ($this->fechaHasta) {
            $info['Hasta'] = \Carbon\Carbon::parse($this->fechaHasta)->format('d/m/Y');
        }
        if (! empty($this->filtroSerie)) {
            $info['Serie'] = $this->filtroSerie;
        }
        if (! empty($this->filtroCorrelativo)) {
            $info['Correlativo'] = $this->filtroCorrelativo;
        }
        return $info;
    }

    private function accionesExportacion(): array
    {
        return [
            Action::make('descargarExcel')
                ->label('Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(fn() => app(ReporteVendedorVentasExportService::class)->generarExcel(
                    $this->getVentasParaExportar(),
                    $this->getFiltrosInfo(),
                    $this->getResumen(),
                    Filament::getTenant(),
                    $this->vendedorNombre ?? 'Vendedor',
                )),

            Action::make('descargarPdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn() => app(ReporteVendedorVentasExportService::class)->generarPdf(
                    $this->getVentasParaExportar(),
                    $this->getFiltrosInfo(),
                    $this->getResumen(),
                    Filament::getTenant(),
                    $this->vendedorNombre ?? 'Vendedor',
                )),
        ];
    }

    // ── Tabla Filament ────────────────────────────────────────────────────────

    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model|array $record): string
    {
        return (string) (is_array($record) ? ($record['id'] ?? '') : $record->id);
    }

    public function table(Table $table): Table
    {
        $sortCol = $this->getTableSortColumn() ?? 'created_at';
        $sortDir = $this->getTableSortDirection() ?? 'desc';
        $perPage = is_numeric($this->tableRecordsPerPage ?? null) ? (int) $this->tableRecordsPerPage : 25;

        return $table
            ->records(fn() => $this->buildQuery()
                ->orderBy($sortCol, $sortDir)
                ->paginate($perPage)
                ->through(fn($r) => (array) $r)
            )
            ->toolbarActions($this->accionesExportacion())
            ->defaultSort('created_at', 'desc')
            ->columns([

                TextColumn::make('comprobante')
                    ->label('Comprobante')
                    ->state(fn($record): string => ($record['serie'] ?? '') . '-' . ($record['correlativo'] ?? ''))
                    ->weight('semibold')
                    ->searchable(false),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->state(fn($record): string => $record['created_at']
                        ? \Carbon\Carbon::parse($record['created_at'])->format('d/m/Y H:i')
                        : '—'
                    )
                    ->sortable()
                    ->color('gray'),

                TextColumn::make('cliente_nombre')
                    ->label('Cliente')
                    ->state(fn($record): string => $record['cliente_nombre'] ?: '—')
                    ->searchable(false),

                TextColumn::make('estado_pago')
                    ->label('Pago')
                    ->badge()
                    ->state(fn($record): string => ($record['estado_pago'] ?? '') === 'pendiente' ? 'Crédito' : 'Contado')
                    ->color(fn($record): string => ($record['estado_pago'] ?? '') === 'pendiente' ? 'warning' : 'success')
                    ->description(fn($record): ?string => ($record['estado_pago'] ?? '') === 'pendiente'
                        ? 'Pag. S/ ' . number_format((float)($record['monto_pagado'] ?? 0), 2)
                          . ' · Pend. S/ ' . number_format((float)($record['saldo_pendiente'] ?? 0), 2)
                        : null
                    ),

                TextColumn::make('total')
                    ->label('Total')
                    ->state(fn($record): string => 'S/ ' . number_format((float) ($record['total'] ?? 0), 2))
                    ->sortable()
                    ->alignEnd()
                    ->color('primary')
                    ->weight('semibold'),

                TextColumn::make('igv')
                    ->label('IGV')
                    ->state(fn($record): string => (float) ($record['igv'] ?? 0) > 0
                        ? 'S/ ' . number_format((float) $record['igv'], 2)
                        : '—'
                    )
                    ->sortable()
                    ->alignEnd()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('costo_total')
                    ->label('Costo')
                    ->state(fn($record): string => 'S/ ' . number_format((float) ($record['costo_total'] ?? 0), 2))
                    ->sortable()
                    ->alignEnd()
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('utilidad')
                    ->label('Utilidad')
                    ->state(fn($record): string => 'S/ ' . number_format((float) ($record['utilidad'] ?? 0), 2))
                    ->sortable()
                    ->alignEnd()
                    ->color(fn($record): string => (float) ($record['utilidad'] ?? 0) >= 0 ? 'success' : 'danger')
                    ->weight('semibold'),

            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Sin ventas')
            ->emptyStateDescription('No se encontraron ventas con los filtros seleccionados.')
            ->emptyStateIcon('heroicon-o-document-chart-bar');
    }
}
