<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Permission;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\PermissionRegistrar;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['empresa_id'] = null;
        $data['guard_name'] = 'web';

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->sincronizarPermisos($this->record);
    }

    private function sincronizarPermisos($role): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);

        $permisosSeleccionados = $this->recogerPermisosDelForm();

        if ($permisosSeleccionados->isNotEmpty()) {
            $role->syncPermissions($permisosSeleccionados);
        }

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
