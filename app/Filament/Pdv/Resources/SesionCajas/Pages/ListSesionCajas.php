<?php

namespace App\Filament\Pdv\Resources\SesionCajas\Pages;

use App\Filament\Pdv\Resources\SesionCajas\SesionCajaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSesionCajas extends ListRecords
{
    protected static string $resource = SesionCajaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Abrir Caja'),
        ];
    }
}
