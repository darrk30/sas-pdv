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

        // Forzar recarga completa de página para que el menú SPA se actualice
        $this->js('window.location.href = window.location.href');
    }

    private function sincronizarPermisos($role): void
    {
        $empresaId = Filament::getTenant()?->id;
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($empresaId);

        $permisosSeleccionados = $this->recogerPermisosDelForm();
        $role->syncPermissions($permisosSeleccionados);

        // Forzar limpieza del caché de permisos (belt-and-suspenders)
        $registrar->forgetCachedPermissions();
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
