<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ReporteVentasPeriodoPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.reporte-ventas-periodo';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Ventas por período';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Ventas por período';

    public function getHeading(): string { return static::$title ?? ''; }

    protected function getHeaderWidgets(): array
    {
        return [\App\Filament\Pdv\Widgets\ReporteVentasPeriodoStatsWidget::class];
    }

    public function getWidgetData(): array
    {
        return [
            'statsData'  => $this->getResumen(),
            'sparksData' => $this->getSparklines(),
        ];
    }

    public static function canAccess(): bool { return Filament::getTenant()->tieneModulo('ventas_periodo') && (auth()->user()?->can('reportes.ventas_periodo') ?? false); }

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
            Section::make('Filtros')
                ->description('Filtra el reporte de ventas por período y agrupación.')
                ->columns(1)
                ->collapsible()
                ->collapsed(true)
                ->schema([
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

        $prev = $this->prevBaseQuery()
            ->selectRaw("
                COUNT(*) as cantidad,
                COALESCE(SUM(v.total), 0) as ingresos_brutos,
                COALESCE(SUM(v.total - v.igv - v.costo_total), 0) as utilidad_bruta
            ")
            ->first();

        $lbl = match($this->filtroRango ?? 'hoy') {
            'hoy'    => 'ayer',
            'semana' => 'sem. anterior',
            'mes'    => 'mes anterior',
            default  => 'período anterior',
        };

        return [
            'cantidad'       => (int)   ($row->cantidad       ?? 0),
            'ingresosBrutos' => (float) ($row->ingresos_brutos ?? 0),
            'ventasNetas'    => (float) ($row->ventas_netas    ?? 0),
            'costoTotal'     => (float) ($row->costo_total     ?? 0),
            'utilidadBruta'  => (float) ($row->utilidad_bruta  ?? 0),
            'tCantidad'      => $this->calcTrend((float)($row->cantidad ?? 0),       (float)($prev->cantidad       ?? 0)),
            'tIngresos'      => $this->calcTrend((float)($row->ingresos_brutos ?? 0),(float)($prev->ingresos_brutos ?? 0)),
            'tUtilidad'      => $this->calcTrend((float)($row->utilidad_bruta  ?? 0),(float)($prev->utilidad_bruta  ?? 0)),
            'labelAnterior'  => $lbl,
        ];
    }

    private function prevBaseQuery(): \Illuminate\Database\Query\Builder
    {
        $desde    = Carbon::parse($this->filtroFechaDesde ?? today()->toDateString());
        $hasta    = Carbon::parse($this->filtroFechaHasta ?? today()->toDateString());
        $dias     = max(1, $desde->diffInDays($hasta) + 1);
        $prevHasta = $desde->copy()->subDay();
        $prevDesde = $prevHasta->copy()->subDays($dias - 1);

        return DB::table('ventas as v')
            ->where('v.empresa_id', Filament::getTenant()->id)
            ->where('v.estado', EstadoVenta::Completada->value)
            ->whereDate('v.created_at', '>=', $prevDesde->toDateString())
            ->whereDate('v.created_at', '<=', $prevHasta->toDateString());
    }

    private function calcTrend(float $actual, float $anterior): ?array
    {
        if ($anterior == 0 && $actual == 0) return null;
        if ($anterior == 0) return ['pct' => null, 'arriba' => $actual > 0];
        $pct = (($actual - $anterior) / abs($anterior)) * 100;
        return ['pct' => round(abs($pct), 1), 'arriba' => $pct >= 0];
    }

    public function getSparklines(): array
    {
        $rows = DB::table('ventas as v')
            ->where('v.empresa_id', Filament::getTenant()->id)
            ->where('v.estado', EstadoVenta::Completada->value)
            ->whereDate('v.created_at', '>=', today()->subDays(6)->toDateString())
            ->selectRaw("DATE(v.created_at) as dia, COUNT(*) as cantidad, COALESCE(SUM(v.total),0) as ingresos, COALESCE(SUM(v.total-v.igv-v.costo_total),0) as utilidad")
            ->groupBy('dia')->orderBy('dia')->get()->keyBy('dia');
        $qty = []; $ing = []; $utl = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = today()->subDays($i)->toDateString(); $r = $rows->get($d);
            $qty[] = (int)   ($r?->cantidad  ?? 0);
            $ing[] = (float) ($r?->ingresos  ?? 0);
            $utl[] = (float) ($r?->utilidad  ?? 0);
        }
        return ['cantidad' => $qty, 'ingresos' => $ing, 'utilidad' => $utl];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int|string|null $recordsPerPage): LengthAwarePaginator {
                $perPage    = is_int($recordsPerPage) ? $recordsPerPage : 25;
                $agrupacion = $this->filtroAgrupacion ?? 'dia';
                $groupExpr  = $agrupacion === 'mes'
                    ? "DATE_FORMAT(v.created_at, '%Y-%m')"
                    : 'DATE(v.created_at)';

                return (clone $this->baseQuery())
                    ->selectRaw("
                        {$groupExpr} AS periodo,
                        COUNT(*) AS cantidad,
                        COALESCE(SUM(v.total), 0) AS ingresos,
                        COALESCE(SUM(v.igv), 0) AS igv,
                        COALESCE(SUM(v.costo_total), 0) AS costo,
                        COALESCE(SUM(v.total - v.igv - v.costo_total), 0) AS utilidad
                    ")
                    ->groupByRaw($groupExpr)
                    ->orderByRaw("{$groupExpr} DESC")
                    ->paginate($perPage, ['*'], 'page', $page)
                    ->through(fn($row) => (array) $row);
            })
            ->columns([
                TextColumn::make('periodo')
                    ->label(fn() => ($this->filtroAgrupacion ?? 'dia') === 'mes' ? 'Mes' : 'Fecha')
                    ->formatStateUsing(function ($state): string {
                        if (($this->filtroAgrupacion ?? 'dia') === 'mes') {
                            $meses = ['01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
                                      '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
                                      '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
                                      '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'];
                            [$year, $month] = explode('-', $state);
                            return ($meses[$month] ?? $month) . ' ' . $year;
                        }
                        return Carbon::parse($state)->format('d/m/Y');
                    })
                    ->url(fn($record): string => ReportePeriodoVentasPage::getUrl([
                        'periodo'    => $record['periodo'],
                        'agrupacion' => $this->filtroAgrupacion ?? 'dia',
                    ]))
                    ->weight('semibold')
                    ->color('primary'),

                TextColumn::make('cantidad')
                    ->label('N° Ventas')
                    ->numeric()
                    ->alignRight(),

                TextColumn::make('ingresos')
                    ->label('Total facturado')
                    ->formatStateUsing(fn($state): string => 'S/ ' . number_format((float) $state, 2))
                    ->alignRight(),

                TextColumn::make('igv')
                    ->label('IGV')
                    ->formatStateUsing(fn($state): string => 'S/ ' . number_format((float) $state, 2))
                    ->alignRight()
                    ->color('gray'),

                TextColumn::make('costo')
                    ->label('Costo')
                    ->formatStateUsing(fn($state): string => 'S/ ' . number_format((float) $state, 2))
                    ->alignRight()
                    ->color('warning'),

                TextColumn::make('utilidad')
                    ->label('Utilidad')
                    ->formatStateUsing(fn($state): string => 'S/ ' . number_format((float) $state, 2))
                    ->alignRight()
                    ->color(fn($state): string => (float) $state >= 0 ? 'success' : 'danger'),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Sin ventas')
            ->emptyStateDescription('No hay ventas en el período seleccionado.')
            ->emptyStateIcon('heroicon-o-calendar-days');
    }
}
