<?php

namespace App\Filament\Pdv\Pages;

use App\Models\Compra;
use App\Services\ReporteComprasExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ReporteComprasPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.reporte-compras';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Reporte de Compras';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 6;
    protected static ?string $title = 'Reporte de Compras';

    public static function canAccess(): bool
    {
        return Filament::getTenant()->tieneModulo('reporte_compras')
            && (auth()->user()?->can('compras.reporte') ?? false);
    }

    // ── Filtros ───────────────────────────────────────────────────────────────

    public ?string $filtroRango           = 'hoy';
    public ?string $filtroFechaDesde      = null;
    public ?string $filtroFechaHasta      = null;
    public ?string $filtroUsuario         = null;
    public ?string $filtroEstado          = null;
    public ?string $filtroEstadoPago      = null;
    public ?string $filtroTipoComprobante = null;
    public ?string $filtroSerie           = null;
    public ?string $filtroCorrelativo     = null;

    // ── Estado modales ────────────────────────────────────────────────────────

    public ?int $compraDetalleId = null;
    public ?int $compraPagosId   = null;

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
                    ->options(['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes', 'personalizado' => 'Personalizado'])
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn (?string $state) => $state ? $this->aplicarRango($state) : null),

                Select::make('filtroUsuario')
                    ->label('Registrado por')
                    ->placeholder('Todos los usuarios')
                    ->options(fn () => DB::table('users')
                        ->join('compras', 'users.id', '=', 'compras.user_id')
                        ->where('compras.empresa_id', Filament::getTenant()->id)
                        ->distinct()->orderBy('users.name')
                        ->pluck('users.name', 'users.id')->toArray())
                    ->native(false)->searchable()
                    ->live(),

                Select::make('filtroEstado')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->options(['borrador' => 'Borrador', 'confirmado' => 'Confirmado', 'anulado' => 'Anulado'])
                    ->native(false)
                    ->live(),

                Select::make('filtroEstadoPago')
                    ->label('Estado de pago')
                    ->placeholder('Todos')
                    ->options(['pendiente' => 'Pendiente', 'pagado' => 'Pagado'])
                    ->native(false)
                    ->live(),

                Select::make('filtroTipoComprobante')
                    ->label('Tipo de comprobante')
                    ->placeholder('Todos')
                    ->options(['factura' => 'Factura', 'boleta' => 'Boleta', 'ticket' => 'Ticket', 'sin_comprobante' => 'Sin comprobante'])
                    ->native(false)
                    ->live(),

                TextInput::make('filtroSerie')
                    ->label('Serie')
                    ->placeholder('Ej: F001')
                    ->live(debounce: 400),

                TextInput::make('filtroCorrelativo')
                    ->label('Correlativo')
                    ->placeholder('Ej: 00001')
                    ->live(debounce: 400),

                DatePicker::make('filtroFechaDesde')
                    ->label('Desde')->displayFormat('d/m/Y')
                    ->live()
                    ->hidden(fn () => $this->filtroRango !== 'personalizado'),

                DatePicker::make('filtroFechaHasta')
                    ->label('Hasta')->displayFormat('d/m/Y')
                    ->live()
                    ->hidden(fn () => $this->filtroRango !== 'personalizado'),

                Actions::make([
                    Action::make('limpiarFiltros')
                        ->label('Limpiar')
                        ->color('gray')->size('sm')->outlined()
                        ->icon('heroicon-o-x-mark')
                        ->visible(fn () => $this->hayFiltros())
                        ->action(fn () => $this->limpiarFiltros()),
                ])->verticallyAlignEnd(),

            ]),
        ]);
    }

    private function aplicarRango(string $rango): void
    {
        [$desde, $hasta] = match ($rango) {
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
        return $this->filtroRango !== 'hoy'
            || ! empty($this->filtroUsuario)
            || ! empty($this->filtroEstado)
            || ! empty($this->filtroEstadoPago)
            || ! empty($this->filtroTipoComprobante)
            || ! empty($this->filtroSerie)
            || ! empty($this->filtroCorrelativo);
    }

    public function limpiarFiltros(): void
    {
        $hoy = today()->toDateString();
        $this->filtroRango           = 'hoy';
        $this->filtroFechaDesde      = $hoy;
        $this->filtroFechaHasta      = $hoy;
        $this->filtroUsuario         = null;
        $this->filtroEstado          = null;
        $this->filtroEstadoPago      = null;
        $this->filtroTipoComprobante = null;
        $this->filtroSerie           = null;
        $this->filtroCorrelativo     = null;
        $this->form->fill([
            'filtroRango' => 'hoy', 'filtroFechaDesde' => $hoy, 'filtroFechaHasta' => $hoy,
            'filtroUsuario' => null, 'filtroEstado' => null, 'filtroEstadoPago' => null,
            'filtroTipoComprobante' => null, 'filtroSerie' => null, 'filtroCorrelativo' => null,
        ]);
    }

    // ── Query base ────────────────────────────────────────────────────────────

    private function aplicarFiltros(Builder $q): void
    {
        $q->where('compras.empresa_id', Filament::getTenant()->id);

        if (! empty($this->filtroFechaDesde)) {
            $q->whereDate('compras.fecha_compra', '>=', $this->filtroFechaDesde);
        }
        if (! empty($this->filtroFechaHasta)) {
            $q->whereDate('compras.fecha_compra', '<=', $this->filtroFechaHasta);
        }
        if (! empty($this->filtroUsuario)) {
            $q->where('compras.user_id', $this->filtroUsuario);
        }
        if (! empty($this->filtroEstado)) {
            $q->where('compras.estado', $this->filtroEstado);
        }
        if (! empty($this->filtroEstadoPago)) {
            $q->where('compras.estado_pago', $this->filtroEstadoPago);
        }
        if (! empty($this->filtroTipoComprobante)) {
            $q->where('compras.tipo_comprobante', $this->filtroTipoComprobante);
        }
        if (! empty($this->filtroSerie)) {
            $q->where('compras.serie', 'like', $this->filtroSerie . '%');
        }
        if (! empty($this->filtroCorrelativo)) {
            $q->where('compras.correlativo', 'like', $this->filtroCorrelativo . '%');
        }
    }

    // ── KPIs ─────────────────────────────────────────────────────────────────

    public function getResumen(): array
    {
        $q = Compra::query();
        $this->aplicarFiltros($q);

        $cantidad  = (clone $q)->count();
        $total     = (float) (clone $q)->sum('total');
        $pendiente = (int) (clone $q)->where('estado_pago', 'pendiente')->count();
        $pagado    = (float) (clone $q)
            ->join('compra_pagos as cp', 'cp.compra_id', '=', 'compras.id')
            ->sum('cp.monto');

        return [
            'cantidad'  => $cantidad,
            'total'     => $total,
            'pendiente' => $pendiente,
            'pagado'    => $pagado,
            'saldo'     => round($total - $pagado, 2),
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
                    return app(ReporteComprasExportService::class)->generarExcel(
                        $this->getComprasParaExportar(),
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
                    return app(ReporteComprasExportService::class)->generarPdf(
                        $this->getComprasParaExportar(),
                        $this->getFiltrosInfo(),
                        $this->getColumnasVisibles(),
                        $this->getResumen(),
                        Filament::getTenant(),
                    );
                }),
        ];
    }

    private function getComprasParaExportar(): \Illuminate\Database\Eloquent\Collection
    {
        $q = Compra::with(['proveedor:id,nombre', 'registradoPor:id,name'])
            ->withSum('pagos', 'monto')
            ->orderByDesc('fecha_compra')
            ->orderByDesc('id');
        $this->aplicarFiltros($q);
        return $q->get();
    }

    private function getFiltrosInfo(): array
    {
        $info = [];
        $rangos = ['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes', 'personalizado' => 'Personalizado'];
        $info['Período'] = $rangos[$this->filtroRango] ?? $this->filtroRango;
        if (! empty($this->filtroFechaDesde)) {
            $info['Desde'] = \Illuminate\Support\Carbon::parse($this->filtroFechaDesde)->format('d/m/Y');
        }
        if (! empty($this->filtroFechaHasta)) {
            $info['Hasta'] = \Illuminate\Support\Carbon::parse($this->filtroFechaHasta)->format('d/m/Y');
        }
        if (! empty($this->filtroEstado)) {
            $info['Estado'] = ucfirst($this->filtroEstado);
        }
        if (! empty($this->filtroEstadoPago)) {
            $info['Pago'] = ucfirst($this->filtroEstadoPago);
        }
        if (! empty($this->filtroTipoComprobante)) {
            $labels = ['factura' => 'Factura', 'boleta' => 'Boleta', 'ticket' => 'Ticket', 'sin_comprobante' => 'Sin comprobante'];
            $info['Tipo'] = $labels[$this->filtroTipoComprobante] ?? $this->filtroTipoComprobante;
        }
        if (! empty($this->filtroSerie)) {
            $info['Serie'] = $this->filtroSerie;
        }
        if (! empty($this->filtroCorrelativo)) {
            $info['Correlativo'] = $this->filtroCorrelativo;
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
            ->query(fn (): Builder => Compra::query()
                ->with(['proveedor:id,nombre', 'registradoPor:id,name'])
                ->withSum('pagos', 'monto')
                ->tap(fn ($q) => $this->aplicarFiltros($q))
            )
            ->defaultSort('fecha_compra', 'desc')
            ->toolbarActions($this->accionesExportacion())
            ->columns([

                TextColumn::make('comprobante')
                    ->label('Comprobante')
                    ->state(fn (Compra $r): string => ($r->serie && $r->correlativo)
                        ? $r->serie . '-' . $r->correlativo
                        : ($r->codigo ?? '—'))
                    ->description(fn (Compra $r): string => match ($r->tipo_comprobante) {
                        'factura'         => 'Factura',
                        'boleta'          => 'Boleta',
                        'ticket'          => 'Ticket',
                        'sin_comprobante' => 'Sin comprobante',
                        default           => $r->tipo_comprobante,
                    })
                    ->weight('medium')
                    ->searchable(false)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('fecha_compra')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->description(fn (Compra $r): string => $r->registradoPor?->name ?? '—')
                    ->searchable(false)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'borrador'   => 'Borrador',
                        'confirmado' => 'Confirmado',
                        'anulado'    => 'Anulado',
                        default      => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'confirmado' => 'success',
                        'anulado'    => 'danger',
                        default      => 'gray',
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('estado_despacho')
                    ->label('Despacho')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'recibido' ? 'Recibido' : 'Pendiente')
                    ->color(fn (?string $state): string => $state === 'recibido' ? 'success' : 'warning')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('estado_pago')
                    ->label('Pago')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pagado'    => 'Pagado',
                        'pendiente' => 'Pendiente',
                        default     => $state ?? '—',
                    })
                    ->color(fn (?string $state): string => $state === 'pagado' ? 'success' : 'warning')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->state(fn (Compra $r): string => $this->calcularSaldo($r) > 0.01
                        ? 'S/ ' . number_format($this->calcularSaldo($r), 2)
                        : '✓ Saldado')
                    ->color(fn (Compra $r): string => $this->calcularSaldo($r) > 0.01 ? 'warning' : 'success')
                    ->alignEnd()
                    ->weight('semibold')
                    ->toggleable(isToggledHiddenByDefault: false),

            ])
            ->actions([
                Action::make('detalle')
                    ->label('Detalle')
                    ->button()
                    ->size('xs')
                    ->color('gray')
                    ->action(fn (Compra $record) => $this->abrirDetalle($record->id)),

                Action::make('pagos')
                    ->label(fn (Compra $record): string => $this->calcularSaldo($record) > 0.01 ? 'Pagos !' : 'Pagos')
                    ->button()
                    ->size('xs')
                    ->color(fn (Compra $record): string => $this->calcularSaldo($record) > 0.01 ? 'warning' : 'success')
                    ->action(fn (Compra $record) => $this->abrirPagos($record->id)),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Sin compras')
            ->emptyStateDescription('No se encontraron compras en el período seleccionado.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }

    private function calcularSaldo(Compra $r): float
    {
        return (float) $r->total - (float) ($r->pagos_sum_monto ?? 0);
    }

    // ── Modales ───────────────────────────────────────────────────────────────

    public function abrirDetalle(int $id): void { $this->compraPagosId = null; $this->compraDetalleId = $id; }
    public function cerrarDetalle(): void        { $this->compraDetalleId = null; }

    public function getCompraDetalle(): ?Compra
    {
        if (! $this->compraDetalleId) return null;

        return Compra::with([
            'proveedor:id,nombre',
            'registradoPor:id,name',
            'detalles.unidad:id,simbolo',
        ])->find($this->compraDetalleId);
    }

    public function abrirPagos(int $id): void { $this->compraDetalleId = null; $this->compraPagosId = $id; }
    public function cerrarPagos(): void        { $this->compraPagosId = null; }

    public function getCompraPagos(): ?Compra
    {
        if (! $this->compraPagosId) return null;

        return Compra::with([
            'proveedor:id,nombre',
            'pagos.metodoPago:id,nombre',
        ])->find($this->compraPagosId);
    }
}
