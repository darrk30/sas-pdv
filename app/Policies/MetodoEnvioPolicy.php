<?php

namespace App\Policies;

use App\Models\MetodoEnvio;
use App\Models\User;

class MetodoEnvioPolicy
{
    public function viewAny(User $user): bool { return $user->can('config.metodos_envio'); }
    public function view(User $user, MetodoEnvio $record): bool { return $user->can('config.metodos_envio'); }
    public function create(User $user): bool { return $user->can('config.metodos_envio'); }
    public function update(User $user, MetodoEnvio $record): bool { return $user->can('config.metodos_envio'); }
    public function delete(User $user, MetodoEnvio $record): bool { return $user->can('config.metodos_envio'); }
}
