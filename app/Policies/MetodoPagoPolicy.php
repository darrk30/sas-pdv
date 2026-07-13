<?php

namespace App\Policies;

use App\Models\MetodoPago;
use App\Models\User;

class MetodoPagoPolicy
{
    public function viewAny(User $user): bool { return $user->can('metodos_pago.ver'); }
    public function view(User $user, MetodoPago $record): bool { return $user->can('metodos_pago.ver'); }
    public function create(User $user): bool { return $user->can('metodos_pago.crear'); }
    public function update(User $user, MetodoPago $record): bool { return $user->can('metodos_pago.editar'); }
    public function delete(User $user, MetodoPago $record): bool { return $user->can('metodos_pago.eliminar'); }
}
