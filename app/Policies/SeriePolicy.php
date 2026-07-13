<?php

namespace App\Policies;

use App\Models\Serie;
use App\Models\User;

class SeriePolicy
{
    public function viewAny(User $user): bool { return $user->can('series.ver'); }
    public function view(User $user, Serie $record): bool { return $user->can('series.ver'); }
    public function create(User $user): bool { return $user->can('series.crear'); }
    public function update(User $user, Serie $record): bool { return $user->can('series.editar'); }
    public function delete(User $user, Serie $record): bool { return $user->can('series.eliminar'); }
}
