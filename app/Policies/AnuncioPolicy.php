<?php

namespace App\Policies;

use App\Models\Anuncio;
use App\Models\User;

class AnuncioPolicy
{
    public function viewAny(User $user): bool { return $user->can('admin.anuncios.ver'); }
    public function view(User $user, Anuncio $anuncio): bool { return $user->can('admin.anuncios.ver'); }
    public function create(User $user): bool { return $user->can('admin.anuncios.crear'); }
    public function update(User $user, Anuncio $anuncio): bool { return $user->can('admin.anuncios.editar'); }
    public function delete(User $user, Anuncio $anuncio): bool { return $user->can('admin.anuncios.eliminar'); }
}
