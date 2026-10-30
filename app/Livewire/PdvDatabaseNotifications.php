<?php

namespace App\Livewire;

use Filament\Livewire\DatabaseNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Attributes\Locked;

class PdvDatabaseNotifications extends DatabaseNotifications
{
    /**
     * Capturado en mount() para que persista en cada poll de Livewire.
     * Si lo calculáramos en getNotificationsQuery(), filament()->getTenant() puede
     * retornar null durante peticiones AJAX de polling.
     */
    #[Locked]
    public int $empresaId = 0;

    public function mount(): void
    {
        $this->empresaId = (int) (filament()->getTenant()?->id ?? 0);
    }

    public function getNotificationsQuery(): Builder | Relation
    {
        $query = parent::getNotificationsQuery();

        if ($this->empresaId > 0) {
            $query->where('data->empresa_id', $this->empresaId);
        }

        return $query;
    }
}
