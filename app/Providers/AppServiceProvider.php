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
            if ($user->roles()->whereNull('empresa_id')->where('name', 'Super Administrador')->exists()) {
                return true;
            }
        });
    }
}
