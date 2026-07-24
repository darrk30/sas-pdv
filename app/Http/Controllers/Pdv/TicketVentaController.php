<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Venta;
use App\Services\PdfVentaService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TicketVentaController extends Controller
{
    /** Abre el ticket en pestaña nueva (para imprimir desde el navegador) */
    public function show(int $id): View
    {
        [$venta, $empresa] = $this->resolverVenta($id);

        $service  = app(PdfVentaService::class);
        $qrBase64 = $service->generarQrParaVenta($venta);

        return view('pdv.ticket-venta', compact('venta', 'empresa', 'qrBase64'));
    }

    /** Descarga el comprobante como PDF (boleta/factura A4 o ticket 80 mm según tipo) */
    public function pdf(int $id): Response
    {
        [$venta, $empresa] = $this->resolverVenta($id);

        $service = app(PdfVentaService::class);
        $pdf     = $service->generar($venta, $empresa);
        $nombre  = $service->nombreArchivo($venta);

        return $pdf->download($nombre);
    }

    /** PDF streameable vía URL firmada (para compartir sin login — expira en 24 h) */
    public function compartir(int $id): Response
    {
        [$venta, $empresa] = $this->resolverVenta($id);

        $service = app(PdfVentaService::class);
        $pdf     = $service->generar($venta, $empresa);
        $nombre  = $service->nombreArchivo($venta);

        return $pdf->stream($nombre);
    }

    private function resolverVenta(int $id): array
    {
        $slug    = explode('.', request()->getHost())[0];
        $empresa = Empresa::where('slug', $slug)->firstOrFail();

        $venta = Venta::where('empresa_id', $empresa->id)
            ->where('id', $id)
            ->with([
                'serie',
                'detalles.producto.unidadMedida',
                'detalles.variante.producto.unidadMedida',
                'pagos.metodoPago',
                'cliente',
            ])
            ->firstOrFail();

        return [$venta, $empresa];
    }
}
