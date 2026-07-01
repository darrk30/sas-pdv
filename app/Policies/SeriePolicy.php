<?php

namespace App\Policies;

use App\Models\Serie;
use App\Models\User;

class SeriePolicy
{
    public function viewAny(User $user): bool { return $user->can('config.series'); }
    public function view(User $user, Serie $record): bool { return $user->can('config.series'); }
    public function create(User $user): bool { return $user->can('config.series'); }
    public function update(User $user, Serie $record): bool { return $user->can('config.series'); }
    public function delete(User $user, Serie $record): bool { return $user->can('config.series'); }
}
