<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\EmpresaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmpresa extends CreateRecord
{
    protected static string $resource = EmpresaResource::class;

    public array $usuariosData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->usuariosData = $data['usuarios'] ?? [];
        unset($data['usuarios']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        
        // La misma lógica de sincronización
        $userIds = collect($this->usuariosData)->pluck('user_id')->toArray();
        $record->usuarios()->sync($userIds);

        foreach ($this->usuariosData as $usuarioRow) {
            $user = \App\Models\User::find($usuarioRow['user_id']);
            if ($user && isset($usuarioRow['roles'])) {
                $user->syncRoles($usuarioRow['roles']);
            }
        }
    }
}
