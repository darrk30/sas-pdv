<?php

namespace App\Policies;

use App\Models\GastoFijo;
use App\Models\User;

class GastoFijoPolicy
{
    public function viewAny(User $user): bool                    { return $user->can('gastos_fijos.ver'); }
    public function view(User $user, GastoFijo $_g): bool        { return $user->can('gastos_fijos.ver'); }
    public function create(User $user): bool                     { return $user->can('gastos_fijos.crear'); }
    public function update(User $user, GastoFijo $_g): bool      { return $user->can('gastos_fijos.editar'); }
    public function delete(User $user, GastoFijo $_g): bool      { return $user->can('gastos_fijos.eliminar'); }
}
