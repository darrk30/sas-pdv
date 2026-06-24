<?php

namespace App\Filament\Pdv\Resources\IngresoEgresos\Pages;

use App\Filament\Pdv\Resources\IngresoEgresos\IngresoEgresoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIngresoEgresos extends ListRecords
{
    protected static string $resource = IngresoEgresoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo Movimiento'),
        ];
    }
}
