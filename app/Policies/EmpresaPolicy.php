<?php

namespace App\Policies;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmpresaPolicy
{
    /**
     * Controla si el usuario puede crear un nuevo local (empresa).
     * - Solo Administradores pueden hacerlo.
     * - El total de empresas del usuario no puede superar el maximo_locales
     *   del plan más generoso entre sus empresas activas.
     */
    public function create(User $user): bool
    {
        // Debe ser Administrador en al menos una empresa
        $esAdmin = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $user->id)
            ->where('roles.name', 'Administrador')
            ->whereNotNull('model_has_roles.empresa_id')
            ->exists();

        if (! $esAdmin) return false;

        $empresas = $user->empresas()->with('suscripcion.plan')->get();

        // Sin plan configurado → no restringir (dejarlo al administrador SaaS)
        $maxLocales = $empresas->max(fn ($e) => $e->suscripcion?->plan?->maximo_locales);

        if ($maxLocales === null) return true;

        return $empresas->count() < $maxLocales;
    }
}
