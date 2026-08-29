<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoMovimiento;
use App\Enums\EstadoVenta;
use App\Enums\TipoMovimiento;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use App\Helpers\OwnerScope;
use App\Models\SesionCaja;
use App\Models\Transaccion;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use UnitEnum;

class ReporteSesionPage extends Page
{
    use WithPagination;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.reporte-sesion';

    protected static string|BackedEnum|null $navigationIcon = null;
    protected static ?string $navigationLabel = null;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'reporte-sesion';

    #[Url]
    public ?int $sesionId = null;

    public string $tabReporte   = 'resumen';
    public string $subTabVentas = 'aprobadas';
    public string $subTabMov    = 'ing_apr';

    public function getBreadcrumbs(): array
    {
        $sesInfo = $this->getSesionInfo();
        $label   = $sesInfo
            ? ($sesInfo->caja?->nombre ?? 'Caja') . ' · ' . $sesInfo->fecha_apertura?->format('d/m/Y')
            : 'Reporte de Sesión';

        return [
            CierresCajaPage::getUrl() => 'Cierres de Caja',
            $label,
        ];
    }

    public function mount(): void
    {
        if (! $this->sesionId) {
            $this->redirect(CierresCajaPage::getUrl());
            return;
        }

        // Verificar que la sesión pertenezca a esta empresa (y al usuario si no es admin)
        $existe = SesionCaja::where('empresa_id', $this->empresaId())
            ->forCurrentUser()
            ->where('id', $this->sesionId)
            ->exists();

        if (! $existe) {
            $this->redirect(CierresCajaPage::getUrl());
        }
    }

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

    private function emptyPaginator(string $pageName): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20, 1, ['pageName' => $pageName]);
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

        $neta     = (float) $comp->neta;
        $costo    = (float) $comp->costo;
        $utilidad = $neta - $costo;
        $margen   = $neta > 0 ? round($utilidad / $neta * 100, 1) : 0.0;

        return [
            'cnt_comp'      => (int)   $comp->cnt,
            'cnt_anu'       => (int)   $anu->cnt,
            'tot_total'     => (float) $comp->tot_total,
            'igv'           => (float) $comp->igv,
            'neta'          => $neta,
            'costo'         => $costo,
            'descuento'     => (float) $comp->descuento,
            'utilidad'      => $utilidad,
            'margen'        => $margen,
            'tot_anu'       => (float) $anu->tot,
            'monto_apertura' => (float) ($sesion?->monto_apertura ?? 0),
            'total_sistema'  => (float) ($sesion?->total_sistema ?? 0),
            'total_cajero'   => (float) ($sesion?->total_cajero ?? 0),
            'diferencia'     => (float) ($sesion?->diferencia_total ?? 0),
            'total_creditos' => (float) ($sesion?->total_creditos ?? 0),
        ];
    }

    // ── Tab: Ventas ───────────────────────────────────────────────────────────

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

    // ── Tab: Productos vendidos ───────────────────────────────────────────────

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

    // ── Tab: Métodos y comprobantes ───────────────────────────────────────────

    public function getMetodosYComprobantesTab(): array
    {
        if (! $this->sesionId) return ['metodos' => collect(), 'comprobantes' => collect(), 'cuadre' => collect()];

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

        $ventaPagos = VentaPago::where('sesion_caja_id', $this->sesionId)
            ->with('metodoPago:id,nombre')
            ->get()
            ->groupBy('metodo_pago_id');

        $metodos = $sistemaMap->map(fn($sistema, $mpId) => [
                'metodo_pago_id' => $mpId,
                'nombre'         => $ventaPagos->get($mpId)?->first()?->metodoPago?->nombre
                                    ?? DB::table('metodos_pago')->where('id', $mpId)->value('nombre')
                                    ?? 'N/A',
                'sistema'        => $sistema,
                'count'          => $ventaPagos->get($mpId)?->groupBy('venta_id')->count() ?? 0,
            ])
            ->values();

        $sesion  = SesionCaja::with('pagos.metodoPago')->find($this->sesionId);
        $cuadre  = $sesion?->pagos ?? collect();

        $comprobantes = DB::table('ventas')
            ->join('series', 'ventas.serie_id', '=', 'series.id')
            ->where('ventas.sesion_caja_id', $this->sesionId)
            ->where('ventas.empresa_id', $this->empresaId())
            ->where('ventas.estado', EstadoVenta::Completada->value)
            ->select('series.tipo', DB::raw('COUNT(*) AS count'), DB::raw('COALESCE(SUM(ventas.total),0) AS total'))
            ->groupBy('series.tipo')
            ->get();

        return compact('metodos', 'cuadre', 'comprobantes');
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

        $ids = VentaDetalle::whereHas('venta', fn($q) => $q
                ->where('sesion_caja_id', $this->sesionId)
                ->where('estado', EstadoVenta::Completada->value))
            ->where('precio_unitario', 0)
            ->distinct()
            ->pluck('venta_id');

        return Venta::whereIn('id', $ids)
            ->with(['serie', 'pagos.metodoPago'])
            ->orderBy('created_at')
            ->paginate(15, pageName: 'cvp');
    }

    // ── Tab: Movimientos ──────────────────────────────────────────────────────

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

    public function getMovimientosTotales(): array
    {
        if (! $this->sesionId) return ['ing_apr_cnt' => 0, 'ing_apr_tot' => 0, 'ing_anu_cnt' => 0, 'ing_anu_tot' => 0, 'egr_apr_cnt' => 0, 'egr_apr_tot' => 0, 'egr_anu_cnt' => 0, 'egr_anu_tot' => 0];

        $grupos = DB::table('transacciones')
            ->where('sesion_caja_id', $this->sesionId)
            ->selectRaw('tipo, estado, COUNT(*) AS cnt, COALESCE(SUM(monto),0) AS tot')
            ->groupBy('tipo', 'estado')
            ->get()
            ->keyBy(fn($r) => $r->tipo . '_' . $r->estado);

        return [
            'ing_apr_cnt' => (int)   ($grupos['ingreso_aprobado']->cnt ?? 0),
            'ing_apr_tot' => (float) ($grupos['ingreso_aprobado']->tot ?? 0),
            'ing_anu_cnt' => (int)   ($grupos['ingreso_anulado']->cnt  ?? 0),
            'ing_anu_tot' => (float) ($grupos['ingreso_anulado']->tot  ?? 0),
            'egr_apr_cnt' => (int)   ($grupos['egreso_aprobado']->cnt  ?? 0),
            'egr_apr_tot' => (float) ($grupos['egreso_aprobado']->tot  ?? 0),
            'egr_anu_cnt' => (int)   ($grupos['egreso_anulado']->cnt   ?? 0),
            'egr_anu_tot' => (float) ($grupos['egreso_anulado']->tot   ?? 0),
        ];
    }
}
