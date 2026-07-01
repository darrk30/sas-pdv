<?php

namespace App\Policies;

use App\Models\Produccion;
use App\Models\User;

class ProduccionPolicy
{
    public function viewAny(User $user): bool { return $user->can('catalogo.produccion'); }
    public function view(User $user, Produccion $record): bool { return $user->can('catalogo.produccion'); }
    public function create(User $user): bool { return $user->can('catalogo.produccion'); }
    public function update(User $user, Produccion $record): bool { return $user->can('catalogo.produccion'); }
    public function delete(User $user, Produccion $record): bool { return $user->can('catalogo.produccion'); }
}
