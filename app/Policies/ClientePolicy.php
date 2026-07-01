<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

class ClientePolicy
{
    public function viewAny(User $user): bool { return $user->can('caja.clientes'); }
    public function view(User $user, Cliente $record): bool { return $user->can('caja.clientes'); }
    public function create(User $user): bool { return $user->can('caja.clientes'); }
    public function update(User $user, Cliente $record): bool { return $user->can('caja.clientes'); }
    public function delete(User $user, Cliente $record): bool { return $user->can('caja.clientes'); }
}
