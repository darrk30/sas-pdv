<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\EmpresaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmpresa extends EditRecord
{
    protected static string $resource = EmpresaResource::class;

    public array $usuariosData = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->usuariosData = $data['usuarios'] ?? [];
        unset($data['usuarios']);
        
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        // 1. Sincronizamos los usuarios con la tabla pivote
        $userIds = collect($this->usuariosData)->pluck('user_id')->toArray();
        $record->usuarios()->sync($userIds);

        // 2. Sincronizamos los roles de cada usuario
        foreach ($this->usuariosData as $usuarioRow) {
            $user = \App\Models\User::find($usuarioRow['user_id']);
            if ($user && isset($usuarioRow['roles'])) {
                $user->syncRoles($usuarioRow['roles']);
            }
        }
    }
}
