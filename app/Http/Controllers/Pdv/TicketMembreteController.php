<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class TicketMembreteController extends Controller
{
    public function show(int $id): Response
    {
        [$venta, $empresa] = $this->resolverVenta($id);

        $serie       = $venta->serie;
        $comprobante = ($serie?->serie ?? '---') . '-' . str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT);

        $orden  = $venta->orden;
        $tieneTracking = ! empty($orden?->tracking_code);
        $tieneGeo      = $orden?->orden_departamento || $orden?->orden_provincia
                      || $orden?->orden_distrito     || $orden?->notas_internas;
        $tieneAgencia  = ! empty($orden?->direccion_agencia);

        // Altura dinámica: base 52mm + 18mm si tiene geo + 14mm si tiene agencia + 20mm si tiene tracking
        $alturaMm = 52
            + ($tieneGeo     ? 18 : 0)
            + ($tieneAgencia ? 14 : 0)
            + ($tieneTracking ? 20 : 0);
        $alturapt = $alturaMm * 2.8346; // mm → puntos

        $pdf = Pdf::loadView('pdv.ticket-membrete', compact('venta', 'empresa'))
            ->setPaper([0, 0, 226.77, $alturapt], 'portrait') // 80 mm × altura dinámica
            ->setOption('defaultFont', 'Helvetica')
            ->setOption('isRemoteEnabled', false)
            ->setOption('dpi', 150);

        return $pdf->stream("membrete-{$comprobante}.pdf");
    }

    private function resolverVenta(int $id): array
    {
        $slug    = explode('.', request()->getHost())[0];
        $empresa = Empresa::where('slug', $slug)->firstOrFail();

        $venta = Venta::where('empresa_id', $empresa->id)
            ->where('id', $id)
            ->with([
                'serie',
                'cliente',
                'orden.metodoEnvio',
            ])
            ->firstOrFail();

        return [$venta, $empresa];
    }
}
