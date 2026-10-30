<?php

namespace App\Jobs;

use App\Enums\CondicionPago;
use App\Enums\EstadoVenta;
use App\Models\Empresa;
use App\Models\ReporteDescarga;
use App\Models\User;
use App\Models\Venta;
use App\Services\ReporteVentasExportService;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerarReporteVentasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300;

    public function __construct(private int $reporteId) {}

    private const MAX_ROWS_PDF = 1_000;

    public function handle(ReporteVentasExportService $service): void
    {
        ini_set('memory_limit', '-1');

        $reporte = ReporteDescarga::find($this->reporteId);
        if (! $reporte) { return; }

        $reporte->update(['estado' => 'procesando']);

        try {
            $empresa  = Empresa::findOrFail($reporte->empresa_id);
            $filtros  = $reporte->filtros;
            $tipo     = $reporte->tipo;

            // ── Construir query de ventas ─────────────────────────────────────
            $q = Venta::where('empresa_id', $empresa->id)
                ->with(['serie', 'pagos.metodoPago'])
                ->withCount([
                    'detalles',
                    'notas',
                    'detalles as cortesias_count' => fn($q) => $q->where('precio_unitario', 0),
                ])
                ->orderBy('created_at', 'desc');

            $this->aplicarFiltros($q, $filtros);

            $maxRows    = $tipo === 'pdf' ? self::MAX_ROWS_PDF : null;
            $totalCount = (clone $q)->count();
            $ventas     = $maxRows ? $q->limit($maxRows)->get() : $q->get();

            // ── Calcular resumen ──────────────────────────────────────────────
            $resumen = $this->calcularResumen($empresa->id, $filtros);

            // ── Guardar archivo ───────────────────────────────────────────────
            $extension     = $tipo === 'pdf' ? 'pdf' : 'xlsx';
            $nombreArchivo = 'reporte-ventas-' . now()->format('Ymd-His') . '.' . $extension;
            $storagePath   = "reportes/{$empresa->id}/{$nombreArchivo}";
            $rutaAbsoluta  = \Illuminate\Support\Facades\Storage::disk('local')->path($storagePath);

            @mkdir(dirname($rutaAbsoluta), 0755, true);

            $usuario = User::find($reporte->user_id);

            if ($tipo === 'pdf') {
                $service->guardarPdf(
                    $ventas,
                    $this->buildFiltrosInfo($filtros),
                    $filtros['columnasVisibles'] ?? [],
                    $resumen,
                    $empresa,
                    $rutaAbsoluta,
                    $usuario?->name ?? 'Sistema'
                );
            } else {
                $service->guardarExcel(
                    $ventas,
                    $this->buildFiltrosInfo($filtros),
                    $filtros['columnasVisibles'] ?? [],
                    $resumen,
                    $empresa,
                    $rutaAbsoluta
                );
            }

            $reporte->update([
                'estado'        => 'listo',
                'path'          => $storagePath,
                'nombre_archivo' => $nombreArchivo,
                'expires_at'    => now()->addHours(24),
            ]);

            // ── Notificar al usuario ──────────────────────────────────────────
            $truncado = $maxRows && $totalCount > $maxRows;
            if ($usuario) {
                $this->notificarConEmpresa(
                    $usuario,
                    $empresa,
                    Notification::make()
                        ->title('Reporte de ventas listo')
                        ->body($truncado
                            ? "Tu reporte ({$nombreArchivo}) contiene las primeras " . number_format($maxRows) . " de {$totalCount} ventas (PDF tiene límite de filas). Usa Excel para exportar todo."
                            : "Tu reporte ({$nombreArchivo}) con {$totalCount} registros está disponible para descargar."
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
            $reporte->update([
                'estado'         => 'error',
                'error_mensaje'  => $e->getMessage(),
            ]);

            $usuario = User::find($reporte->user_id);
            $empresa ??= \App\Models\Empresa::find($reporte->empresa_id);
            if ($usuario && $empresa) {
                $this->notificarConEmpresa(
                    $usuario,
                    $empresa,
                    Notification::make()
                        ->title('Error al generar reporte')
                        ->body('Ocurrió un error al generar el reporte de ventas. Intenta nuevamente.')
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

    // ── Replicar la lógica de aplicarFiltros ─────────────────────────────────

    private function aplicarFiltros(Builder $q, array $filtros): void
    {
        if (! empty($filtros['filtroCliente'])) {
            $b = $filtros['filtroCliente'];
            $q->where(fn($sub) => $sub
                ->where('cliente_nombre', 'like', "%{$b}%")
                ->orWhere('cliente_num_doc', 'like', "%{$b}%")
            );
        }
        if (! empty($filtros['filtroSerie'])) {
            $s = $filtros['filtroSerie'];
            $q->whereHas('serie', fn($sub) => $sub->where('serie', $s));
        }
        if (! empty($filtros['filtroCorrelativo'])) {
            $q->where('correlativo', 'like', "%{$filtros['filtroCorrelativo']}%");
        }
        if (! empty($filtros['filtroMetodo'])) {
            $m = $filtros['filtroMetodo'];
            $q->whereHas('pagos', fn($sub) => $sub->where('metodo_pago_id', $m));
        }
        if (! empty($filtros['filtroEstado'])) {
            $q->where('estado', $filtros['filtroEstado']);
        }
        if (! empty($filtros['filtroOrigen'])) {
            $q->where('tipo', $filtros['filtroOrigen']);
        }
        if (! empty($filtros['filtroFechaDesde'])) {
            $q->where('created_at', '>=', $filtros['filtroFechaDesde']);
        }
        if (! empty($filtros['filtroFechaHasta'])) {
            $q->where('created_at', '<=', $filtros['filtroFechaHasta']);
        }
    }

    private function calcularResumen(int $empresaId, array $filtros): array
    {
        $base = Venta::where('empresa_id', $empresaId);
        $this->aplicarFiltros($base, $filtros);

        $completada = EstadoVenta::Completada->value;
        $anulada    = EstadoVenta::Anulada->value;

        $row = (clone $base)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END), 0)                AS cnt_completadas,
                COALESCE(SUM(CASE WHEN estado = ? THEN monto_pagado ELSE 0 END), 0)      AS total_cobrado,
                COALESCE(SUM(CASE WHEN estado = ? THEN descuento_total ELSE 0 END), 0)   AS descuento_total,
                COALESCE(SUM(CASE WHEN estado = ? AND estado_pago IN ('pendiente','parcial') THEN saldo_pendiente ELSE 0 END), 0) AS credito_pendiente,
                COALESCE(SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END), 0)                AS cnt_anuladas
            ", [$completada, $completada, $completada, $completada, $anulada])
            ->first();

        $cortesias = (clone $base)
            ->where('estado', $completada)
            ->whereHas('detalles', fn($q) => $q->where('precio_unitario', 0))
            ->count();

        $porMetodo = DB::table('venta_pagos as vp')
            ->join('ventas as v', 'v.id', '=', 'vp.venta_id')
            ->join('metodos_pago as mp', 'mp.id', '=', 'vp.metodo_pago_id')
            ->where('v.empresa_id', $empresaId)
            ->where('v.estado', $completada)
            ->where(fn($q) => $q
                ->where('mp.condicion_pago', '!=', CondicionPago::Credito->value)
                ->orWhere('v.estado_pago', 'pendiente')
            )
            ->when(! empty($filtros['filtroFechaDesde']), fn($q) => $q->where('v.created_at', '>=', $filtros['filtroFechaDesde']))
            ->when(! empty($filtros['filtroFechaHasta']), fn($q) => $q->where('v.created_at', '<=', $filtros['filtroFechaHasta']))
            ->when(! empty($filtros['filtroEstado']),    fn($q) => $q->where('v.estado', $filtros['filtroEstado']))
            ->when(! empty($filtros['filtroOrigen']),    fn($q) => $q->where('v.tipo',   $filtros['filtroOrigen']))
            ->selectRaw('vp.metodo_pago_id, mp.nombre, SUM(vp.monto) as total')
            ->groupBy('vp.metodo_pago_id', 'mp.nombre')
            ->get()
            ->map(fn($r) => ['nombre' => $r->nombre ?? 'N/A', 'total' => (float) $r->total])
            ->values()
            ->toArray();

        return [
            'count'            => (int)   $row->cnt_completadas,
            'total'            => (float) $row->total_cobrado,
            'descuentoTotal'   => (float) $row->descuento_total,
            'creditoPendiente' => (float) $row->credito_pendiente,
            'anuladas'         => (int)   $row->cnt_anuladas,
            'cortesias'        => $cortesias,
            'porMetodo'        => $porMetodo,
        ];
    }

    private function buildFiltrosInfo(array $filtros): array
    {
        $info = [];
        if (! empty($filtros['filtroFechaDesde']) || ! empty($filtros['filtroFechaHasta'])) {
            $desde = ! empty($filtros['filtroFechaDesde'])
                ? \Illuminate\Support\Carbon::parse($filtros['filtroFechaDesde'])->format('d/m/Y H:i')
                : '—';
            $hasta = ! empty($filtros['filtroFechaHasta'])
                ? \Illuminate\Support\Carbon::parse($filtros['filtroFechaHasta'])->format('d/m/Y H:i')
                : '—';
            $info[] = "Período: {$desde} – {$hasta}";
        }
        if (! empty($filtros['filtroCliente']))    { $info[] = "Cliente: {$filtros['filtroCliente']}"; }
        if (! empty($filtros['filtroSerie']))       { $info[] = "Serie: {$filtros['filtroSerie']}"; }
        if (! empty($filtros['filtroEstado']))      { $info[] = "Estado: " . ucfirst($filtros['filtroEstado']); }
        if (! empty($filtros['filtroOrigen']))      { $info[] = "Origen: " . ucfirst($filtros['filtroOrigen']); }
        return $info;
    }
}
