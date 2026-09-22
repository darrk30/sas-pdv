<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Permission;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

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
        $this->js('window.location.href = window.location.href');
    }

    private function sincronizarPermisos($role): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);

        $permisosSeleccionados = $this->recogerPermisosDelForm();
        $role->syncPermissions($permisosSeleccionados);

        $registrar->forgetCachedPermissions();
    }

    private function recogerPermisosDelForm(): \Illuminate\Support\Collection
    {
        $modulos = Permission::where('scope', 'admin')->select('module')->distinct()->pluck('module');
        $ids = collect();

        foreach ($modulos as $modulo) {
            $valores = $this->form->getRawState()["permisos_modulo_{$modulo}"] ?? [];
            $ids = $ids->merge(collect($valores)->filter());
        }

        return Permission::whereIn('id', $ids->unique()->values())->get();
    }
}
