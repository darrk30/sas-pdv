<?php

namespace App\Livewire;

use Filament\Livewire\DatabaseNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class PdvDatabaseNotifications extends DatabaseNotifications
{
    /**
     * Filtra las notificaciones por la empresa activa (tenant).
     * Evita que usuarios vinculados a varias empresas vean notificaciones cruzadas.
     */
    public function getNotificationsQuery(): Builder | Relation
    {
        $query = parent::getNotificationsQuery();

        $empresaId = filament()->getTenant()?->id;

        if ($empresaId) {
            $query->where('data->empresa_id', $empresaId);
        }

        return $query;
    }
}
