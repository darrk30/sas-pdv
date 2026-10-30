<?php

namespace App\Jobs;

use App\Enums\EstadoVenta;
use App\Models\Empresa;
use App\Models\ReporteDescarga;
use App\Models\User;
use App\Services\GastoExportService;
use App\Services\InventarioExportService;
use App\Services\ProductoExportService;
use App\Services\KardexExportService;
use App\Services\ReporteComprasExportService;
use App\Services\ReporteGananciasExportService;
use App\Services\ReporteVendedorVentasExportService;
use App\Services\ReporteVendedoresExportService;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerarReporteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300;

    private const MAX_ROWS_PDF = 1_000;
    private const LABELS = [
        'compras'          => 'Reporte de compras',
        'ganancias'        => 'Reporte de ganancias',
        'vendedores'       => 'Reporte de vendedores',
        'vendedor_ventas'  => 'Ventas del vendedor',
        'kardex'           => 'Kardex de inventario',
        'inventario'       => 'Inventario',
        'gastos'           => 'Reporte de gastos',
        'productos'        => 'Exportar productos',
    ];

    public function __construct(private int $reporteId) {}

    public function handle(
        ReporteComprasExportService $comprasService,
        ReporteGananciasExportService $gananciasService,
        ReporteVendedoresExportService $vendedoresService,
        ReporteVendedorVentasExportService $vendedorVentasService,
        KardexExportService $kardexService,
        InventarioExportService $inventarioService,
        GastoExportService $gastoService,
        ProductoExportService $productoService
    ): void {
        ini_set('memory_limit', '-1');

        $reporte = ReporteDescarga::find($this->reporteId);
        if (! $reporte) { return; }
        $reporte->update(['estado' => 'procesando']);

        try {
            $empresa       = Empresa::findOrFail($reporte->empresa_id);
            $filtros       = $reporte->filtros;
            $tipo          = $reporte->tipo;
            $reporteNombre = $reporte->reporte;

            $extension     = $tipo === 'pdf' ? 'pdf' : 'xlsx';
            $nombreArchivo = $reporteNombre . '-' . now()->format('Ymd-His') . '.' . $extension;
            $storagePath   = "reportes/{$empresa->id}/{$nombreArchivo}";
            $rutaAbsoluta  = Storage::disk('local')->path($storagePath);
            @mkdir(dirname($rutaAbsoluta), 0755, true);

            $usuario       = User::find($reporte->user_id);
            $usuarioNombre = $usuario?->name ?? 'Sistema';

            $totalCount = 0;
            $truncado   = false;

            switch ($reporteNombre) {
                case 'compras':
                    $this->handleCompras($comprasService, $empresa, $filtros, $tipo, $rutaAbsoluta, $usuarioNombre, $totalCount, $truncado);
                    break;
                case 'ganancias':
                    $this->handleGanancias($gananciasService, $empresa, $filtros, $tipo, $rutaAbsoluta, $usuarioNombre, $totalCount, $truncado);
                    break;
                case 'vendedores':
                    $this->handleVendedores($vendedoresService, $empresa, $filtros, $tipo, $rutaAbsoluta, $usuarioNombre);
                    break;
                case 'vendedor_ventas':
                    $this->handleVendedorVentas($vendedorVentasService, $empresa, $filtros, $tipo, $rutaAbsoluta, $usuarioNombre, $totalCount, $truncado);
                    break;
                case 'kardex':
                    $this->handleKardex($kardexService, $empresa, $filtros, $tipo, $rutaAbsoluta, $usuarioNombre);
                    break;
                case 'inventario':
                    $this->handleInventario($inventarioService, $empresa, $filtros, $tipo, $rutaAbsoluta, $usuarioNombre);
                    break;
                case 'gastos':
                    $this->handleGastos($gastoService, $empresa, $tipo, $rutaAbsoluta, $usuarioNombre);
                    break;
                case 'productos':
                    $productoService->guardar($empresa, $rutaAbsoluta);
                    break;
                default:
                    throw new \RuntimeException("Reporte desconocido: {$reporteNombre}");
            }

            $reporte->update([
                'estado'         => 'listo',
                'path'           => $storagePath,
                'nombre_archivo' => $nombreArchivo,
                'expires_at'     => now()->addHours(24),
            ]);

            $label = self::LABELS[$reporteNombre] ?? $reporteNombre;
            if ($usuario) {
                $this->notificarConEmpresa(
                    $usuario,
                    $empresa,
                    Notification::make()
                        ->title("{$label} listo")
                        ->body($truncado
                            ? "Tu reporte ({$nombreArchivo}) contiene las primeras " . number_format(self::MAX_ROWS_PDF) . " de {$totalCount} registros (PDF tiene límite). Usa Excel para exportar todo."
                            : "Tu reporte ({$nombreArchivo}) está disponible para descargar."
                        )
                        ->success()
                        ->actions([
                            NotificationAction::make('descargar')
                                ->label('Ir a descargas')
                                ->url(route('filament.pdv.pages.reporte-descargas-page', ['tenant' => $empresa->slug]))
                                ->button(),
                        ])
                );
            }

        } catch (\Throwable $e) {
            $reporte->update(['estado' => 'error', 'error_mensaje' => $e->getMessage()]);
            $usuario  = User::find($reporte->user_id);
            $empresa  ??= Empresa::find($reporte->empresa_id);
            if ($usuario && $empresa) {
                $this->notificarConEmpresa(
                    $usuario,
                    $empresa,
                    Notification::make()
                        ->title('Error al generar reporte')
                        ->body('Ocurrió un error al generar el reporte. Intenta nuevamente.')
                        ->danger()
                );
            }
            throw $e;
        }
    }

    private function notificarConEmpresa(User $usuario, Empresa $empresa, Notification $notificacion): void
    {
        $data               = $notificacion->getDatabaseMessage();
        $data['empresa_id'] = $empresa->id;

        $usuario->notifications()->create([
            'id'   => (string) Str::uuid(),
            'type' => \Filament\Notifications\DatabaseNotification::class,
            'data' => $data,
        ]);

        // Broadcast directo (sin pasar por la cola) para que el campana actualice en tiempo real
        $channel = str_replace('\\', '.', get_class($usuario)) . '.' . $usuario->getKey();
        try {
            app(Broadcaster::class)->broadcast(
                [new PrivateChannel($channel)],
                'database-notifications.sent',
                []
            );
        } catch (\Throwable) {
            // Si Reverb no está disponible, el polling de 5s lo cubrirá
        }
    }

    // ── Compras ───────────────────────────────────────────────────────────────

    private function handleCompras(
        ReporteComprasExportService $service,
        Empresa $empresa, array $filtros, string $tipo,
        string $rutaAbsoluta, string $usuarioNombre,
        int &$totalCount, bool &$truncado
    ): void {
        $q = \App\Models\Compra::with(['proveedor:id,nombre', 'registradoPor:id,name'])
            ->withSum('pagos', 'monto')
            ->where('empresa_id', $empresa->id)
            ->orderByDesc('fecha_compra')
            ->orderByDesc('id');

        if (! empty($filtros['filtroFechaDesde']))      { $q->whereDate('fecha_compra', '>=', $filtros['filtroFechaDesde']); }
        if (! empty($filtros['filtroFechaHasta']))      { $q->whereDate('fecha_compra', '<=', $filtros['filtroFechaHasta']); }
        if (! empty($filtros['filtroUsuario']))         { $q->where('user_id', $filtros['filtroUsuario']); }
        if (! empty($filtros['filtroEstado']))          { $q->where('estado', $filtros['filtroEstado']); }
        if (! empty($filtros['filtroEstadoPago']))      { $q->where('estado_pago', $filtros['filtroEstadoPago']); }
        if (! empty($filtros['filtroTipoComprobante'])) { $q->where('tipo_comprobante', $filtros['filtroTipoComprobante']); }
        if (! empty($filtros['filtroSerie']))           { $q->where('serie', 'like', $filtros['filtroSerie'] . '%'); }
        if (! empty($filtros['filtroCorrelativo']))     { $q->where('correlativo', 'like', $filtros['filtroCorrelativo'] . '%'); }

        $maxRows    = $tipo === 'pdf' ? self::MAX_ROWS_PDF : null;
        $totalCount = (clone $q)->count();
        $truncado   = $maxRows !== null && $totalCount > $maxRows;
        $compras    = $maxRows ? $q->limit($maxRows)->get() : $q->get();

        $resumen          = $filtros['resumen'] ?? [];
        $filtrosInfo      = $filtros['filtrosInfo'] ?? [];
        $columnasVisibles = $filtros['columnasVisibles'] ?? [];

        if ($tipo === 'pdf') {
            $service->guardarPdf($compras, $filtrosInfo, $columnasVisibles, $resumen, $empresa, $rutaAbsoluta, $usuarioNombre);
        } else {
            $service->guardarExcel($compras, $filtrosInfo, $columnasVisibles, $resumen, $empresa, $rutaAbsoluta);
        }
    }

    // ── Ganancias ─────────────────────────────────────────────────────────────

    private function handleGanancias(
        ReporteGananciasExportService $service,
        Empresa $empresa, array $filtros, string $tipo,
        string $rutaAbsoluta, string $usuarioNombre,
        int &$totalCount, bool &$truncado
    ): void {
        $q = \App\Models\Venta::where('empresa_id', $empresa->id)
            ->where('estado', EstadoVenta::Completada->value)
            ->with(['serie', 'vendedor:id,name'])
            ->orderBy('created_at', 'desc');

        if (! empty($filtros['desde']))    { $q->where('created_at', '>=', $filtros['desde']); }
        if (! empty($filtros['hasta']))    { $q->where('created_at', '<=', $filtros['hasta']); }
        if (! empty($filtros['vendedor'])) { $q->where('vendedor_id', $filtros['vendedor']); }

        $maxRows    = $tipo === 'pdf' ? self::MAX_ROWS_PDF : null;
        $totalCount = (clone $q)->count();
        $truncado   = $maxRows !== null && $totalCount > $maxRows;
        $ventas     = $maxRows ? $q->limit($maxRows)->get() : $q->get();

        $resumen          = $filtros['resumen'] ?? [];
        $filtrosInfo      = $filtros['filtrosInfo'] ?? [];
        $columnasVisibles = $filtros['columnasVisibles'] ?? [];

        if ($tipo === 'pdf') {
            $service->guardarPdf($ventas, $filtrosInfo, $columnasVisibles, $resumen, $empresa, $rutaAbsoluta, $usuarioNombre);
        } else {
            $service->guardarExcel($ventas, $filtrosInfo, $columnasVisibles, $resumen, $empresa, $rutaAbsoluta);
        }
    }

    // ── Vendedores ────────────────────────────────────────────────────────────

    private function handleVendedores(
        ReporteVendedoresExportService $service,
        Empresa $empresa, array $filtros, string $tipo,
        string $rutaAbsoluta, string $usuarioNombre
    ): void {
        $q = DB::table('ventas as v')
            ->join('users as u', 'v.vendedor_id', '=', 'u.id')
            ->where('v.empresa_id', $empresa->id)
            ->where('v.estado', EstadoVenta::Completada->value);

        if (! empty($filtros['filtroFechaDesde'])) { $q->whereDate('v.created_at', '>=', $filtros['filtroFechaDesde']); }
        if (! empty($filtros['filtroFechaHasta'])) { $q->whereDate('v.created_at', '<=', $filtros['filtroFechaHasta']); }
        if (! empty($filtros['filtroVendedor']))   { $q->where('v.vendedor_id', $filtros['filtroVendedor']); }

        $vendedores = collect(
            $q->selectRaw("
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
            ->groupBy('v.vendedor_id', 'u.name')
            ->orderByDesc('ingresos')
            ->get()
        )->map(fn($r) => (array) $r);

        $resumen     = $filtros['resumen'] ?? [];
        $filtrosInfo = $filtros['filtrosInfo'] ?? [];

        if ($tipo === 'pdf') {
            $service->guardarPdf($vendedores, $filtrosInfo, $resumen, $empresa, $rutaAbsoluta, $usuarioNombre);
        } else {
            $service->guardarExcel($vendedores, $filtrosInfo, $resumen, $empresa, $rutaAbsoluta);
        }
    }

    // ── Vendedor Ventas ───────────────────────────────────────────────────────

    private function handleVendedorVentas(
        ReporteVendedorVentasExportService $service,
        Empresa $empresa, array $filtros, string $tipo,
        string $rutaAbsoluta, string $usuarioNombre,
        int &$totalCount, bool &$truncado
    ): void {
        $q = DB::table('ventas as v')
            ->join('series as s', 'v.serie_id', '=', 's.id')
            ->where('v.empresa_id', $empresa->id)
            ->where('v.estado', EstadoVenta::Completada->value)
            ->where('v.vendedor_id', $filtros['vendedorId'])
            ->selectRaw("
                v.id, s.serie, v.correlativo,
                v.cliente_nombre, v.total, v.igv, v.costo_total,
                v.monto_pagado, v.saldo_pendiente, v.estado_pago,
                v.total - v.igv - v.costo_total AS utilidad,
                v.created_at
            ")
            ->orderByDesc('v.created_at');

        if (! empty($filtros['fechaDesde'])) { $q->whereDate('v.created_at', '>=', $filtros['fechaDesde']); }
        if (! empty($filtros['fechaHasta'])) { $q->whereDate('v.created_at', '<=', $filtros['fechaHasta']); }
        if (! empty($filtros['serie']))       { $q->where('s.serie', $filtros['serie']); }
        if (! empty($filtros['correlativo'])) { $q->where('v.correlativo', 'like', $filtros['correlativo'] . '%'); }

        $maxRows    = $tipo === 'pdf' ? self::MAX_ROWS_PDF : null;
        $totalCount = $q->count();
        $truncado   = $maxRows !== null && $totalCount > $maxRows;
        $ventas     = collect($maxRows ? $q->limit($maxRows)->get() : $q->get())
            ->map(fn($r) => (array) $r);

        $vendedorNombre = $filtros['vendedorNombre'] ?? 'Vendedor';
        $filtrosInfo    = $filtros['filtrosInfo'] ?? [];
        $resumen        = $filtros['resumen'] ?? [];

        if ($tipo === 'pdf') {
            $service->guardarPdf($ventas, $filtrosInfo, $resumen, $empresa, $vendedorNombre, $rutaAbsoluta, $usuarioNombre);
        } else {
            $service->guardarExcel($ventas, $filtrosInfo, $resumen, $empresa, $vendedorNombre, $rutaAbsoluta);
        }
    }

    // ── Kardex ────────────────────────────────────────────────────────────────

    private function handleKardex(
        KardexExportService $service,
        Empresa $empresa, array $filtros, string $tipo,
        string $rutaAbsoluta, string $usuarioNombre
    ): void {
        $kardexFiltros = [
            'producto' => $filtros['producto'] ?? null,
            'desde'    => $filtros['desde'] ?? null,
            'hasta'    => $filtros['hasta'] ?? null,
            'tipo'     => $filtros['tipo'] ?? null,
            'origen'   => $filtros['origen'] ?? null,
        ];
        $resumen = $filtros['resumen'] ?? [];

        if ($tipo === 'pdf') {
            $service->guardarPdf($empresa, $kardexFiltros, $resumen, $rutaAbsoluta, $usuarioNombre);
        } else {
            $service->guardarExcel($empresa, $kardexFiltros, $resumen, $rutaAbsoluta);
        }
    }

    // ── Gastos ────────────────────────────────────────────────────────────────

    private function handleGastos(
        GastoExportService $service,
        Empresa $empresa, string $tipo,
        string $rutaAbsoluta, string $usuarioNombre
    ): void {
        if ($tipo === 'pdf') {
            $service->guardarPdf($empresa, $rutaAbsoluta, $usuarioNombre);
        } else {
            $service->guardarExcel($empresa, $rutaAbsoluta);
        }
    }

    // ── Inventario ────────────────────────────────────────────────────────────

    private function handleInventario(
        InventarioExportService $service,
        Empresa $empresa, array $filtros, string $tipo,
        string $rutaAbsoluta, string $usuarioNombre
    ): void {
        $stats = $filtros['stats'] ?? [];

        if ($tipo === 'pdf') {
            $service->guardarPdf($empresa, $stats, $rutaAbsoluta, $usuarioNombre);
        } else {
            $service->guardarExcel($empresa, $stats, $rutaAbsoluta);
        }
    }
}
