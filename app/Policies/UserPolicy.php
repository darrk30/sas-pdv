<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool { return $user->can('config.usuarios'); }
    public function view(User $user, User $record): bool { return $user->can('config.usuarios'); }
    public function create(User $user): bool { return $user->can('config.usuarios'); }
    public function update(User $user, User $record): bool { return $user->can('config.usuarios'); }
    public function delete(User $user, User $record): bool { return $user->can('config.usuarios'); }
}
