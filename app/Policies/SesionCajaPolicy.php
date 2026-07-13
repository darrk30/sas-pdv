<?php

namespace App\Policies;

use App\Models\SesionCaja;
use App\Models\User;

class SesionCajaPolicy
{
    public function viewAny(User $user): bool { return $user->can('sesiones.ver'); }
    public function view(User $user, SesionCaja $record): bool { return $user->can('sesiones.ver'); }
    public function create(User $user): bool { return $user->can('sesiones.ver'); }
    public function update(User $user, SesionCaja $record): bool { return $user->can('sesiones.ver'); }
    public function delete(User $user, SesionCaja $record): bool { return $user->can('sesiones.eliminar'); }
}
