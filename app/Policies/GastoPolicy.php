<?php

namespace App\Policies;

use App\Models\Gasto;
use App\Models\User;

class GastoPolicy
{
    public function viewAny(User $user): bool              { return $user->can('gastos.ver'); }
    public function view(User $user, Gasto $_g): bool      { return $user->can('gastos.ver'); }
    public function create(User $user): bool               { return $user->can('gastos.crear'); }
    public function update(User $user, Gasto $gasto): bool { return $user->can('gastos.crear') && ! $gasto->estaAnulado(); }
    public function delete(User $_u, Gasto $_g): bool      { return false; }
    public function anular(User $user, Gasto $gasto): bool { return $user->can('gastos.anular') && ! $gasto->estaAnulado(); }
    public function aprobar(User $user, Gasto $gasto): bool { return $user->can('gastos.crear') && ! $gasto->estaAnulado(); }
}
