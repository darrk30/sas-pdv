<?php

namespace App\Policies;

use App\Models\ListaPrecio;
use App\Models\User;

class ListaPrecioPolicy
{
    public function viewAny(User $user): bool { return $user->can('listas_precios.ver'); }
    public function view(User $user, ListaPrecio $record): bool { return $user->can('listas_precios.ver'); }
    public function create(User $user): bool { return $user->can('listas_precios.crear'); }
    public function update(User $user, ListaPrecio $record): bool { return $user->can('listas_precios.editar'); }
    public function delete(User $user, ListaPrecio $record): bool { return $user->can('listas_precios.eliminar'); }
}
