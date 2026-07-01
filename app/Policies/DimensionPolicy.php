<?php

namespace App\Policies;

use App\Models\Dimension;
use App\Models\User;

class DimensionPolicy
{
    public function viewAny(User $user): bool { return $user->can('catalogo.dimensiones'); }
    public function view(User $user, Dimension $record): bool { return $user->can('catalogo.dimensiones'); }
    public function create(User $user): bool { return $user->can('catalogo.dimensiones'); }
    public function update(User $user, Dimension $record): bool { return $user->can('catalogo.dimensiones'); }
    public function delete(User $user, Dimension $record): bool { return $user->can('catalogo.dimensiones'); }
}
