<?php

namespace App\Filament\Pdv\Resources\Roles\Pages;

use App\Filament\Pdv\Resources\Roles\RoleResource;
use App\Models\Permission;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function afterSave(): void
    {
        $this->sincronizarPermisos($this->record);
    }

    private function sincronizarPermisos($role): void
    {
        $empresaId = Filament::getTenant()?->id;
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($empresaId);

        $permisosSeleccionados = $this->recogerPermisosDelForm();
        $role->syncPermissions($permisosSeleccionados);
    }

    private function recogerPermisosDelForm(): \Illuminate\Support\Collection
    {
        $modulos = Permission::select('module')->distinct()->pluck('module');
        $ids = collect();

        foreach ($modulos as $modulo) {
            $valores = $this->form->getRawState()["permisos_modulo_{$modulo}"] ?? [];
            $ids = $ids->merge(collect($valores)->filter());
        }

        return Permission::whereIn('id', $ids->unique()->values())->get();
    }
}
