<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\FrecuenciaGasto;
use App\Models\GastoFijo;
use BackedEnum;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ReporteBalancePage extends Page implements HasForms
{
    use InteractsWithForms;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.reporte-balance';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationLabel = 'Balance';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 10;
    protected static ?string $title = 'Reporte de Balance';

    public function getHeading(): string { return static::$title ?? ''; }

    public static function canAccess(): bool
    {
        return (auth()->user()?->can('reportes.balance') ?? false)
            && (Filament::getTenant()?->tieneModulo('reporte_balance') ?? false);
    }

    public ?string $filtroRango      = 'hoy';
    public ?string $filtroFechaDesde = null;
    public ?string $filtroFechaHasta = null;

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
            Grid::make(['default' => 1, 'md' => 3])->schema([
                Select::make('filtroRango')
                    ->label('Período')
                    ->options([
                        'hoy'      => 'Hoy',
                        'semana'   => 'Esta semana',
                        'quincena' => 'Esta quincena',
                        'mes'      => 'Este mes',
                        'custom'   => 'Personalizado',
                    ])
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        [$desde, $hasta] = $this->rangoFechas($state);
                        $set('filtroFechaDesde', $desde);
                        $set('filtroFechaHasta', $hasta);
                    }),

                DatePicker::make('filtroFechaDesde')
                    ->label('Desde')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->visible(fn ($get) => $get('filtroRango') === 'custom'),

                DatePicker::make('filtroFechaHasta')
                    ->label('Hasta')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->visible(fn ($get) => $get('filtroRango') === 'custom'),
            ]),
        ]);
    }

    public function updated($property): void
    {
        if (in_array($property, ['filtroRango', 'filtroFechaDesde', 'filtroFechaHasta'])) {
            // Reactivo — la vista se recalcula automáticamente
        }
    }

    // ── Helpers de fecha ──────────────────────────────────────────────────────

    private function rangoFechas(?string $rango): array
    {
        $hoy = today();
        return match ($rango) {
            'semana'   => [$hoy->copy()->startOfWeek()->toDateString(),  $hoy->toDateString()],
            'quincena' => [$hoy->day <= 15
                            ? $hoy->copy()->startOfMonth()->toDateString()
                            : $hoy->copy()->setDay(16)->toDateString(),   $hoy->toDateString()],
            'mes'      => [$hoy->copy()->startOfMonth()->toDateString(),  $hoy->toDateString()],
            default    => [$hoy->toDateString(), $hoy->toDateString()],   // hoy
        };
    }

    private function fechas(): array
    {
        if ($this->filtroRango && $this->filtroRango !== 'custom') {
            return $this->rangoFechas($this->filtroRango);
        }
        $desde = $this->filtroFechaDesde ?: today()->toDateString();
        $hasta = $this->filtroFechaHasta ?: today()->toDateString();
        return [$desde, $hasta];
    }

    // ── Cálculos del período ──────────────────────────────────────────────────

    public function getResumenPeriodo(): array
    {
        [$desde, $hasta] = $this->fechas();
        $empresaId       = Filament::getTenant()->id;

        // Ventas completadas — monto_pagado = cobrado en caja, saldo_pendiente = crédito pendiente
        $ventas = DB::table('ventas')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'completada')
            ->whereDate('fecha_emision', '>=', $desde)
            ->whereDate('fecha_emision', '<=', $hasta)
            ->selectRaw('
                COUNT(*) AS total_ventas,
                COALESCE(SUM(monto_pagado), 0)    AS monto_cobrado,
                COALESCE(SUM(saldo_pendiente), 0) AS por_cobrar,
                COALESCE(SUM(costo_total), 0)     AS costo_ventas
            ')
            ->first();

        // Ingresos y egresos de caja en una sola query
        $movCaja = DB::table('ingresos_egresos')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'aprobado')
            ->where('fecha_hora', '>=', $desde)
            ->where('fecha_hora', '<=', $hasta . ' 23:59:59')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) AS ingresos,
                COALESCE(SUM(CASE WHEN tipo = 'egreso'  THEN monto ELSE 0 END), 0) AS egresos
            ")
            ->first();

        $ingresosCaja = (float) ($movCaja->ingresos ?? 0);
        $egresosCaja  = (float) ($movCaja->egresos  ?? 0);

        // Gastos variables del período
        $gastosVariables = (float) DB::table('gastos')
            ->where('empresa_id', $empresaId)
            ->where('estado', '!=', 'anulado')
            ->where('fecha', '>=', $desde)
            ->where('fecha', '<=', $hasta)
            ->sum('monto');

        // Compras confirmadas y pagadas del período (suma de pagos por método)
        $compras = (float) DB::table('compra_pagos')
            ->join('compras', 'compras.id', '=', 'compra_pagos.compra_id')
            ->where('compras.empresa_id', $empresaId)
            ->where('compras.estado', 'confirmado')
            ->where('compras.estado_pago', 'pagado')
            ->where('compras.created_at', '>=', $desde)
            ->where('compras.created_at', '<=', $hasta . ' 23:59:59')
            ->sum('compra_pagos.monto');

        // Gastos fijos prorrateados (solo para ganancia estimada)
        $diasPeriodo       = Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1;
        $periodoFrecuencia = FrecuenciaGasto::Mensual;
        if ($diasPeriodo <= 1)      $periodoFrecuencia = FrecuenciaGasto::Diario;
        elseif ($diasPeriodo <= 7)  $periodoFrecuencia = FrecuenciaGasto::Semanal;
        elseif ($diasPeriodo <= 15) $periodoFrecuencia = FrecuenciaGasto::Quincenal;

        $gastosFijos = GastoFijo::where('empresa_id', $empresaId)
            ->where('estado', 'activo')
            ->get()
            ->sum(fn ($g) => $g->frecuencia->montoEnPeriodo((float) $g->monto, $periodoFrecuencia));

        $montoVentas = (float) ($ventas->monto_cobrado ?? 0);
        $porCobrar   = (float) ($ventas->por_cobrar   ?? 0);
        $costoVentas = (float) ($ventas->costo_ventas ?? 0);
        $gananciaBruta = $montoVentas - $costoVentas;
        $totalGastos   = $gastosVariables + $egresosCaja + $compras;
        $gananciaNeta  = $gananciaBruta - $totalGastos;

        return compact(
            'desde', 'hasta',
            'montoVentas', 'porCobrar', 'costoVentas', 'gananciaBruta',
            'ingresosCaja', 'egresosCaja',
            'gastosVariables', 'gastosFijos', 'compras', 'totalGastos',
            'gananciaNeta',
        ) + ['totalVentas' => (int) ($ventas->total_ventas ?? 0)];
    }

    public function getSaldosPorMetodo(): array
    {
        $empresaId = Filament::getTenant()->id;
        $metodos   = DB::table('metodos_pago')
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        if ($metodos->isEmpty()) return [];

        $ids = $metodos->pluck('id')->all();

        // 1. Ventas contado por método (una sola query)
        $ventas = DB::table('venta_pagos')
            ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->join('metodos_pago', 'metodos_pago.id', '=', 'venta_pagos.metodo_pago_id')
            ->where('ventas.empresa_id', $empresaId)
            ->where('ventas.estado', 'completada')
            ->where('metodos_pago.condicion_pago', 'contado')
            ->whereIn('venta_pagos.metodo_pago_id', $ids)
            ->selectRaw('venta_pagos.metodo_pago_id, SUM(venta_pagos.monto) AS total')
            ->groupBy('venta_pagos.metodo_pago_id')
            ->pluck('total', 'metodo_pago_id');

        // 2. Movimientos de caja netos por método (una sola query)
        $movimientos = DB::table('transacciones')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'aprobado')
            ->where('transaccionable_type', \App\Models\IngresoEgreso::class)
            ->whereIn('metodo_pago_id', $ids)
            ->selectRaw("metodo_pago_id, SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END) AS saldo")
            ->groupBy('metodo_pago_id')
            ->pluck('saldo', 'metodo_pago_id');

        // 3. Gastos por método (una sola query)
        $gastos = DB::table('gastos')
            ->where('empresa_id', $empresaId)
            ->where('estado', '!=', 'anulado')
            ->whereIn('metodo_pago_id', $ids)
            ->selectRaw('metodo_pago_id, SUM(monto) AS total')
            ->groupBy('metodo_pago_id')
            ->pluck('total', 'metodo_pago_id');

        // 4. Compras pagadas por método (una sola query)
        $compras = DB::table('compra_pagos')
            ->join('compras', 'compras.id', '=', 'compra_pagos.compra_id')
            ->where('compras.empresa_id', $empresaId)
            ->where('compras.estado', 'confirmado')
            ->where('compras.estado_pago', 'pagado')
            ->whereIn('compra_pagos.metodo_pago_id', $ids)
            ->selectRaw('compra_pagos.metodo_pago_id, SUM(compra_pagos.monto) AS total')
            ->groupBy('compra_pagos.metodo_pago_id')
            ->pluck('total', 'metodo_pago_id');

        return $metodos->map(fn ($m) => [
            'nombre' => $m->nombre,
            'saldo'  => round(
                (float) ($ventas->get($m->id)      ?? 0)
                + (float) ($movimientos->get($m->id) ?? 0)
                - (float) ($gastos->get($m->id)     ?? 0)
                - (float) ($compras->get($m->id)    ?? 0),
                2
            ),
        ])->all();
    }

    public function getGastosPorMetodoPeriodo(): array
    {
        [$desde, $hasta] = $this->fechas();
        $empresaId = Filament::getTenant()->id;

        // Gastos variables por método
        $gastosVar = DB::table('gastos')
            ->join('metodos_pago', 'metodos_pago.id', '=', 'gastos.metodo_pago_id')
            ->where('gastos.empresa_id', $empresaId)
            ->where('gastos.estado', '!=', 'anulado')
            ->whereDate('gastos.fecha', '>=', $desde)
            ->whereDate('gastos.fecha', '<=', $hasta)
            ->selectRaw('metodos_pago.id AS metodo_id, metodos_pago.nombre, SUM(gastos.monto) AS total')
            ->groupBy('metodos_pago.id', 'metodos_pago.nombre')
            ->get()
            ->keyBy('metodo_id');

        // Compra pagos por método (solo confirmadas y pagadas)
        $comprasVar = DB::table('compra_pagos')
            ->join('compras', 'compras.id', '=', 'compra_pagos.compra_id')
            ->join('metodos_pago', 'metodos_pago.id', '=', 'compra_pagos.metodo_pago_id')
            ->where('compras.empresa_id', $empresaId)
            ->where('compras.estado', 'confirmado')
            ->where('compras.estado_pago', 'pagado')
            ->whereDate('compras.created_at', '>=', $desde)
            ->whereDate('compras.created_at', '<=', $hasta)
            ->selectRaw('metodos_pago.id AS metodo_id, metodos_pago.nombre, SUM(compra_pagos.monto) AS total')
            ->groupBy('metodos_pago.id', 'metodos_pago.nombre')
            ->get()
            ->keyBy('metodo_id');

        // Merge por método
        $merged = collect($gastosVar)->map(fn ($g) => [
            'nombre' => $g->nombre,
            'total'  => (float) $g->total + (float) ($comprasVar->get($g->metodo_id)?->total ?? 0),
        ]);

        // Métodos que solo tienen compras (sin gastos variables)
        foreach ($comprasVar as $id => $c) {
            if (! $merged->has($id)) {
                $merged->put($id, ['nombre' => $c->nombre, 'total' => (float) $c->total]);
            }
        }

        return $merged->sortByDesc('total')->values()->all();
    }

    public function getIngresosPorMetodoPeriodo(): array
    {
        return $this->movimientosCajaPorMetodo('ingreso');
    }

    public function getEgresosPorMetodoPeriodo(): array
    {
        return $this->movimientosCajaPorMetodo('egreso');
    }

    private function movimientosCajaPorMetodo(string $tipo): array
    {
        [$desde, $hasta] = $this->fechas();
        $empresaId = Filament::getTenant()->id;

        return DB::table('ingresos_egresos')
            ->join('metodos_pago', 'metodos_pago.id', '=', 'ingresos_egresos.metodo_pago_id')
            ->where('ingresos_egresos.empresa_id', $empresaId)
            ->where('ingresos_egresos.tipo', $tipo)
            ->where('ingresos_egresos.estado', 'aprobado')
            ->whereDate('ingresos_egresos.fecha_hora', '>=', $desde)
            ->whereDate('ingresos_egresos.fecha_hora', '<=', $hasta)
            ->selectRaw('metodos_pago.nombre, SUM(ingresos_egresos.monto) AS total')
            ->groupBy('metodos_pago.id', 'metodos_pago.nombre')
            ->get()
            ->map(fn ($r) => ['nombre' => $r->nombre, 'total' => (float) $r->total])
            ->keyBy('nombre')
            ->all();
    }

    public function getVentasPorMetodoPeriodo(): array
    {
        [$desde, $hasta] = $this->fechas();
        $empresaId = Filament::getTenant()->id;

        // Contado: suma lo efectivamente cobrado (venta_pagos.monto)
        $contado = DB::table('venta_pagos')
            ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->join('metodos_pago', 'metodos_pago.id', '=', 'venta_pagos.metodo_pago_id')
            ->where('ventas.empresa_id', $empresaId)
            ->where('ventas.estado', 'completada')
            ->where('metodos_pago.condicion_pago', 'contado')
            ->whereDate('ventas.fecha_emision', '>=', $desde)
            ->whereDate('ventas.fecha_emision', '<=', $hasta)
            ->selectRaw('metodos_pago.nombre, metodos_pago.condicion_pago, SUM(venta_pagos.monto) AS total')
            ->groupBy('metodos_pago.id', 'metodos_pago.nombre', 'metodos_pago.condicion_pago')
            ->get();

        // Crédito: suma saldo_pendiente real (ya descuenta abonos recibidos)
        $credito = DB::table('ventas')
            ->join('venta_pagos', 'venta_pagos.venta_id', '=', 'ventas.id')
            ->join('metodos_pago', 'metodos_pago.id', '=', 'venta_pagos.metodo_pago_id')
            ->where('ventas.empresa_id', $empresaId)
            ->where('ventas.estado', 'completada')
            ->where('metodos_pago.condicion_pago', 'credito')
            ->whereDate('ventas.fecha_emision', '>=', $desde)
            ->whereDate('ventas.fecha_emision', '<=', $hasta)
            ->selectRaw('metodos_pago.nombre, metodos_pago.condicion_pago, SUM(ventas.saldo_pendiente) AS total')
            ->groupBy('metodos_pago.id', 'metodos_pago.nombre', 'metodos_pago.condicion_pago')
            ->get();

        return $contado->merge($credito)
            ->map(fn ($r) => [
                'nombre'     => $r->nombre,
                'total'      => (float) $r->total,
                'es_credito' => $r->condicion_pago === 'credito',
            ])
            ->all();
    }
}
