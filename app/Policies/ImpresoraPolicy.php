<?php

namespace App\Policies;

use App\Models\Impresora;
use App\Models\User;

class ImpresoraPolicy
{
    public function viewAny(User $user): bool { return $user->can('config.impresoras'); }
    public function view(User $user, Impresora $record): bool { return $user->can('config.impresoras'); }
    public function create(User $user): bool { return $user->can('config.impresoras'); }
    public function update(User $user, Impresora $record): bool { return $user->can('config.impresoras'); }
    public function delete(User $user, Impresora $record): bool { return $user->can('config.impresoras'); }
}
