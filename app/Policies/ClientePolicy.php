<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

class ClientePolicy
{
    public function viewAny(User $user): bool { return $user->can('clientes.ver'); }
    public function view(User $user, Cliente $record): bool { return $user->can('clientes.ver'); }
    public function create(User $user): bool { return $user->can('clientes.crear'); }
    public function update(User $user, Cliente $record): bool { return $user->can('clientes.editar'); }
    public function delete(User $user, Cliente $record): bool { return $user->can('clientes.eliminar'); }
}
