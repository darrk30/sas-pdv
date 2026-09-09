<?php

namespace App\Spatie;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * TeamResolver que resuelve el team_id desde Filament como fallback.
 *
 * Spatie llama getPermissionsTeamId() en CADA chequeo de permiso.
 * Si setPermissionsTeamId() no fue llamado (p.ej. request Livewire que
 * no pasó por ValidarEstadoUsuarioEmpresa), este resolver recupera el
 * tenant de Filament automáticamente, evitando que el usuario solo vea
 * la pantalla de Escritorio.
 */
class FilamentTeamResolver implements PermissionsTeamResolver
{
    protected int|string|null $teamId = null;

    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        $this->teamId = $id instanceof Model ? $id->getKey() : $id;
    }

    public function getPermissionsTeamId(): int|string|null
    {
        if ($this->teamId !== null) {
            return $this->teamId;
        }

        // Fallback: leer el tenant actual de Filament.
        // Esto cubre requests Livewire/SPA donde el middleware no llegó
        // a llamar setPermissionsTeamId antes del chequeo de permisos.
        try {
            $tenant = Filament::getTenant();
            return $tenant?->getKey();
        } catch (\Throwable) {
            return null;
        }
    }
}
