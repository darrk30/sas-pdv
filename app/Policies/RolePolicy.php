<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool { return $user->can('roles.ver'); }
    public function view(User $user, Role $record): bool { return $user->can('roles.ver'); }
    public function create(User $user): bool { return $user->can('roles.crear'); }
    public function update(User $user, Role $record): bool { return $user->can('roles.editar'); }
    public function delete(User $user, Role $record): bool { return $user->can('roles.eliminar'); }
}
