<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\EmpresaResource;
use App\Models\Empresa;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmpresa extends EditRecord
{
    protected static string $resource = EmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Empresas creadas antes del sistema de módulos tienen modulos_activos = null.
        // Mezclamos con los defaults para que los toggles aparezcan activos por defecto.
        $data['modulos_activos'] = array_merge(
            Empresa::defaultModulos(),
            $data['modulos_activos'] ?? [],
        );

        return $data;
    }
}