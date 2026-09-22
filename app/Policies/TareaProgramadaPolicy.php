<?php

namespace App\Policies;

use App\Models\TareaProgramada;
use App\Models\User;

class TareaProgramadaPolicy
{
    public function viewAny(User $user): bool { return $user->can('admin.tareas.ver'); }
    public function view(User $user, TareaProgramada $tarea): bool { return $user->can('admin.tareas.ver'); }
    public function create(User $user): bool { return $user->can('admin.tareas.crear'); }
    public function update(User $user, TareaProgramada $tarea): bool { return $user->can('admin.tareas.editar'); }
    public function delete(User $user, TareaProgramada $tarea): bool { return $user->can('admin.tareas.eliminar'); }
}
