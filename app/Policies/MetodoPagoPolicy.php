<?php

namespace App\Policies;

use App\Models\MetodoPago;
use App\Models\User;

class MetodoPagoPolicy
{
    public function viewAny(User $user): bool { return $user->can('config.metodos_pago'); }
    public function view(User $user, MetodoPago $record): bool { return $user->can('config.metodos_pago'); }
    public function create(User $user): bool { return $user->can('config.metodos_pago'); }
    public function update(User $user, MetodoPago $record): bool { return $user->can('config.metodos_pago'); }
    public function delete(User $user, MetodoPago $record): bool { return $user->can('config.metodos_pago'); }
}
