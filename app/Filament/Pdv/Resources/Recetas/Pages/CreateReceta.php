<?php

namespace App\Filament\Pdv\Resources\Recetas\Pages;

use App\Filament\Pdv\Resources\Recetas\RecetaResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateReceta extends CreateRecord
{
    protected static string $resource = RecetaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['empresa_id'] = Filament::getTenant()->id;
        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->load([
            'detalles.unidadMedida',
            'detalles.insumoProducto.unidadMedida',
            'detalles.insumoVariante.producto.unidadMedida',
        ]);
        $this->record->sincronizarCosto();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
