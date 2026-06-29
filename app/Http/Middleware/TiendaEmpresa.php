<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use Closure;
use Illuminate\Http\Request;

class TiendaEmpresa
{
    public function handle(Request $request, Closure $next): mixed
    {
        $slug    = explode('.', $request->getHost())[0];
        $empresa = Empresa::where('slug', $slug)->first();

        if (! $empresa) {
            abort(404, 'Tienda no encontrada.');
        }

        app()->instance('tienda.empresa', $empresa);

        return $next($request);
    }
}
