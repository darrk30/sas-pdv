<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\SesionCaja;
use App\Services\ArqueoCajaService;
use Illuminate\Http\Response;

class ArqueoCajaController extends Controller
{
    public function pdf(int $id): Response
    {
        $slug    = explode('.', request()->getHost())[0];
        $empresa = Empresa::where('slug', $slug)->firstOrFail();

        $sesion = SesionCaja::where('empresa_id', $empresa->id)
            ->where('id', $id)
            ->firstOrFail();

        $formato = in_array(request()->query('formato'), ['ticket', 'a4']) ? request()->query('formato') : 'a4';

        return app(ArqueoCajaService::class)->generarPdf($sesion, $empresa, $formato);
    }
}
