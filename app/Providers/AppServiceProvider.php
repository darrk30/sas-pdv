<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::before(function (\App\Models\User $user, string $ability) {
            // Consulta directa sin team scope de Spatie para evitar ambigüedad en empresa_id
            $esSuperAdmin = \Illuminate\Support\Facades\DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $user->id)
                ->where('model_has_roles.model_type', get_class($user))
                ->whereNull('model_has_roles.empresa_id')
                ->where('roles.name', 'Super Administrador')
                ->whereNull('roles.empresa_id')
                ->exists();

            if ($esSuperAdmin) {
                return true;
            }
        });
    }
}
