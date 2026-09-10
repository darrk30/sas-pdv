<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ValidarEstadoUsuarioEmpresa
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $empresa = Filament::getTenant();
        $user = Auth::user();
        if ($empresa && $user) {
            // Buscamos el registro en la tabla pivote para este usuario y esta empresa
            $pivot = \Illuminate\Support\Facades\DB::table('empresa_user')
                ->where('user_id', $user->id)
                ->where('empresa_id', $empresa->id)
                ->first();
            // Si el registro existe y su estado es explícitamente inactivo, lo bloqueamos
            if ($pivot && $pivot->estado !== null && $pivot->estado !== 'activo') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('filament.pdv.auth.login')
                    ->withErrors([
                        'email' => 'Tu acceso a este sistema ha sido suspendido por el administrador.',
                    ]);
            }
        }

        if ($empresa) {
            $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
            // Clave de caché única por empresa — evita que distintos tenants
            // compartan la misma entrada de caché de Spatie.
            $registrar->cacheKey = config('permission.cache.key') . '.empresa.' . $empresa->id;
            $registrar->setPermissionsTeamId($empresa->id);
        }

        return $next($request);
    }
}
