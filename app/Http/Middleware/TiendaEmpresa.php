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

        if ($empresa->carta_activa_cliente !== 'activo') {
            $usuario = auth()->user();
            $esAdmin = $usuario && $empresa->usuarios()
                ->where('users.id', $usuario->id)
                ->wherePivot('estado', 'activo')
                ->exists();

            if (! $esAdmin) {
                return response(view('tienda.catalogo-cerrado', compact('empresa')));
            }

            view()->share('esModoPreview', true);
        }

        return $next($request);
    }
}
