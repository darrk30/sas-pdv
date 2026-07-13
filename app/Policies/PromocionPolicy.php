<?php

namespace App\Policies;

use App\Models\Promocion;
use App\Models\User;

class PromocionPolicy
{
    public function viewAny(User $user): bool { return $user->can('promociones.ver'); }
    public function view(User $user, Promocion $record): bool { return $user->can('promociones.ver'); }
    public function create(User $user): bool { return $user->can('promociones.crear'); }
    public function update(User $user, Promocion $record): bool { return $user->can('promociones.editar'); }
    public function delete(User $user, Promocion $record): bool { return $user->can('promociones.eliminar'); }
}
