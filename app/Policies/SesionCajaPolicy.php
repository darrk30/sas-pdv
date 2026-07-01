<?php

namespace App\Policies;

use App\Models\SesionCaja;
use App\Models\User;

class SesionCajaPolicy
{
    public function viewAny(User $user): bool { return $user->can('caja.sesiones'); }
    public function view(User $user, SesionCaja $record): bool { return $user->can('caja.sesiones'); }
    public function create(User $user): bool { return $user->can('caja.sesiones'); }
    public function update(User $user, SesionCaja $record): bool { return $user->can('caja.sesiones'); }
    public function delete(User $user, SesionCaja $record): bool { return $user->can('caja.sesiones'); }
}
