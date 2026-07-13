<?php

namespace App\Policies;

use App\Models\Caja;
use App\Models\User;

class CajaPolicy
{
    public function viewAny(User $user): bool { return $user->can('cajas.ver'); }
    public function view(User $user, Caja $record): bool { return $user->can('cajas.ver'); }
    public function create(User $user): bool { return $user->can('cajas.crear'); }
    public function update(User $user, Caja $record): bool { return $user->can('cajas.editar'); }
    public function delete(User $user, Caja $record): bool { return $user->can('cajas.eliminar'); }
}
