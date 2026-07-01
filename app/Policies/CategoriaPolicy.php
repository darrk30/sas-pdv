<?php

namespace App\Policies;

use App\Models\Categoria;
use App\Models\User;

class CategoriaPolicy
{
    public function viewAny(User $user): bool { return $user->can('catalogo.categorias'); }
    public function view(User $user, Categoria $record): bool { return $user->can('catalogo.categorias'); }
    public function create(User $user): bool { return $user->can('catalogo.categorias'); }
    public function update(User $user, Categoria $record): bool { return $user->can('catalogo.categorias'); }
    public function delete(User $user, Categoria $record): bool { return $user->can('catalogo.categorias'); }
}
