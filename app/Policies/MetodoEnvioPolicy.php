<?php

namespace App\Policies;

use App\Models\MetodoEnvio;
use App\Models\User;

class MetodoEnvioPolicy
{
    public function viewAny(User $user): bool { return $user->can('metodos_envio.ver'); }
    public function view(User $user, MetodoEnvio $record): bool { return $user->can('metodos_envio.ver'); }
    public function create(User $user): bool { return $user->can('metodos_envio.crear'); }
    public function update(User $user, MetodoEnvio $record): bool { return $user->can('metodos_envio.editar'); }
    public function delete(User $user, MetodoEnvio $record): bool { return $user->can('metodos_envio.eliminar'); }
}
