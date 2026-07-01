<?php

namespace App\Policies;

use App\Models\Caja;
use App\Models\User;

class CajaPolicy
{
    public function viewAny(User $user): bool { return $user->can('config.cajas'); }
    public function view(User $user, Caja $record): bool { return $user->can('config.cajas'); }
    public function create(User $user): bool { return $user->can('config.cajas'); }
    public function update(User $user, Caja $record): bool { return $user->can('config.cajas'); }
    public function delete(User $user, Caja $record): bool { return $user->can('config.cajas'); }
}
