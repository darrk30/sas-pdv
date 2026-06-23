<?php

namespace App\Filament\Pdv\Resources\Ajustes\Pages;

use App\Filament\Pdv\Resources\Ajustes\AjusteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAjuste extends CreateRecord
{
    protected static string $resource = AjusteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Asignamos el usuario autenticado al array de datos
        $data['user_id'] = auth()->id();
        
        return $data;
    }

    /**
     * Se ejecuta DESPUÉS de que Filament guarda el Ajuste y sus detalles (Repeater).
     * En este punto todos los AjusteDetalle ya están en BD.
     */
    protected function afterCreate(): void
    {
        app(InventarioCoreService::class)->aplicarAjuste($this->record);
    }
    // El ajuste se crea en estado 'borrador'.
    // El stock se aplica únicamente cuando el usuario confirma desde la tabla.
}
