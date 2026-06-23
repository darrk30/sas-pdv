<?php

namespace App\Filament\Pdv\Resources\Ajustes\Pages;

use App\Filament\Pdv\Resources\Ajustes\AjusteResource;
use App\Services\InventarioCoreService;
use Filament\Resources\Pages\CreateRecord;

class CreateAjuste extends CreateRecord
{
    protected static string $resource = AjusteResource::class;

    /**
     * Se ejecuta DESPUÉS de que Filament guarda el Ajuste y sus detalles (Repeater).
     * En este punto todos los AjusteDetalle ya están en BD.
     */
    protected function afterCreate(): void
    {
        app(InventarioCoreService::class)->aplicarAjuste($this->record);
    }
}
