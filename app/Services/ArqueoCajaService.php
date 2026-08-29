<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\SesionCaja;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ArqueoCajaService
{
    public function generarPdf(SesionCaja $sesion, Empresa $empresa, string $formato = 'a4'): Response
    {
        $datos = $this->recopilarDatos($sesion, $empresa);
        $datos['formato'] = $formato;

        $pdf = Pdf::loadView('pdf.arqueo-caja', $datos)
            ->setOption('defaultFont', $formato === 'ticket' ? 'Courier' : 'DejaVu Sans')
            ->setOption('isRemoteEnabled', false)
            ->setOption('dpi', 150);

        if ($formato === 'ticket') {
            // 80 mm de ancho, alto dinámico (máximo 1 metro de rollo)
            $pdf->setPaper([0, 0, 226.77, 2834.65], 'portrait');
        } else {
            $pdf->setPaper('a4', 'portrait');
        }

        $nombre = 'arqueo-caja-' . $sesion->id . '-' . $formato . '-' . now()->format('Ymd') . '.pdf';

        return $pdf->download($nombre);
    }

    public function recopilarDatos(SesionCaja $sesion, Empresa $empresa): array
    {
        // Q1 — relaciones (índices, muy rápido)
        $sesion->loadMissing(['caja', 'cajero:id,name', 'pagos.metodoPago:id,nombre']);

        // Q2 — ventas: completadas + anuladas en una sola pasada
        $ventas = DB::table('ventas')
            ->where('sesion_caja_id', $sesion->id)
            ->selectRaw("
                SUM(CASE WHEN estado = 'completada' THEN 1     ELSE 0 END) AS cnt_comp,
                SUM(CASE WHEN estado = 'anulada'    THEN 1     ELSE 0 END) AS cnt_anu,
                COALESCE(SUM(CASE WHEN estado = 'completada' THEN total           ELSE 0 END), 0) AS tot_total,
                COALESCE(SUM(CASE WHEN estado = 'completada' THEN igv             ELSE 0 END), 0) AS igv,
                COALESCE(SUM(CASE WHEN estado = 'completada' THEN costo_total     ELSE 0 END), 0) AS costo,
                COALESCE(SUM(CASE WHEN estado = 'completada' THEN descuento_total ELSE 0 END), 0) AS descuento,
                COALESCE(SUM(CASE WHEN estado = 'anulada'    THEN total           ELSE 0 END), 0) AS tot_anu
            ")
            ->first();

        // Q3 — comprobantes por tipo de serie
        $comprobantes = DB::table('ventas')
            ->join('series', 'ventas.serie_id', '=', 'series.id')
            ->where('ventas.sesion_caja_id', $sesion->id)
            ->where('ventas.estado', 'completada')
            ->selectRaw('series.tipo, COUNT(*) AS cnt, COALESCE(SUM(ventas.total), 0) AS total')
            ->groupBy('series.tipo')
            ->get();

        // Q4 — cortesías agrupadas por descripción
        $cortesias = DB::table('venta_detalles')
            ->join('ventas', 'venta_detalles.venta_id', '=', 'ventas.id')
            ->where('ventas.sesion_caja_id', $sesion->id)
            ->where('ventas.estado', 'completada')
            ->where('venta_detalles.precio_unitario', 0)
            ->selectRaw('
                venta_detalles.descripcion,
                COALESCE(SUM(venta_detalles.cantidad), 0)      AS qty,
                COUNT(DISTINCT venta_detalles.venta_id)        AS en_ventas
            ')
            ->groupBy('venta_detalles.descripcion')
            ->orderByDesc('qty')
            ->get();

        // Q5 — movimientos: resumen por tipo+estado (siempre 4 filas máximo)
        $movResumen = DB::table('transacciones')
            ->where('sesion_caja_id', $sesion->id)
            ->selectRaw('tipo, estado, COUNT(*) AS cnt, COALESCE(SUM(monto), 0) AS tot')
            ->groupBy('tipo', 'estado')
            ->get()
            ->keyBy(fn($r) => $r->tipo . '_' . $r->estado);

        // Q6 — movimientos manuales: detalle (excluye los generados por ventas)
        $movDetalle = DB::table('transacciones')
            ->leftJoin('metodos_pago', 'transacciones.metodo_pago_id', '=', 'metodos_pago.id')
            ->where('transacciones.sesion_caja_id', $sesion->id)
            ->whereNull('transacciones.transaccionable_id')
            ->select(
                'transacciones.tipo',
                'transacciones.estado',
                'transacciones.concepto',
                'transacciones.monto',
                'transacciones.fecha',
                'metodos_pago.nombre as metodo'
            )
            ->orderBy('transacciones.fecha')
            ->get();

        $generadoEn = now()->format('d/m/Y H:i:s');

        return compact(
            'sesion', 'empresa',
            'ventas', 'comprobantes', 'cortesias',
            'movResumen', 'movDetalle',
            'generadoEn'
        );
    }
}
