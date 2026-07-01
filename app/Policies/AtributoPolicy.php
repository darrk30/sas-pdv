<?php

namespace App\Policies;

use App\Models\Atributo;
use App\Models\User;

class AtributoPolicy
{
    public function viewAny(User $user): bool { return $user->can('catalogo.atributos'); }
    public function view(User $user, Atributo $record): bool { return $user->can('catalogo.atributos'); }
    public function create(User $user): bool { return $user->can('catalogo.atributos'); }
    public function update(User $user, Atributo $record): bool { return $user->can('catalogo.atributos'); }
    public function delete(User $user, Atributo $record): bool { return $user->can('catalogo.atributos'); }
}
