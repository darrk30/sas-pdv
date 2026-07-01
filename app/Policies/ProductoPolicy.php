<?php

namespace App\Policies;

use App\Models\Producto;
use App\Models\User;

class ProductoPolicy
{
    public function viewAny(User $user): bool { return $user->can('productos.ver'); }
    public function view(User $user, Producto $record): bool { return $user->can('productos.ver'); }
    public function create(User $user): bool { return $user->can('productos.crear'); }
    public function update(User $user, Producto $record): bool { return $user->can('productos.editar'); }
    public function delete(User $user, Producto $record): bool { return $user->can('productos.editar'); }
}
