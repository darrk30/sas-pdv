<?php

namespace App\Policies;

use App\Models\Categoria;
use App\Models\User;

class CategoriaPolicy
{
    public function viewAny(User $user): bool { return $user->can('categorias.ver'); }
    public function view(User $user, Categoria $record): bool { return $user->can('categorias.ver'); }
    public function create(User $user): bool { return $user->can('categorias.crear'); }
    public function update(User $user, Categoria $record): bool { return $user->can('categorias.editar'); }
    public function delete(User $user, Categoria $record): bool { return $user->can('categorias.eliminar'); }
}
