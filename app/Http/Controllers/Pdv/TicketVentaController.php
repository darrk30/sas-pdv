<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Venta;
use Illuminate\View\View;

class TicketVentaController extends Controller
{
    public function show(int $id): View
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

        return view('pdv.ticket-venta', compact('venta', 'empresa'));
    }
}
