<?php

namespace App\Policies;

use App\Models\Promocion;
use App\Models\User;

class PromocionPolicy
{
    public function viewAny(User $user): bool { return $user->can('promociones.ver'); }
    public function view(User $user, Promocion $record): bool { return $user->can('promociones.ver'); }
    public function create(User $user): bool { return $user->can('promociones.gestionar'); }
    public function update(User $user, Promocion $record): bool { return $user->can('promociones.gestionar'); }
    public function delete(User $user, Promocion $record): bool { return $user->can('promociones.gestionar'); }
}
