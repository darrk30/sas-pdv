<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    public function viewAny(User $user): bool { return $user->can('admin.planes.ver'); }
    public function view(User $user, Plan $plan): bool { return $user->can('admin.planes.ver'); }
    public function create(User $user): bool { return $user->can('admin.planes.crear'); }
    public function update(User $user, Plan $plan): bool { return $user->can('admin.planes.editar'); }
    public function delete(User $user, Plan $plan): bool { return $user->can('admin.planes.eliminar'); }
}
