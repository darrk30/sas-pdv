<?php

namespace App\Policies;

use App\Models\Cupon;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CuponPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('cupones.ver');
    }

    public function view(User $user, Cupon $cupon): bool
    {
        return $user->can('cupones.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('cupones.crear');
    }

    public function update(User $user, Cupon $cupon): bool
    {
        return $user->can('cupones.editar');
    }

    public function delete(User $user, Cupon $cupon): bool
    {
        return $user->can('cupones.eliminar');
    }
}
