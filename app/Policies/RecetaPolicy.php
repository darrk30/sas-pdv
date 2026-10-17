<?php

namespace App\Policies;

use App\Models\Receta;
use App\Models\User;

class RecetaPolicy
{
    public function viewAny(User $user): bool { return $user->can('recetas.ver'); }
    public function view(User $user, Receta $record): bool { return $user->can('recetas.ver'); }
    public function create(User $user): bool { return $user->can('recetas.crear'); }
    public function update(User $user, Receta $record): bool { return $user->can('recetas.editar'); }
    public function delete(User $user, Receta $record): bool { return $user->can('recetas.eliminar'); }
}
