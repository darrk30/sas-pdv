<?php

namespace App\Policies;

use App\Models\IngresoEgreso;
use App\Models\User;

class IngresoEgresoPolicy
{
    public function viewAny(User $user): bool { return $user->can('caja.ingresos_egresos'); }
    public function view(User $user, IngresoEgreso $record): bool { return $user->can('caja.ingresos_egresos'); }
    public function create(User $user): bool { return $user->can('caja.ingresos_egresos'); }
    public function update(User $user, IngresoEgreso $record): bool { return $user->can('caja.ingresos_egresos'); }
    public function delete(User $user, IngresoEgreso $record): bool { return $user->can('caja.ingresos_egresos'); }
}
