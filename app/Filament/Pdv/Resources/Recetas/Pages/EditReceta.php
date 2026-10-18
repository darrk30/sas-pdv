<?php

namespace App\Filament\Pdv\Resources\Recetas\Pages;

use App\Filament\Pdv\Resources\Recetas\RecetaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReceta extends EditRecord
{
    protected static string $resource = RecetaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function afterSave(): void
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
