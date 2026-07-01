<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool { return $user->can('config.roles'); }
    public function view(User $user, Role $record): bool { return $user->can('config.roles'); }
    public function create(User $user): bool { return $user->can('config.roles'); }
    public function update(User $user, Role $record): bool { return $user->can('config.roles'); }
    public function delete(User $user, Role $record): bool { return $user->can('config.roles'); }
}
