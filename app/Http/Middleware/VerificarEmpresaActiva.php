<?php

namespace App\Http\Middleware;

use App\Enums\EstadoGeneral;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerificarEmpresaActiva
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $empresa = Filament::getTenant();
        if ($empresa && $empresa->estado !== 'activo') {
            return redirect()->route('suspendido');
        }
        return $next($request);
    }
}
 