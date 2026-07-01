<?php

namespace App\Policies;

use App\Models\Marca;
use App\Models\User;

class MarcaPolicy
{
    public function viewAny(User $user): bool { return $user->can('catalogo.marcas'); }
    public function view(User $user, Marca $record): bool { return $user->can('catalogo.marcas'); }
    public function create(User $user): bool { return $user->can('catalogo.marcas'); }
    public function update(User $user, Marca $record): bool { return $user->can('catalogo.marcas'); }
    public function delete(User $user, Marca $record): bool { return $user->can('catalogo.marcas'); }
}
