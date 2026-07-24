<?php

namespace App\Filament\Pdv\Resources\Roles\Pages;

use App\Filament\Pdv\Resources\Roles\RoleResource;
use App\Models\Permission;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['empresa_id'] = Filament::getTenant()?->id;
        $data['guard_name'] = 'web';

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->sincronizarPermisos($this->record);
    }

    private function sincronizarPermisos($role): void
    {
        $empresaId = Filament::getTenant()?->id;
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($empresaId);

        $permisosSeleccionados = $this->recogerPermisosDelForm();

        if ($permisosSeleccionados->isNotEmpty()) {
            $role->syncPermissions($permisosSeleccionados);
        }

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
