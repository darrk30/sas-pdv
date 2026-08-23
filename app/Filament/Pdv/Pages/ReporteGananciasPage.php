<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use App\Models\User;
use App\Models\Venta;
use App\Services\ReporteGananciasExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ReporteGananciasPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.reporte-ganancias';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-trending-up';
    protected static ?string $navigationLabel = 'Reporte de Ganancias';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Reporte de Ganancias';

    public static function canAccess(): bool { return Filament::getTenant()->tieneModulo('reporte_ganancias') && (auth()->user()?->can('caja.reporte_ganancias') ?? false); }

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

                Select::make('filtroVendedor')
                    ->label('Vendedor')
                    ->placeholder('Todos los vendedores')
                    ->options(fn() => User::whereHas('empresas', fn($q) => $q->where('empresa_id', Filament::getTenant()->id))
                        ->orderBy('name')->pluck('name', 'id')->toArray())
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
            || ! empty($this->filtroVendedor);
    }

    public function limpiarFiltros(): void
    {
        $this->filtroFechaDesde = null;
        $this->filtroFechaHasta = null;
        $this->filtroVendedor   = null;
        $this->form->fill();
    }

    // ── Query base ────────────────────────────────────────────────────────────

    private function baseQuery(): Builder
    {
        $q = Venta::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', EstadoVenta::Completada->value);

        if (! empty($this->filtroFechaDesde)) {
            $q->where('created_at', '>=', $this->filtroFechaDesde);
        }
        if (! empty($this->filtroFechaHasta)) {
            $q->where('created_at', '<=', $this->filtroFechaHasta);
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
                COUNT(*)                                                                                                                                          AS cantidad,
                COALESCE(SUM(total), 0)                                                                                                                           AS ingresos_brutos,
                COALESCE(SUM(total - igv), 0)                                                                                                                     AS ventas_netas,
                COALESCE(SUM(costo_total), 0)                                                                                                                     AS costo_total,
                COALESCE(SUM(CASE WHEN estado_pago IN ('pendiente','parcial') THEN saldo_pendiente ELSE 0 END), 0)                                                AS credito_pendiente,
                COALESCE(SUM(CASE WHEN estado_pago IN ('pendiente','parcial') THEN (total - igv - costo_total) * saldo_pendiente / NULLIF(total,0) ELSE 0 END), 0) AS utilidad_en_riesgo
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

    // ── Helpers de utilidad por fila ──────────────────────────────────────────

    private static function calcularUtilidad(Venta $v): array
    {
        $ventaNeta     = (float) $v->total - (float) $v->igv;  // descuenta IGV si aplica
        $costo         = (float) $v->costo_total;
        $utilidadTotal = $ventaNeta - $costo;
        $total         = (float) $v->total;
        $estadoPago    = $v->estado_pago ?? 'pagado';

        if ($estadoPago === 'pagado' || $total <= 0) {
            return [
                'cobrada' => $utilidadTotal,
                'riesgo'  => 0.0,
                'total'   => $utilidadTotal,
                'neta'    => $ventaNeta,
            ];
        }

        // Proporcional: sólo la parte cobrada genera utilidad realizada
        $pctCobrado      = (float) $v->monto_pagado / $total;
        $utilidadCobrada = $utilidadTotal * $pctCobrado;
        $utilidadRiesgo  = $utilidadTotal - $utilidadCobrada;

        return [
            'cobrada' => $utilidadCobrada,
            'riesgo'  => $utilidadRiesgo,
            'total'   => $utilidadTotal,
            'neta'    => $ventaNeta,
        ];
    }

    // ── Exportación ───────────────────────────────────────────────────────────

    private function accionesExportacion(): array
    {
        return [
            Action::make('descargarExcel')
                ->label('Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(function () {
                    return app(ReporteGananciasExportService::class)->generarExcel(
                        $this->getVentasParaExportar(),
                        $this->getFiltrosInfo(),
                        $this->getColumnasVisibles(),
                        $this->getResumen(),
                        Filament::getTenant(),
                    );
                }),
            Action::make('descargarPdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(function () {
                    return app(ReporteGananciasExportService::class)->generarPdf(
                        $this->getVentasParaExportar(),
                        $this->getFiltrosInfo(),
                        $this->getColumnasVisibles(),
                        $this->getResumen(),
                        Filament::getTenant(),
                    );
                }),
        ];
    }

    private function getVentasParaExportar(): \Illuminate\Database\Eloquent\Collection
    {
        return (clone $this->baseQuery())
            ->with(['serie', 'vendedor:id,name'])
            ->orderBy('created_at', 'desc')
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
        if (! empty($this->filtroVendedor)) {
            $info['Vendedor'] = User::find($this->filtroVendedor)?->name ?? $this->filtroVendedor;
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
            if (($item['type'] ?? '') === 'group') {
                foreach ($item['columns'] ?? [] as $col) {
                    if ($col['isToggled'] ?? false) {
                        $visible[] = $col['name'];
                    }
                }
            }
        }
        return $visible;
    }

    // ── Tabla Filament ────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => (clone $this->baseQuery())
                ->with(['serie', 'vendedor:id,name'])
                ->selectRaw('ventas.*')
            )
            ->defaultSort('created_at', 'desc')
            ->toolbarActions($this->accionesExportacion())
            ->columns([

                TextColumn::make('comprobante')
                    ->label('Comprobante')
                    ->state(fn (Venta $r): string => ($r->serie?->serie ?? '---') . '-' . str_pad((string) $r->correlativo, 8, '0', STR_PAD_LEFT))
                    ->description(fn (Venta $r): ?string => (float) $r->saldo_pendiente > 0
                        ? '⚠ Saldo: S/ ' . number_format((float) $r->saldo_pendiente, 2)
                        : null)
                    ->weight('medium')
                    ->searchable(false)
                    ->sortable(false)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('cliente_nombre')
                    ->label('Cliente')
                    ->description(fn (Venta $r): string => $r->vendedor?->name ?? '—')
                    ->searchable(false)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('estado_pago')
                    ->label('Pago')
                    ->state(fn (Venta $r): string => match ($r->estado_pago ?? 'pagado') {
                        'pendiente' => 'Crédito',
                        'parcial'   => 'Parcial · S/ ' . number_format((float) $r->monto_pagado, 2) . ' pag.',
                        default     => 'Contado',
                    })
                    ->badge()
                    ->color(fn (Venta $r): string => match ($r->estado_pago ?? 'pagado') {
                        'pendiente' => 'warning',
                        'parcial'   => 'info',
                        default     => 'success',
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('venta_neta')
                    ->label('Venta neta')
                    ->state(fn (Venta $r): string => 'S/ ' . number_format((float) $r->total - (float) $r->igv, 2))
                    ->description(fn (Venta $r): ?string => (float) $r->igv > 0
                        ? 'IGV: S/ ' . number_format((float) $r->igv, 2)
                        : null)
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('costo_total')
                    ->label('Costo')
                    ->money('PEN')
                    ->alignEnd()
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('utilidad')
                    ->label('Utilidad')
                    ->state(function (Venta $r): string {
                        $u = self::calcularUtilidad($r);
                        return ($u['cobrada'] >= 0 ? '' : '- ') . 'S/ ' . number_format(abs($u['cobrada']), 2);
                    })
                    ->description(function (Venta $r): ?string {
                        $u = self::calcularUtilidad($r);
                        return $u['riesgo'] > 0
                            ? 'riesgo: S/ ' . number_format($u['riesgo'], 2)
                            : null;
                    })
                    ->color(fn (Venta $r): string => self::calcularUtilidad($r)['cobrada'] >= 0 ? 'success' : 'danger')
                    ->alignEnd()
                    ->weight('semibold')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('utilidad_riesgo')
                    ->label('En riesgo')
                    ->state(function (Venta $r): string {
                        $u = self::calcularUtilidad($r);
                        return $u['riesgo'] > 0
                            ? 'S/ ' . number_format($u['riesgo'], 2)
                            : '—';
                    })
                    ->color(fn (Venta $r): string => self::calcularUtilidad($r)['riesgo'] > 0 ? 'warning' : 'gray')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('saldo_pendiente')
                    ->label('Saldo pendiente')
                    ->money('PEN')
                    ->alignEnd()
                    ->color('warning')
                    ->formatStateUsing(fn ($state): string => (float) $state > 0 ? 'S/ ' . number_format((float) $state, 2) : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('margen')
                    ->label('Margen')
                    ->state(function (Venta $r): string {
                        $u = self::calcularUtilidad($r);
                        if ($u['neta'] <= 0) return '—';
                        // Solo mostrar margen cuando hay algo cobrado
                        if ($r->estado_pago === 'pendiente') return '—';
                        $margen = round($u['cobrada'] / $u['neta'] * 100, 1);
                        return number_format($margen, 1) . '%';
                    })
                    ->badge()
                    ->color(function (Venta $r): string {
                        $u = self::calcularUtilidad($r);
                        if ($u['neta'] <= 0 || $r->estado_pago === 'pendiente') return 'gray';
                        $m = $u['cobrada'] / $u['neta'] * 100;
                        return match(true) {
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
            ->emptyStateIcon('heroicon-o-arrow-trending-up');
    }
}
