<?php

namespace App\Policies;

use App\Models\Ajuste;
use App\Models\User;

class AjustePolicy
{
    public function viewAny(User $user): bool { return $user->can('ajustes.ver'); }
    public function view(User $user, Ajuste $record): bool { return $user->can('ajustes.ver'); }
    public function create(User $user): bool { return $user->can('ajustes.crear'); }
    public function update(User $user, Ajuste $record): bool { return $user->can('ajustes.ver'); }
    public function delete(User $user, Ajuste $record): bool { return $user->can('ajustes.ver'); }
}
