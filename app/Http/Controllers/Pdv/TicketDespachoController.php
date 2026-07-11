<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class TicketDespachoController extends Controller
{
    public function show(int $id): Response
    {
        [$venta, $empresa] = $this->resolverVenta($id);

        $serie       = $venta->serie;
        $comprobante = ($serie?->serie ?? '---') . '-' . str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT);

        $pdf = Pdf::loadView('pdv.ticket-despacho', compact('venta', 'empresa'))
            ->setPaper([0, 0, 226.77, 1133.86], 'portrait')
            ->setOption('defaultFont', 'Courier')
            ->setOption('isRemoteEnabled', false)
            ->setOption('dpi', 150);

        return $pdf->stream("despacho-{$comprobante}.pdf");
    }

    private function resolverVenta(int $id): array
    {
        $slug    = explode('.', request()->getHost())[0];
        $empresa = Empresa::where('slug', $slug)->firstOrFail();

        $venta = Venta::where('empresa_id', $empresa->id)
            ->where('id', $id)
            ->with([
                'serie',
                'detalles',
                'cliente',
                'orden',
            ])
            ->firstOrFail();

        return [$venta, $empresa];
    }
}
