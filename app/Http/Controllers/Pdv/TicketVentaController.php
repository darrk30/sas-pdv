<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TicketVentaController extends Controller
{
    /** Abre el ticket en pestaña nueva (para imprimir / guardar PDF desde el navegador) */
    public function show(int $id): View
    {
        [$venta, $empresa] = $this->resolverVenta($id);

        return view('pdv.ticket-venta', compact('venta', 'empresa'));
    }

    /** Descarga el ticket directamente como PDF */
    public function pdf(int $id): Response
    {
        [$venta, $empresa] = $this->resolverVenta($id);

        $serie       = $venta->serie;
        $comprobante = ($serie?->serie ?? 'TKT') . '-' . str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT);

        // 80 mm = 226.77 pt ; altura variable (400 mm = 1133.86 pt)
        $pdf = Pdf::loadView('pdv.ticket-venta-pdf', compact('venta', 'empresa'))
            ->setPaper([0, 0, 226.77, 1133.86], 'portrait')
            ->setOption('defaultFont', 'Courier')
            ->setOption('isRemoteEnabled', false)
            ->setOption('dpi', 150);

        $nombre = "ticket-{$comprobante}.pdf";

        return $pdf->download($nombre);
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
