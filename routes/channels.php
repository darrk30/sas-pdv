<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Mapa de mesas: solo usuarios con algún rol en esa empresa
Broadcast::channel('mesas.{empresaId}', function ($user, $empresaId) {
    return \Illuminate\Support\Facades\DB::table('model_has_roles')
        ->where('model_type', get_class($user))
        ->where('model_id', $user->id)
        ->where('empresa_id', $empresaId)
        ->exists();
});
