<?php

namespace App\Policies;

use App\Models\Orden;
use App\Models\User;

class OrdenPolicy
{
    public function viewAny(User $user): bool { return $user->can('ordenes.ver'); }
    public function view(User $user, Orden $record): bool { return $user->can('ordenes.ver'); }
    public function create(User $user): bool { return $user->can('ordenes.gestionar'); }
    public function update(User $user, Orden $record): bool { return $user->can('ordenes.gestionar'); }
    public function delete(User $user, Orden $record): bool { return $user->can('ordenes.gestionar'); }
}
