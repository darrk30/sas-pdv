<?php

namespace App\Policies;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmpresaPolicy
{
    public function viewAny(User $user): bool { return $user->can('admin.empresas.ver'); }
    public function view(User $user, Empresa $empresa): bool { return $user->can('admin.empresas.ver'); }

    public function create(User $user): bool
    {
        // Admin del panel SaaS
        if ($user->can('admin.empresas.crear')) return true;

        // Administrador PDV: verificar límite de locales del plan
        $esAdmin = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $user->id)
            ->where('roles.name', 'Administrador')
            ->whereNotNull('model_has_roles.empresa_id')
            ->exists();

        if (! $esAdmin) return false;

        $empresas = $user->empresas()->with('suscripcion.plan')->get();
        $maxLocales = $empresas->max(fn ($e) => $e->suscripcion?->plan?->maximo_locales);

        if ($maxLocales === null) return true;

        return $empresas->count() < $maxLocales;
    }

    public function update(User $user, Empresa $empresa): bool { return $user->can('admin.empresas.editar'); }
    public function delete(User $user, Empresa $empresa): bool { return $user->can('admin.empresas.eliminar'); }
}
