<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoMovimiento;
use App\Enums\EstadoSesion;
use App\Enums\EstadoVenta;
use App\Enums\TipoMovimiento;
use App\Models\SesionCaja;
use App\Models\Transaccion;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use UnitEnum;

class CierresCajaPage extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected string $view = 'filament.pdv.pages.cierres-caja';
    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel  = 'Cierres de Caja';
    protected static string|UnitEnum|null $navigationGroup  = 'Caja';
    protected static ?int    $navigationSort   = 10;
    protected static ?string $title            = 'Cierres de Caja';

    public function getHeading(): string         { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }

    // ── Filtros de la lista ───────────────────────────────────────────────────

    public ?string $filtroFechaDesde = null;
    public ?string $filtroFechaHasta = null;
    public ?string $filtroVendedor   = null;

    // ── Estado del modal / reporte ────────────────────────────────────────────

    public ?int   $sesionId         = null;
    public string $tabReporte       = 'resumen';
    public string $subTabVentas     = 'aprobadas';   // aprobadas | anuladas
    public string $subTabMov        = 'ing_apr';      // ing_apr | ing_anu | egr_apr | egr_anu

    public function mount(): void
    {
        $this->form->fill();
    }

    // ── Form de filtros ───────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 3])->schema([

                DatePicker::make('filtroFechaDesde')
                    ->label('Desde')->displayFormat('d/m/Y')
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

                DatePicker::make('filtroFechaHasta')
                    ->label('Hasta')->displayFormat('d/m/Y')
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

                Select::make('filtroVendedor')
                    ->label('Cajero')->placeholder('Todos los cajeros')
                    ->options(fn() => User::whereHas('empresas', fn($q) => $q->where('empresa_id', Filament::getTenant()->id))
                        ->orderBy('name')->pluck('name', 'id')->toArray())
                    ->native(false)->searchable()
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

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
        $this->resetPage();
    }

    // ── Lista de sesiones (paginada, pageName='page') ─────────────────────────

    public function getSesiones(): LengthAwarePaginator
    {
        $q = SesionCaja::where('empresa_id', Filament::getTenant()->id)
            ->with(['caja', 'cajero:id,name'])
            ->orderBy('fecha_apertura', 'desc');

        if (! empty($this->filtroVendedor))   $q->where('user_id', $this->filtroVendedor);
        if (! empty($this->filtroFechaDesde)) $q->whereDate('fecha_apertura', '>=', $this->filtroFechaDesde);
        if (! empty($this->filtroFechaHasta)) $q->whereDate('fecha_apertura', '<=', $this->filtroFechaHasta);

        return $q->withCount(['pagos as tiene_cuadre' => fn($q) => $q->whereNotNull('importe_cajero')])
                 ->paginate(20);
    }

    // ── Abrir / cerrar reporte ────────────────────────────────────────────────

    public function abrirReporte(int $id): void
    {
        $this->sesionId   = $id;
        $this->tabReporte = 'resumen';
        $this->subTabVentas = 'aprobadas';
        $this->subTabMov    = 'ing_apr';
        $this->resetPage('vp');
        $this->resetPage('pp');
        $this->resetPage('mp');
        $this->resetPage('cvp');
    }

    public function cerrarReporte(): void { $this->sesionId = null; }

    // ── Cambios de tab ────────────────────────────────────────────────────────

    public function setTab(string $tab): void
    {
        $this->tabReporte = $tab;
        $this->resetPage('vp');
        $this->resetPage('pp');
        $this->resetPage('mp');
        $this->resetPage('cvp');
    }

    public function setSubTabVentas(string $sub): void
    {
        $this->subTabVentas = $sub;
        $this->resetPage('vp');
    }

    public function setSubTabMov(string $sub): void
    {
        $this->subTabMov = $sub;
        $this->resetPage('mp');
    }

    // ── Helpers internos ──────────────────────────────────────────────────────

    private function empresaId(): int
    {
        return Filament::getTenant()->id;
    }

    private function ventasBase(): \Illuminate\Database\Eloquent\Builder
    {
        return Venta::where('empresa_id', $this->empresaId())
                    ->where('sesion_caja_id', $this->sesionId);
    }

    // ── Info de sesión ────────────────────────────────────────────────────────

    public function getSesionInfo(): ?SesionCaja
    {
        if (! $this->sesionId) return null;
        return SesionCaja::with(['caja', 'cajero:id,name', 'pagos.metodoPago'])
                          ->find($this->sesionId);
    }

    // ── Tab: Resumen ──────────────────────────────────────────────────────────

    public function getResumen(): array
    {
        if (! $this->sesionId) return [];

        $sesion = SesionCaja::find($this->sesionId);

        $comp = (clone $this->ventasBase())->where('estado', EstadoVenta::Completada->value)
            ->selectRaw('
                COUNT(*)                            AS cnt,
                COALESCE(SUM(total), 0)             AS tot_total,
                COALESCE(SUM(igv), 0)               AS igv,
                COALESCE(SUM(total - igv), 0)       AS neta,
                COALESCE(SUM(costo_total), 0)       AS costo,
                COALESCE(SUM(descuento_total), 0)   AS descuento
            ')->first();

        $anu = (clone $this->ventasBase())->where('estado', EstadoVenta::Anulada->value)
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(total),0) AS tot')->first();

        $desp = (clone $this->ventasBase())
            ->where('estado_despacho', EstadoVenta::PendienteEnvio->value)->count();

        $neta     = (float) $comp->neta;
        $costo    = (float) $comp->costo;
        $utilidad = $neta - $costo;
        $margen   = $neta > 0 ? round($utilidad / $neta * 100, 1) : 0.0;

        return [
            'cnt_comp'       => (int)   $comp->cnt,
            'cnt_anu'        => (int)   $anu->cnt,
            'cnt_desp'       => $desp,
            'tot_total'      => (float) $comp->tot_total,
            'igv'            => (float) $comp->igv,
            'neta'           => $neta,
            'costo'          => $costo,
            'descuento'      => (float) $comp->descuento,
            'utilidad'       => $utilidad,
            'margen'         => $margen,
            'tot_anu'        => (float) $anu->tot,
            'monto_apertura'  => (float) ($sesion?->monto_apertura ?? 0),
            'total_sistema'   => (float) ($sesion?->total_sistema ?? 0),
            'total_cajero'    => (float) ($sesion?->total_cajero ?? 0),
            'diferencia'      => (float) ($sesion?->diferencia_total ?? 0),
            'total_creditos'  => (float) ($sesion?->total_creditos ?? 0),
        ];
    }

    // ── Tab: Ventas (pageName 'vp') ───────────────────────────────────────────

    public function getVentasTab(): LengthAwarePaginator
    {
        if (! $this->sesionId) return $this->emptyPaginator('vp');

        $estado = $this->subTabVentas === 'aprobadas'
            ? EstadoVenta::Completada->value
            : EstadoVenta::Anulada->value;

        return (clone $this->ventasBase())
            ->where('estado', $estado)
            ->with(['serie', 'pagos.metodoPago'])
            ->withCount('detalles')
            ->orderBy('created_at')
            ->paginate(20, pageName: 'vp');
    }

    // ── Tab: Productos vendidos (pageName 'pp') ───────────────────────────────

    public function getProductosTab(): LengthAwarePaginator
    {
        if (! $this->sesionId) return $this->emptyPaginator('pp');

        return VentaDetalle::whereHas('venta', fn($q) => $q
                ->where('sesion_caja_id', $this->sesionId)
                ->where('estado', EstadoVenta::Completada->value))
            ->where('precio_unitario', '>', 0)
            ->selectRaw('
                descripcion,
                COALESCE(SUM(cantidad), 0)  AS qty,
                COALESCE(SUM(total), 0)     AS tot,
                COUNT(DISTINCT venta_id)    AS en_ventas
            ')
            ->groupBy('descripcion')
            ->orderByDesc('qty')
            ->paginate(30, pageName: 'pp');
    }

    // ── Tab: Métodos de pago y comprobantes ───────────────────────────────────

    public function getMetodosYComprobantesTab(): array
    {
        if (! $this->sesionId) return ['metodos' => collect(), 'comprobantes' => collect(), 'cuadre' => collect()];

        // Sistema por método desde transacciones aprobadas (incluye fondo de apertura)
        $sistemaMap = DB::table('transacciones')
            ->where('sesion_caja_id', $this->sesionId)
            ->where('estado', 'aprobado')
            ->whereNotNull('metodo_pago_id')
            ->select('metodo_pago_id', 'tipo', DB::raw('COALESCE(SUM(monto),0) AS total'))
            ->groupBy('metodo_pago_id', 'tipo')
            ->get()
            ->groupBy('metodo_pago_id')
            ->map(fn($rows) => $rows->sum(
                fn($r) => $r->tipo === 'ingreso' ? (float) $r->total : -(float) $r->total
            ));

        // Conteo de pagos por método en esta sesión (por VentaPago.sesion_caja_id,
        // no por venta.sesion_caja_id, para capturar cobros de créditos de otras sesiones)
        $ventaPagos = VentaPago::where('sesion_caja_id', $this->sesionId)
            ->with('metodoPago:id,nombre')
            ->get()
            ->groupBy('metodo_pago_id');

        // Unir: todos los métodos que aparecen en transacciones
        $metodos = $sistemaMap->map(fn($sistema, $mpId) => [
                'metodo_pago_id' => $mpId,
                'nombre'         => $ventaPagos->get($mpId)?->first()?->metodoPago?->nombre
                                    ?? DB::table('metodos_pago')->where('id', $mpId)->value('nombre')
                                    ?? 'N/A',
                'sistema'        => $sistema,
                'count'          => $ventaPagos->get($mpId)?->groupBy('venta_id')->count() ?? 0,
            ])
            ->values();

        // Cuadre vs cajero (SesionCajaPago)
        $sesion        = SesionCaja::with('pagos.metodoPago')->find($this->sesionId);
        $cuadre        = $sesion?->pagos ?? collect();
        $montoApertura = (float) ($sesion?->monto_apertura ?? 0);

        // Por tipo de comprobante — DB::table evita cast de enums en el resultado
        $comprobantes = DB::table('ventas')
            ->join('series', 'ventas.serie_id', '=', 'series.id')
            ->where('ventas.sesion_caja_id', $this->sesionId)
            ->where('ventas.empresa_id', $this->empresaId())
            ->where('ventas.estado', EstadoVenta::Completada->value)
            ->select('series.tipo', DB::raw('COUNT(*) AS count'), DB::raw('COALESCE(SUM(ventas.total),0) AS total'))
            ->groupBy('series.tipo')
            ->get(); // Collection de stdClass: ->tipo, ->count, ->total

        return compact('metodos', 'cuadre', 'comprobantes', 'montoApertura');
    }

    // ── Tab: Cortesías ────────────────────────────────────────────────────────

    public function getCortesiasProductos(): Collection
    {
        if (! $this->sesionId) return collect();

        return VentaDetalle::whereHas('venta', fn($q) => $q
                ->where('sesion_caja_id', $this->sesionId)
                ->where('estado', EstadoVenta::Completada->value))
            ->where('precio_unitario', 0)
            ->selectRaw('descripcion, COALESCE(SUM(cantidad),0) AS qty, COUNT(DISTINCT venta_id) AS en_ventas')
            ->groupBy('descripcion')
            ->orderByDesc('qty')
            ->get();
    }

    public function getCortesiasVentas(): LengthAwarePaginator
    {
        if (! $this->sesionId) return $this->emptyPaginator('cvp');

        $ventasConCortesia = VentaDetalle::whereHas('venta', fn($q) => $q
                ->where('sesion_caja_id', $this->sesionId)
                ->where('estado', EstadoVenta::Completada->value))
            ->where('precio_unitario', 0)
            ->distinct()
            ->pluck('venta_id');

        return Venta::whereIn('id', $ventasConCortesia)
            ->with(['serie', 'pagos.metodoPago'])
            ->orderBy('created_at')
            ->paginate(15, pageName: 'cvp');
    }

    // ── Tab: Movimientos (pageName 'mp') ──────────────────────────────────────

    public function getMovimientosTab(): LengthAwarePaginator
    {
        if (! $this->sesionId) return $this->emptyPaginator('mp');

        [$tipo, $estado] = match ($this->subTabMov) {
            'ing_apr' => [TipoMovimiento::Ingreso->value, EstadoMovimiento::Aprobado->value],
            'ing_anu' => [TipoMovimiento::Ingreso->value, EstadoMovimiento::Anulado->value],
            'egr_apr' => [TipoMovimiento::Egreso->value,  EstadoMovimiento::Aprobado->value],
            'egr_anu' => [TipoMovimiento::Egreso->value,  EstadoMovimiento::Anulado->value],
            default   => [TipoMovimiento::Ingreso->value, EstadoMovimiento::Aprobado->value],
        };

        return Transaccion::where('sesion_caja_id', $this->sesionId)
            ->where('tipo', $tipo)
            ->where('estado', $estado)
            ->with('metodoPago:id,nombre')
            ->orderBy('fecha', 'desc')
            ->paginate(20, pageName: 'mp');
    }

    // ── Totales resumen por subTabMov ─────────────────────────────────────────

    public function getMovimientosTotales(): array
    {
        if (! $this->sesionId) return ['ing_apr' => 0, 'ing_anu' => 0, 'egr_apr' => 0, 'egr_anu' => 0];

        // DB::table evita que los enums de Transaccion se apliquen al resultado raw
        $grupos = DB::table('transacciones')
            ->where('sesion_caja_id', $this->sesionId)
            ->selectRaw('tipo, estado, COUNT(*) AS cnt, COALESCE(SUM(monto),0) AS tot')
            ->groupBy('tipo', 'estado')
            ->get()
            ->keyBy(fn($r) => $r->tipo . '_' . $r->estado);

        return [
            'ing_apr_cnt' => (int)   ($grupos['ingreso_aprobado']->cnt ?? 0),
            'ing_apr_tot' => (float) ($grupos['ingreso_aprobado']->tot ?? 0),
            'ing_anu_cnt' => (int)   ($grupos['ingreso_anulado']->cnt ?? 0),
            'ing_anu_tot' => (float) ($grupos['ingreso_anulado']->tot ?? 0),
            'egr_apr_cnt' => (int)   ($grupos['egreso_aprobado']->cnt ?? 0),
            'egr_apr_tot' => (float) ($grupos['egreso_aprobado']->tot ?? 0),
            'egr_anu_cnt' => (int)   ($grupos['egreso_anulado']->cnt ?? 0),
            'egr_anu_tot' => (float) ($grupos['egreso_anulado']->tot ?? 0),
        ];
    }

    // ── Helper: paginador vacío compatible con Livewire ───────────────────────

    private function emptyPaginator(string $pageName): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20, 1, ['pageName' => $pageName]);
    }
}
