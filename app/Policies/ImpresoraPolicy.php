<?php

namespace App\Policies;

use App\Models\Impresora;
use App\Models\User;

class ImpresoraPolicy
{
    public function viewAny(User $user): bool { return $user->can('impresoras.ver'); }
    public function view(User $user, Impresora $record): bool { return $user->can('impresoras.ver'); }
    public function create(User $user): bool { return $user->can('impresoras.crear'); }
    public function update(User $user, Impresora $record): bool { return $user->can('impresoras.editar'); }
    public function delete(User $user, Impresora $record): bool { return $user->can('impresoras.eliminar'); }
}
