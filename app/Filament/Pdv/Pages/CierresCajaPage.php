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

    public function getHeading(): string      { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }

    // ── Filtros lista ─────────────────────────────────────────────────────────

    public ?string $filtroFechaDesde = null;
    public ?string $filtroFechaHasta = null;
    public ?string $filtroVendedor   = null;

    // ── Reporte modal ─────────────────────────────────────────────────────────

    public ?int $sesionId = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 3])->schema([

                DatePicker::make('filtroFechaDesde')
                    ->label('Desde')
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->afterStateUpdated(fn() => $this->resetPage()),

                DatePicker::make('filtroFechaHasta')
                    ->label('Hasta')
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->afterStateUpdated(fn() => $this->resetPage()),

                Select::make('filtroVendedor')
                    ->label('Cajero')
                    ->placeholder('Todos los cajeros')
                    ->options(fn() => User::whereHas('empresas', fn($q) => $q->where('empresa_id', Filament::getTenant()->id))
                        ->orderBy('name')->pluck('name', 'id')->toArray())
                    ->native(false)
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn() => $this->resetPage()),

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

    // ── Lista de sesiones ─────────────────────────────────────────────────────

    public function getSesiones(): LengthAwarePaginator
    {
        $q = SesionCaja::where('empresa_id', Filament::getTenant()->id)
            ->with(['caja', 'cajero:id,name'])
            ->orderBy('fecha_apertura', 'desc');

        if (! empty($this->filtroVendedor)) {
            $q->where('user_id', $this->filtroVendedor);
        }
        if (! empty($this->filtroFechaDesde)) {
            $q->whereDate('fecha_apertura', '>=', $this->filtroFechaDesde);
        }
        if (! empty($this->filtroFechaHasta)) {
            $q->whereDate('fecha_apertura', '<=', $this->filtroFechaHasta);
        }

        return $q->withCount([
            'pagos as tiene_cuadre' => fn($q) => $q->whereNotNull('importe_cajero'),
        ])->paginate(20);
    }

    // ── Modal: abrir / cerrar ─────────────────────────────────────────────────

    public function abrirReporte(int $id): void  { $this->sesionId = $id; }
    public function cerrarReporte(): void          { $this->sesionId = null; }

    // ── Datos del reporte ─────────────────────────────────────────────────────

    public function getReporte(): ?array
    {
        if (! $this->sesionId) return null;

        $sesion = SesionCaja::with(['caja', 'cajero:id,name', 'pagos.metodoPago'])
            ->find($this->sesionId);

        if (! $sesion || $sesion->empresa_id !== Filament::getTenant()->id) return null;

        $empresaId = $sesion->empresa_id;
        $sid       = $sesion->id;

        // ── Base query ventas de esta sesión ──────────────────────────────────
        $ventasQ = Venta::where('empresa_id', $empresaId)->where('sesion_caja_id', $sid);

        // ── ① Resumen por estado ──────────────────────────────────────────────
        $comp = (clone $ventasQ)->where('estado', EstadoVenta::Completada->value)
            ->selectRaw('
                COUNT(*)                                                    AS cnt,
                COALESCE(SUM(total), 0)                                     AS tot,
                COALESCE(SUM(costo_total), 0)                               AS costo,
                COALESCE(SUM(descuento_total), 0)                           AS descuento,
                COALESCE(SUM(igv), 0)                                       AS igv,
                COALESCE(SUM(op_gravadas + op_exoneradas + op_inafectas),0) AS neta
            ')->first();

        $anu = (clone $ventasQ)->where('estado', EstadoVenta::Anulada->value)
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(total),0) AS tot')
            ->first();

        $despCount = (clone $ventasQ)
            ->where('estado_despacho', EstadoVenta::PendienteEnvio->value)
            ->count();

        // ── ② Por tipo de comprobante ─────────────────────────────────────────
        $porComprobante = DB::table('ventas')
            ->join('series', 'ventas.serie_id', '=', 'series.id')
            ->where('ventas.sesion_caja_id', $sid)
            ->where('ventas.empresa_id', $empresaId)
            ->where('ventas.estado', EstadoVenta::Completada->value)
            ->select('series.tipo', DB::raw('COUNT(*) AS cnt'), DB::raw('COALESCE(SUM(ventas.total),0) AS tot'))
            ->groupBy('series.tipo')
            ->get()
            ->map(fn($r) => [
                'tipo'  => $r->tipo,
                'count' => (int) $r->cnt,
                'total' => (float) $r->tot,
            ]);

        // ── ③ Cuadre por método de pago ──────────────────────────────────────
        $sistemaPorMetodo = VentaPago::whereHas('venta', fn($q) => $q
                ->where('sesion_caja_id', $sid)
                ->where('estado', EstadoVenta::Completada->value))
            ->with('metodoPago:id,nombre')
            ->get()
            ->groupBy('metodo_pago_id')
            ->map(fn($pagos) => [
                'nombre'  => $pagos->first()->metodoPago?->nombre ?? 'N/A',
                'sistema' => (float) $pagos->sum('monto'),
            ]);

        $cajeroCuadre = $sesion->pagos->keyBy('metodo_pago_id');

        $metodosPago = $sistemaPorMetodo->map(function ($datos, $metodoId) use ($cajeroCuadre) {
            $caj = $cajeroCuadre->get($metodoId);
            return [
                'nombre'     => $datos['nombre'],
                'sistema'    => $datos['sistema'],
                'cajero'     => $caj ? (float) $caj->importe_cajero  : null,
                'diferencia' => $caj ? (float) $caj->diferencia       : null,
            ];
        })->values();

        // ── ④ Movimientos manuales (no vinculados a una Venta) ───────────────
        $movimientos = Transaccion::where('sesion_caja_id', $sid)
            ->whereNull('transaccionable_id')
            ->where('estado', EstadoMovimiento::Aprobado->value)
            ->with('metodoPago:id,nombre')
            ->orderBy('fecha')
            ->get();

        $ingresosManuales = $movimientos->where('tipo', TipoMovimiento::Ingreso)->values();
        $egresosManuales  = $movimientos->where('tipo', TipoMovimiento::Egreso)->values();

        // ── ⑤ Cortesías (precio unitario = 0 en ventas completadas) ──────────
        $cortesias = VentaDetalle::whereHas('venta', fn($q) => $q
                ->where('sesion_caja_id', $sid)
                ->where('estado', EstadoVenta::Completada->value))
            ->where('precio_unitario', 0)
            ->selectRaw('descripcion, COALESCE(SUM(cantidad), 0) AS qty, COUNT(*) AS veces')
            ->groupBy('descripcion')
            ->orderByDesc('qty')
            ->get();

        // ── ⑥ Descuentos por venta (detalle) ─────────────────────────────────
        $totalDescuentos = (float) $comp->descuento;

        // ── ⑦ Top 10 productos más vendidos ──────────────────────────────────
        $topProductos = VentaDetalle::whereHas('venta', fn($q) => $q
                ->where('sesion_caja_id', $sid)
                ->where('estado', EstadoVenta::Completada->value))
            ->where('precio_unitario', '>', 0)
            ->selectRaw('descripcion, COALESCE(SUM(cantidad),0) AS qty, COALESCE(SUM(total),0) AS tot')
            ->groupBy('descripcion')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        // ── ⑧ Ganancia del turno ──────────────────────────────────────────────
        $ventasNetas   = (float) $comp->neta;
        $costoTurno    = (float) $comp->costo;
        $utilidadTurno = $ventasNetas - $costoTurno;
        $margenTurno   = $ventasNetas > 0
            ? round($utilidadTurno / $ventasNetas * 100, 1)
            : 0.0;

        // ── ⑨ Lista completa de ventas ────────────────────────────────────────
        $ventas = (clone $ventasQ)
            ->with(['serie', 'pagos.metodoPago'])
            ->orderBy('created_at')
            ->get();

        return compact(
            'sesion',
            'comp', 'anu', 'despCount',
            'porComprobante',
            'metodosPago',
            'ingresosManuales', 'egresosManuales',
            'cortesias',
            'totalDescuentos',
            'topProductos',
            'ventasNetas', 'costoTurno', 'utilidadTurno', 'margenTurno',
            'ventas'
        );
    }
}
