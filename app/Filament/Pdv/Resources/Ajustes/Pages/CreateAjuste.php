<?php

namespace App\Filament\Pdv\Resources\Ajustes\Pages;

use App\Filament\Pdv\Resources\Ajustes\AjusteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAjuste extends CreateRecord
{
    protected static string $resource = AjusteResource::class;

    // El ajuste se crea en estado 'borrador'.
    // El stock se aplica únicamente cuando el usuario confirma desde la tabla.
}
