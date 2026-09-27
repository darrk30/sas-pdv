<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use App\Models\Serie;
use App\Models\Venta;
use BackedEnum;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use UnitEnum;

class ReportePeriodoVentasPage extends Page implements HasTable
{
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.reporte-periodo-ventas';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Ventas del período';
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool { return Filament::getTenant()->tieneModulo('reporte_ventas') && (auth()->user()?->can('reportes.ventas_periodo') ?? false); }

    protected function getHeaderWidgets(): array
    {
        return [\App\Filament\Pdv\Widgets\ReportePeriodoVentasStatsWidget::class];
    }

    public function getWidgetData(): array
    {
        $resumen = $this->getResumen();
        $resumen['periodoLabel'] = $this->getPeriodoLabel();
        return ['statsData' => $resumen];
    }

    #[Url]
    public ?string $periodo = null;
    #[Url]
    public ?string $agrupacion = 'dia';

    public function getBreadcrumbs(): array
    {
        return [
            ReporteVentasPeriodoPage::getUrl() => 'Ventas por período',
            $this->getPeriodoLabel(),
        ];
    }

    public function getPeriodoLabel(): string
    {
        if (!$this->periodo) {
            return 'Detalle';
        }

        if ($this->agrupacion === 'mes') {
            $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril',
                      '05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto',
                      '09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
            [$year, $month] = explode('-', $this->periodo);
            return ($meses[$month] ?? $month) . ' ' . $year;
        }

        return Carbon::parse($this->periodo)->format('d/m/Y');
    }

    public function getResumen(): array
    {
        $q = Venta::query()
            ->where('empresa_id', Filament::getTenant()->id)
            ->where('estado', EstadoVenta::Completada->value);

        if ($this->periodo) {
            if ($this->agrupacion === 'mes') {
                $q->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$this->periodo]);
            } else {
                $q->whereDate('created_at', $this->periodo);
            }
        }

        $row = $q->selectRaw("
                COUNT(*) AS cantidad,
                COALESCE(SUM(monto_pagado), 0)                  AS cobrado,
                COALESCE(SUM(saldo_pendiente), 0)               AS credito_pendiente,
                COALESCE(SUM(total - igv - costo_total), 0)     AS utilidad
            ")
            ->first();

        return [
            'cantidad'         => (int) ($row->cantidad ?? 0),
            'cobrado'          => (float) ($row->cobrado ?? 0),
            'creditoPendiente' => (float) ($row->credito_pendiente ?? 0),
            'utilidad'         => (float) ($row->utilidad ?? 0),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->buildVentasQuery())
            ->filters([
                Filter::make('serie')
                    ->form([
                        \Filament\Forms\Components\Select::make('serie')
                            ->label('Serie')
                            ->placeholder('Todas las series')
                            ->options(fn() => \App\Models\Serie::where('empresa_id', \Filament\Facades\Filament::getTenant()->id)
                                ->orderBy('serie')->pluck('serie', 'serie')->toArray())
                            ->native(false)
                            ->searchable(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when($data['serie'] ?? null, fn($q, $v) => $q->where('s.serie', $v))
                    )
                    ->indicateUsing(fn (array $data): ?string =>
                        ($data['serie'] ?? null) ? 'Serie: ' . $data['serie'] : null
                    ),

                Filter::make('correlativo')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('correlativo')
                            ->label('Correlativo')
                            ->placeholder('Ej: 00001'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when($data['correlativo'] ?? null, fn($q, $v) => $q->where('ventas.correlativo', 'like', $v . '%'))
                    )
                    ->indicateUsing(fn (array $data): ?string =>
                        ($data['correlativo'] ?? null) ? 'Correlativo: ' . $data['correlativo'] : null
                    ),
            ])
            ->columns([
                TextColumn::make('serie')
                    ->label('Comprobante')
                    ->formatStateUsing(fn($state, $record): string => "{$state}-{$record->correlativo}")
                    ->fontFamily('mono')
                    ->weight('semibold'),

                TextColumn::make('created_at')
                    ->label('Fecha / Hora')
                    ->formatStateUsing(fn($state): string =>
                        Carbon::parse($state)->format('d/m/Y') . "\n" . Carbon::parse($state)->format('H:i')
                    )
                    ->wrap(),

                TextColumn::make('cliente_nombre')
                    ->label('Cliente')
                    ->formatStateUsing(fn($state): string => $state ?: '—')
                    ->wrap()
                    ->limit(30),

                TextColumn::make('vendedor')
                    ->label('Vendedor')
                    ->color('gray')
                    ->limit(20),

                TextColumn::make('estado_pago')
                    ->label('Pago')
                    ->badge()
                    ->color(fn($state): string => match((string) $state) {
                        'pendiente' => 'warning',
                        'parcial'   => 'info',
                        default     => 'success',
                    })
                    ->formatStateUsing(fn($state): string => match((string) $state) {
                        'pendiente' => 'Crédito',
                        'parcial'   => 'Parcial',
                        default     => 'Contado',
                    })
                    ->description(fn($record): ?string =>
                        in_array($record->estado_pago, ['pendiente', 'parcial'])
                            ? 'S/ ' . number_format((float) $record->saldo_pendiente, 2)
                            : null
                    ),

                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn($state): string => 'S/ ' . number_format((float) $state, 2))
                    ->alignRight(),

                TextColumn::make('igv')
                    ->label('IGV')
                    ->formatStateUsing(fn($state): string =>
                        (float) $state > 0 ? 'S/ ' . number_format((float) $state, 2) : '—'
                    )
                    ->alignRight()
                    ->color('gray'),

                TextColumn::make('costo_total')
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
            ->emptyStateDescription('No se encontraron ventas con los filtros seleccionados.')
            ->emptyStateIcon('heroicon-o-shopping-cart');
    }

    private function buildVentasQuery(): Builder
    {
        return Venta::query()
            ->join('series as s', 'ventas.serie_id', '=', 's.id')
            ->join('users as u', 'ventas.vendedor_id', '=', 'u.id')
            ->selectRaw("
                ventas.id,
                s.serie,
                ventas.correlativo,
                ventas.cliente_nombre,
                ventas.total,
                ventas.igv,
                ventas.costo_total,
                ventas.monto_pagado,
                ventas.saldo_pendiente,
                ventas.estado_pago,
                ventas.total - ventas.igv - ventas.costo_total AS utilidad,
                u.name AS vendedor,
                ventas.created_at
            ")
            ->where('ventas.empresa_id', Filament::getTenant()->id)
            ->where('ventas.estado', EstadoVenta::Completada->value)
            ->when($this->periodo, function ($q) {
                if ($this->agrupacion === 'mes') {
                    $q->whereRaw("DATE_FORMAT(ventas.created_at, '%Y-%m') = ?", [$this->periodo]);
                } else {
                    $q->whereDate('ventas.created_at', $this->periodo);
                }
            })
            ->orderByDesc('ventas.created_at');
    }
}
