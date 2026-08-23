<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use App\Services\ReporteVendedoresExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ReporteVendedoresPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.reporte-vendedores';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'Vendedores';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 7;
    protected static ?string $title = 'Reporte de Vendedores';

    public static function canAccess(): bool
    {
        return Filament::getTenant()->tieneModulo('reporte_vendedores')
            && (auth()->user()?->can('caja.reporte_vendedores') ?? false);
    }

    // ── Filtros ───────────────────────────────────────────────────────────────

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
            Grid::make(['default' => 1, 'sm' => 2, 'md' => 4])->schema([

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
                    ->live(),

                DatePicker::make('filtroFechaDesde')
                    ->label('Desde')->displayFormat('d/m/Y')
                    ->live()
                    ->hidden(fn() => $this->filtroRango !== 'personalizado'),

                DatePicker::make('filtroFechaHasta')
                    ->label('Hasta')->displayFormat('d/m/Y')
                    ->live()
                    ->hidden(fn() => $this->filtroRango !== 'personalizado'),

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
    }

    public function hayFiltros(): bool
    {
        return ! empty($this->filtroVendedor) || $this->filtroRango !== 'hoy';
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
    }

    // ── Query ─────────────────────────────────────────────────────────────────

    private function baseQuery(): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('ventas as v')
            ->join('users as u', 'v.vendedor_id', '=', 'u.id')
            ->where('v.empresa_id', Filament::getTenant()->id)
            ->where('v.estado', EstadoVenta::Completada->value);

        if (! empty($this->filtroFechaDesde)) {
            $q->whereDate('v.created_at', '>=', $this->filtroFechaDesde);
        }
        if (! empty($this->filtroFechaHasta)) {
            $q->whereDate('v.created_at', '<=', $this->filtroFechaHasta);
        }
        if (! empty($this->filtroVendedor)) {
            $q->where('v.vendedor_id', $this->filtroVendedor);
        }

        return $q;
    }

    private function buildQuery(): \Illuminate\Database\Query\Builder
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
            ->groupBy('v.vendedor_id', 'u.name');
    }

    // ── KPIs ──────────────────────────────────────────────────────────────────

    public function getResumen(): array
    {
        $row = (clone $this->baseQuery())
            ->selectRaw("
                COUNT(DISTINCT v.vendedor_id)                      AS total_vendedores,
                COUNT(*)                                           AS cantidad,
                COALESCE(SUM(v.total), 0)                         AS ingresos_brutos,
                COALESCE(SUM(v.total - v.igv), 0)                 AS ventas_netas,
                COALESCE(SUM(v.costo_total), 0)                   AS costo_total,
                COALESCE(SUM(v.total - v.igv - v.costo_total), 0) AS utilidad_bruta,
                COALESCE(SUM(v.monto_pagado), 0)                  AS cobrado,
                COALESCE(SUM(v.saldo_pendiente), 0)               AS credito_pendiente
            ")
            ->first();

        return [
            'totalVendedores'  => (int)   ($row->total_vendedores  ?? 0),
            'cantidad'         => (int)   ($row->cantidad           ?? 0),
            'ingresosBrutos'   => (float) ($row->ingresos_brutos    ?? 0),
            'cobrado'          => (float) ($row->cobrado            ?? 0),
            'costoTotal'       => (float) ($row->costo_total        ?? 0),
            'utilidadBruta'    => (float) ($row->utilidad_bruta     ?? 0),
            'creditoPendiente' => (float) ($row->credito_pendiente  ?? 0),
        ];
    }

    // ── Exportación ───────────────────────────────────────────────────────────

    private function getVendedoresParaExportar(): \Illuminate\Support\Collection
    {
        $sortCol = $this->getTableSortColumn() ?? 'ingresos';
        $sortDir = $this->getTableSortDirection() ?? 'desc';

        return $this->buildQuery()->orderBy($sortCol, $sortDir)->get()
            ->map(fn($r) => (array) $r);
    }

    private function getFiltrosInfo(): array
    {
        $info = [];
        $labels = ['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes', 'personalizado' => 'Personalizado'];
        $info['Período'] = $labels[$this->filtroRango] ?? $this->filtroRango;

        if (! empty($this->filtroFechaDesde)) {
            $info['Desde'] = \Carbon\Carbon::parse($this->filtroFechaDesde)->format('d/m/Y');
        }
        if (! empty($this->filtroFechaHasta)) {
            $info['Hasta'] = \Carbon\Carbon::parse($this->filtroFechaHasta)->format('d/m/Y');
        }
        if (! empty($this->filtroVendedor)) {
            $info['Vendedor'] = DB::table('users')->where('id', $this->filtroVendedor)->value('name') ?? $this->filtroVendedor;
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
                ->action(fn() => app(ReporteVendedoresExportService::class)->generarExcel(
                    $this->getVendedoresParaExportar(),
                    $this->getFiltrosInfo(),
                    $this->getResumen(),
                    Filament::getTenant(),
                )),

            Action::make('descargarPdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn() => app(ReporteVendedoresExportService::class)->generarPdf(
                    $this->getVendedoresParaExportar(),
                    $this->getFiltrosInfo(),
                    $this->getResumen(),
                    Filament::getTenant(),
                )),
        ];
    }

    // ── Tabla Filament ────────────────────────────────────────────────────────

    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model|array $record): string
    {
        return (string) (is_array($record) ? ($record['vendedor_id'] ?? '') : $record->vendedor_id);
    }

    public function table(Table $table): Table
    {
        $sortCol = $this->getTableSortColumn() ?? 'ingresos';
        $sortDir = $this->getTableSortDirection() ?? 'desc';
        $perPage = is_numeric($this->tableRecordsPerPage ?? null) ? (int) $this->tableRecordsPerPage : 25;

        return $table
            ->records(fn() => $this->buildQuery()
                ->orderBy($sortCol, $sortDir)
                ->paginate($perPage)
                ->through(fn($r) => (array) $r)
            )
            ->toolbarActions($this->accionesExportacion())
            ->defaultSort('ingresos', 'desc')
            ->columns([

                TextColumn::make('vendedor')
                    ->label('Vendedor')
                    ->sortable()
                    ->weight('semibold')
                    ->searchable(false),

                TextColumn::make('cantidad')
                    ->label('N° Ventas')
                    ->state(fn($record) => number_format((int) ($record['cantidad'] ?? 0)))
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('ingresos')
                    ->label('Total facturado')
                    ->state(fn($record) => 'S/ ' . number_format((float) ($record['ingresos'] ?? 0), 2))
                    ->sortable()
                    ->alignEnd()
                    ->color('primary')
                    ->weight('semibold'),

                TextColumn::make('cobrado')
                    ->label('Cobrado')
                    ->state(fn($record) => 'S/ ' . number_format((float) ($record['cobrado'] ?? 0), 2))
                    ->sortable()
                    ->alignEnd()
                    ->color('success'),

                TextColumn::make('credito_pendiente')
                    ->label('Crédito pend.')
                    ->state(fn($record): string => (float) ($record['credito_pendiente'] ?? 0) > 0
                        ? 'S/ ' . number_format((float) $record['credito_pendiente'], 2)
                        : '—'
                    )
                    ->sortable()
                    ->alignEnd()
                    ->color(fn($record): string => (float) ($record['credito_pendiente'] ?? 0) > 0 ? 'warning' : 'gray'),

                TextColumn::make('costo')
                    ->label('Costo')
                    ->state(fn($record) => 'S/ ' . number_format((float) ($record['costo'] ?? 0), 2))
                    ->sortable()
                    ->alignEnd()
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('utilidad')
                    ->label('Utilidad')
                    ->state(fn($record) => 'S/ ' . number_format((float) ($record['utilidad'] ?? 0), 2))
                    ->sortable()
                    ->alignEnd()
                    ->color(fn($record): string => (float) ($record['utilidad'] ?? 0) >= 0 ? 'success' : 'danger')
                    ->weight('semibold'),

                TextColumn::make('margen')
                    ->label('Margen %')
                    ->state(function ($record): string {
                        $i = (float) ($record['ingresos'] ?? 0);
                        if ($i <= 0) return '—';
                        return number_format((float) ($record['utilidad'] ?? 0) / $i * 100, 1) . '%';
                    })
                    ->badge()
                    ->color(function ($record): string {
                        $i = (float) ($record['ingresos'] ?? 0);
                        if ($i <= 0) return 'gray';
                        $m = (float) ($record['utilidad'] ?? 0) / $i * 100;
                        return match(true) {
                            $m >= 30 => 'success',
                            $m >= 10 => 'info',
                            $m > 0   => 'warning',
                            default  => 'danger',
                        };
                    })
                    ->alignEnd(),

                TextColumn::make('ultima_venta')
                    ->label('Última venta')
                    ->state(fn($record): string => $record['ultima_venta']
                        ? \Carbon\Carbon::parse($record['ultima_venta'])->format('d/m/Y')
                        : '—'
                    )
                    ->sortable()
                    ->alignEnd()
                    ->color('gray'),

            ])
            ->recordUrl(fn($record) => ReporteVendedorVentasPage::getUrl() . '?' . http_build_query([
                'vendedorId'     => $record['vendedor_id'] ?? '',
                'vendedorNombre' => $record['vendedor']    ?? '',
                'fechaDesde'     => $this->filtroFechaDesde,
                'fechaHasta'     => $this->filtroFechaHasta,
            ]))
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Sin vendedores')
            ->emptyStateDescription('No hay ventas registradas en el período seleccionado.')
            ->emptyStateIcon('heroicon-o-identification');
    }
}
